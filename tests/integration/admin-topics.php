<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\{ApiException, Database};
use App\Modules\Vocabulary\Repositories\TopicRepository;

function check(bool $ok, string $name): void {
    if (!$ok) throw new RuntimeException($name);
    echo "PASS: $name\n";
}
$db = Database::connection();
if (!str_ends_with((string) $db->query('SELECT DATABASE()')->fetchColumn(), '_test')) {
    throw new RuntimeException('Use a disposable *_test database.');
}
$repo = new TopicRepository();
$data = ['name' => 'Automatic topic', 'description' => null, 'image' => null, 'is_active' => 1];
if (($argv[1] ?? '') === 'worker') {
    $db->exec('SET innodb_lock_wait_timeout=3');
    $id = $repo->save(null, array_replace($data, ['name' => $argv[2]]));
    echo json_encode(['id' => $id, 'position' => (int) $repo->find($id)['position']], JSON_THROW_ON_ERROR);
    exit;
}

// Connection-local table tests empty-table allocation without removing project fixtures.
$db->exec('CREATE TEMPORARY TABLE topic_fixture_schema LIKE topics');
$db->exec('CREATE TEMPORARY TABLE topics LIKE topic_fixture_schema');
$db->exec('DROP TEMPORARY TABLE topic_fixture_schema');
$db->exec('ALTER TABLE topics AUTO_INCREMENT=1');
try {
    $first = $repo->save(null, $data + ['id' => 900, 'position' => 900]);
    check($first === 1 && (int) $repo->find($first)['position'] === 1, 'Empty table starts at ID/order 1 and ignores client values');
    $second = $repo->save(null, array_replace($data, ['name' => 'Hidden last topic', 'is_active' => 0]));
    $db->exec('UPDATE topics SET position=7 WHERE id=' . $second);
    $third = $repo->save(null, $data);
    check((int) $repo->find($third)['position'] === 8, 'Append after the highest order including hidden topics and gaps');
    check(count($repo->activeForSelection()) === 2, 'Existing active-topic selection still excludes hidden topics');
    $repo->save($first, array_replace($data, ['name' => 'Renamed topic', 'is_active' => 0, 'position' => 999, 'id' => 999]));
    $saved = $repo->find($first);
    check((int) $saved['id'] === 1 && (int) $saved['position'] === 1 && $saved['name'] === 'Renamed topic' && (int) $saved['is_active'] === 0, 'Edit/hide preserves ID and existing order');
    try { $repo->save(999999, $data); throw new RuntimeException('Missing topic was accepted'); }
    catch (ApiException $e) { check($e->errorCode === 'TOPIC_NOT_FOUND', 'Missing topics keep the existing 404 behavior'); }
    $db->exec('UPDATE topics SET position=2147483647 WHERE id=' . $third);
    try { $repo->save(null, $data); throw new RuntimeException('Order overflow accepted'); }
    catch (ApiException $e) { check($e->errorCode === 'TOPIC_ORDER_LIMIT', 'Order limit fails without changing existing rows'); }
    check((int) $db->query('SELECT COUNT(*) FROM topics')->fetchColumn() === 3, 'Failed saves roll back');
} finally {
    $db->exec('DROP TEMPORARY TABLE topics');
}

// Independent connections compete for the same index tail.
$marker = 'Auto-order test ' . bin2hex(random_bytes(5));
$processes = [];
$initial = (int) $db->query('SELECT COALESCE(MAX(position),0) FROM topics')->fetchColumn();
$db->beginTransaction();
$db->query('SELECT position FROM topics ORDER BY position DESC LIMIT 1 FOR UPDATE')->fetchColumn();
try {
    for ($i = 0; $i < 3; $i++) {
        $command = [PHP_BINARY];
        if (php_ini_loaded_file()) array_push($command, '-c', php_ini_loaded_file());
        array_push($command, __FILE__, 'worker', $marker . ' ' . $i);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start concurrency test');
        fclose($pipes[0]);
        $processes[] = [$process, $pipes[1], $pipes[2]];
    }
    usleep(150000);
    $db->commit();
    $positions = [];
    $ids = [];
    foreach ($processes as [$process, $out, $err]) {
        $text = stream_get_contents($out);
        $errors = stream_get_contents($err);
        fclose($out); fclose($err);
        $exit = proc_close($process);
        if ($exit !== 0) throw new RuntimeException('Concurrent create failed: ' . $text . $errors);
        $created = json_decode($text, true, 8, JSON_THROW_ON_ERROR);
        $positions[] = $created['position']; $ids[] = $created['id'];
    }
    $processes = [];
    sort($positions);
    check($positions === [$initial + 1, $initial + 2, $initial + 3] && count(array_unique($ids)) === 3, 'Simultaneous creates receive distinct automatic IDs and positions');
} finally {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($processes as [$process, $out, $err]) {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        if (is_resource($out)) fclose($out);
        if (is_resource($err)) fclose($err);
    }
    $delete = $db->prepare('DELETE FROM topics WHERE LOCATE(?,name)=1');
    $delete->execute([$marker]);
}
