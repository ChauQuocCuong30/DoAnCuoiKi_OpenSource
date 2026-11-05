<?php
declare(strict_types=1);
namespace App\Modules\Admin\Controllers;
use App\Core\ApiException;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Modules\Admin\Services\AdminCsrf;
use App\Middleware\Auth;
use App\Modules\Admin\Requests\ContentInput as Input;
use App\Modules\Admin\Services\ContentCsvImporter;
use App\Modules\Vocabulary\Repositories\ContentRepository;
use App\Modules\Vocabulary\Controllers\DictionaryController;

// LIÊN QUAN CƯỜNG: dùng Auth/Session/Response chung; Admin ghi phải có CSRF.
final class ContentApiController
{
    public static function register(Router $router): void
    {
        $router->get('/api/v1/admin/dictionary', [
            DictionaryController::class,
            'lookup',
        ]);
        $router->post('/api/v1/admin/dictionary/words', [
            DictionaryWordController::class,
            'store',
        ]);
        $router->get('/api/v1/admin/content/bootstrap', [
            self::class,
            'bootstrap',
        ]);
        $router->post('/api/v1/admin/content/csv/preview', [
            self::class,
            'preview',
        ]);
        $router->post('/api/v1/admin/content/csv/commit', [
            self::class,
            'commit',
        ]);
        $router->get('/api/v1/admin/content/{kind}', [self::class, 'listing']);
        $router->get('/api/v1/admin/content/{kind}/{id}', [
            self::class,
            'show',
        ]);
        $router->post('/api/v1/admin/content/{kind}', [self::class, 'store']);
    }
    private function admin(): void
    {
        Auth::admin();
        Session::start();
        header('Cache-Control: no-store');
    }
    private function body(): array
    {
        $raw = file_get_contents('php://input', false, null, 0, 131073);
        if ($raw === false || strlen($raw) > 131072) {
            throw new ApiException(
                'PAYLOAD_TOO_LARGE',
                'JSON tối đa 128 KiB.',
                413,
            );
        }
        return Input::object($raw, 'request');
    }
    private function csrf(): void
    {
        AdminCsrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }
    public function bootstrap(array $params): void
    {
        $this->admin();
        Response::data([
            'csrf_token' => AdminCsrf::token(),
            'part_of_speech' => Input::POS,
            'exercise_types' => Input::TYPES,
            'csv_headers' => ContentCsvImporter::HEADERS,
        ]);
    }
    public function listing(array $params): void
    {
        $this->admin();
        $q = Input::text($_GET, 'q', 100, false);
        $page = Input::number($_GET['page'] ?? 1, 1, 1000000);
        $parent =
            ($_GET['parent_id'] ?? '') === ''
                ? null
                : Input::id($_GET['parent_id']);
        Response::data(
            (new ContentRepository())->listing(
                $params['kind'],
                $q,
                $page,
                $parent,
            ),
        );
    }
    public function show(array $params): void
    {
        $this->admin();
        Response::data(
            (new ContentRepository())->get(
                $params['kind'],
                Input::id($params['id']),
            ),
        );
    }
    public function store(array $params): void
    {
        $this->admin();
        $this->csrf();
        $b = $this->body();
        $id = ($b['id'] ?? '') === '' ? null : Input::id($b['id']);
        $repo = new ContentRepository();
        switch ($params['kind']) {
            case 'words':
                $data = Input::word($b);
                $saved = $repo->saveWord($id, $data['word'], $data['source']);
                break;
            case 'lessons':
                $saved = $repo->saveLesson($id, Input::lesson($b));
                break;
            case 'exercises':
                $saved = $repo->saveExercise($id, Input::exercise($b));
                break;
            default:
                throw new ApiException(
                    'NOT_FOUND',
                    'Loại nội dung không tồn tại.',
                    404,
                );
        }
        Response::data(['id' => $saved], $id === null ? 201 : 200);
    }
    public function preview(array $params): void
    {
        $this->admin();
        $this->csrf();
        unset($_SESSION['admin_content_csv']);
        $file = $_FILES['csv'] ?? null;
        if (
            !is_array($file) ||
            ($file['error'] ?? null) !== UPLOAD_ERR_OK ||
            !is_string($file['tmp_name'] ?? null) ||
            !is_uploaded_file($file['tmp_name'])
        ) {
            Input::fail(
                'Chọn file CSV hợp lệ, tối đa 1 MiB; kiểm tra upload_max_filesize nếu lỗi.',
            );
        }
        if (filesize($file['tmp_name']) > 1048576) {
            throw new ApiException('CSV_TOO_LARGE', 'CSV tối đa 1 MiB.', 413);
        }
        $preview = (new ContentCsvImporter(new ContentRepository()))->preview(
            (string) file_get_contents($file['tmp_name']),
        );
        $token = null;
        if (!$preview['errors']) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['admin_content_csv'] = [
                'token' => $token,
                'expires' => time() + 900,
                'rows' => $preview['rows'],
            ];
        }
        Response::data([
            'preview_token' => $token,
            'valid_count' => $preview['valid_count'],
            'errors' => $preview['errors'],
            'sample' => array_slice(
                array_column($preview['rows'], 'word'),
                0,
                20,
            ),
        ]);
    }
    public function commit(array $params): void
    {
        $this->admin();
        $this->csrf();
        $b = $this->body();
        $token = Input::text($b, 'preview_token', 64);
        $draft = $_SESSION['admin_content_csv'] ?? null;
        if (
            !is_array($draft) ||
            $draft['expires'] <= time() ||
            !hash_equals($draft['token'], $token)
        ) {
            throw new ApiException(
                'PREVIEW_EXPIRED',
                'Bản xem trước đã hết hạn hoặc bị thay thế.',
                422,
            );
        }
        if (isset($draft['result'])) {
            Response::data($draft['result']);
        }
        $result = (new ContentCsvImporter(new ContentRepository()))->commit(
            $draft['rows'],
        );
        $_SESSION['admin_content_csv']['result'] = $result;
        unset($_SESSION['admin_content_csv']['rows']);
        Response::data($result, 201);
    }
}
