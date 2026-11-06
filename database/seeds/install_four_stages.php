<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy file này bằng PHP CLI.');
}

use App\Core\Database;
use App\Modules\Learning\Services\ExerciseService;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$base = rtrim($argv[1] ?? '/doulingo/public', '/');

if (!preg_match('~^(?:/[A-Za-z0-9_-]+)*$~D', $base)) {
    exit("Đường dẫn public không hợp lệ.\n");
}

$stages = [
    ['drinks', 'Đồ uống và giao tiếp', [
        ['milk', 'sữa', 'noun', 'I drink milk.', 'Tôi uống sữa.'],
        ['tea', 'trà', 'noun', 'Would you like some tea?', 'Bạn có muốn uống trà không?'],
        ['coffee', 'cà phê', 'noun', 'I would like a coffee, please.', 'Cho tôi một ly cà phê.'],
        ['water', 'nước', 'noun', 'Can I have some water?', 'Cho tôi xin ít nước được không?'],
        ['juice', 'nước ép', 'noun', 'This juice is fresh.', 'Nước ép này tươi ngon.'],
        ['beer', 'bia', 'noun', 'A glass of beer, please.', 'Cho tôi một ly bia.'],
        ['hello', 'xin chào', 'interjection', 'Hello, my friend!', 'Xin chào bạn!'],
        ['goodbye', 'tạm biệt', 'interjection', 'Goodbye! See you tomorrow.', 'Tạm biệt! Hẹn gặp bạn ngày mai.'],
        ['thanks', 'cảm ơn', 'interjection', 'Thanks for your help.', 'Cảm ơn bạn đã giúp đỡ.'],
        ['sorry', 'xin lỗi', 'interjection', 'Sorry, I am late.', 'Xin lỗi, tôi đến muộn.'],
        ['welcome', 'chào mừng', 'interjection', 'Welcome to our class!', 'Chào mừng bạn đến lớp của chúng tôi!'],
        ['smile', 'mỉm cười', 'verb', 'Smile for the photo.', 'Hãy mỉm cười để chụp ảnh.'],
    ]],

    ['transport', 'Giao thông', [
        ['bike', 'xe đạp', 'noun', 'I ride my bike to school.', 'Tôi đạp xe đến trường.'],
        ['motorbike', 'xe máy', 'noun', 'He rides a motorbike.', 'Anh ấy đi xe máy.'],
        ['car', 'ô tô', 'noun', 'The car is blue.', 'Chiếc ô tô màu xanh dương.'],
        ['bus', 'xe buýt', 'noun', 'We go by bus.', 'Chúng tôi đi bằng xe buýt.'],
        ['taxi', 'xe taxi', 'noun', 'Let\'s take a taxi.', 'Chúng ta đi taxi nhé.'],
        ['train', 'tàu hỏa', 'noun', 'The train is at the station.', 'Tàu hỏa đang ở nhà ga.'],
        ['plane', 'máy bay', 'noun', 'The plane is in the sky.', 'Máy bay đang ở trên trời.'],
        ['helicopter', 'trực thăng', 'noun', 'A helicopter flies above us.', 'Một chiếc trực thăng bay phía trên chúng tôi.'],
        ['boat', 'thuyền', 'noun', 'The boat is on the river.', 'Chiếc thuyền đang ở trên sông.'],
        ['ship', 'tàu thủy', 'noun', 'The ship is very big.', 'Chiếc tàu thủy rất lớn.'],
        ['truck', 'xe tải', 'noun', 'The truck carries food.', 'Xe tải chở thực phẩm.'],
        ['traffic', 'giao thông', 'noun', 'There is a lot of traffic today.', 'Hôm nay xe cộ rất đông.'],
    ]],

    ['countries', 'Quốc gia', [
        ['vietnam', 'Việt Nam', 'proper noun', 'I live in Vietnam.', 'Tôi sống ở Việt Nam.'],
        ['america', 'Hoa Kỳ', 'proper noun', 'She lives in America.', 'Cô ấy sống ở Hoa Kỳ.'],
        ['england', 'Anh', 'proper noun', 'London is in England.', 'Luân Đôn nằm ở Anh.'],
        ['france', 'Pháp', 'proper noun', 'Paris is in France.', 'Paris nằm ở Pháp.'],
        ['germany', 'Đức', 'proper noun', 'Berlin is in Germany.', 'Berlin nằm ở Đức.'],
        ['canada', 'Canada', 'proper noun', 'Canada has many lakes.', 'Canada có nhiều hồ.'],
        ['australia', 'Úc', 'proper noun', 'Australia has kangaroos.', 'Úc có chuột túi.'],
        ['china', 'Trung Quốc', 'proper noun', 'Beijing is in China.', 'Bắc Kinh nằm ở Trung Quốc.'],
        ['japan', 'Nhật Bản', 'proper noun', 'Tokyo is in Japan.', 'Tokyo nằm ở Nhật Bản.'],
        ['korea', 'Hàn Quốc', 'proper noun', 'Seoul is in South Korea.', 'Seoul nằm ở Hàn Quốc.'],
        ['thailand', 'Thái Lan', 'proper noun', 'Bangkok is in Thailand.', 'Bangkok nằm ở Thái Lan.'],
        ['india', 'Ấn Độ', 'proper noun', 'New Delhi is in India.', 'New Delhi nằm ở Ấn Độ.'],
    ]],

    ['animals', 'Động vật', [
        ['cat', 'con mèo', 'noun', 'The cat is sleeping.', 'Con mèo đang ngủ.'],
        ['dog', 'con chó', 'noun', 'The dog is friendly.', 'Con chó rất thân thiện.'],
        ['bird', 'con chim', 'noun', 'The bird can fly.', 'Con chim có thể bay.'],
        ['fish', 'con cá', 'noun', 'The fish is swimming.', 'Con cá đang bơi.'],
        ['duck', 'con vịt', 'noun', 'The duck is in the pond.', 'Con vịt đang ở trong ao.'],
        ['cow', 'con bò', 'noun', 'The cow eats grass.', 'Con bò ăn cỏ.'],
        ['pig', 'con lợn', 'noun', 'The pig is on the farm.', 'Con lợn ở trong trang trại.'],
        ['horse', 'con ngựa', 'noun', 'The horse runs fast.', 'Con ngựa chạy nhanh.'],
        ['rabbit', 'con thỏ', 'noun', 'The rabbit has long ears.', 'Con thỏ có đôi tai dài.'],
        ['monkey', 'con khỉ', 'noun', 'The monkey climbs a tree.', 'Con khỉ leo cây.'],
        ['elephant', 'con voi', 'noun', 'The elephant is big.', 'Con voi rất lớn.'],
        ['tiger', 'con hổ', 'noun', 'The tiger has stripes.', 'Con hổ có những sọc vằn.'],
    ]],
];

function jsonValue(array $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

function questionData(string $type, array $words, int $index): array
{
    $word = $words[$index];

    $content = [
        'target_word_ids' => [(int) $word['id']],
    ];

    if ($type === 'translate_text') {
        $content += [
            'instruction' => 'Viết từ tiếng Anh',
            'prompt' => $word['vietnamese'],
        ];

        $answer = [
            'accepted_answers' => [$word['english']],
            'normalization' => [
                'trim' => true,
                'case_sensitive' => false,
            ],
        ];

        return [$content, $answer];
    }

    $image = $type === 'choose_image';
    $listen = $type === 'listen_select';

    $content['instruction'] = $image
        ? 'Chọn hình đúng'
        : ($listen ? 'Nghe và chọn từ' : 'Chọn nghĩa đúng');

    $content['prompt'] = $listen ? '' : $word['english'];

    if ($listen) {
        $content['audio'] = $word['audio'];
    }

    $content['options'] = [];
    $correctOptionId = '';

    for ($j = 0; $j < 4; $j++) {
        $optionId = bin2hex(random_bytes(12));

        if ($j === 0) {
            $correctOptionId = $optionId;
        }

        $candidate = $words[($index + $j) % count($words)];
        $field = $image ? 'image' : 'text';

        $content['options'][] = [
            'id' => $optionId,
            $field => $image
                ? $candidate['image']
                : ($listen
                    ? $candidate['english']
                    : $candidate['vietnamese']),
        ];
    }

    shuffle($content['options']);

    $answer = [
        'correct_option_ids' => [$correctOptionId],
    ];

    return [$content, $answer];
}

$db = null;
$locked = false;

try {
    // Kiểm tra media trước khi ghi database.
    $missing = [];

    foreach ($stages as [, , $rows]) {
        foreach ($rows as $row) {
            $slug = $row[0];

            foreach (['images' => 'png', 'audio' => 'mp3'] as $folder => $extension) {
                $path = $root
                    . '/public/assets/media/'
                    . $folder . '/'
                    . $slug . '.'
                    . $extension;

                if (!is_file($path) || !is_readable($path) || filesize($path) < 1) {
                    $missing[] = $folder . '/' . $slug . '.' . $extension;
                } elseif ($extension === 'png') {
                    $info = @getimagesize($path);

                    if ($info === false || $info[2] !== IMAGETYPE_PNG) {
                        $missing[] = $folder . '/' . $slug
                            . '.png (không phải PNG hợp lệ)';
                    }
                }
            }
        }
    }

    if ($missing !== []) {
        throw new RuntimeException(
            "Thiếu hoặc lỗi media:\n" . implode("\n", $missing)
        );
    }

    $db = Database::connection();

    $locked = (int) $db->query(
        "SELECT GET_LOCK('doulingo_content_4x4_v1', 5)"
    )->fetchColumn() === 1;

    if (!$locked) {
        throw new RuntimeException('Đang có một lần cài nội dung khác.');
    }

    $db->beginTransaction();

    $topics = $db->query(
        'SELECT * FROM topics ORDER BY position,id FOR UPDATE'
    )->fetchAll();

    $packDescription = '12 từ · 4 ải';
    $packNames = array_column($stages, 1);

    $installed = array_values(array_filter(
        $topics,
        static fn($t) =>
            $t['description'] === $packDescription
            && in_array($t['name'], $packNames, true)
    ));

    if ($installed !== []) {
        // Chạy lại chỉ kiểm tra, không thêm bản sao.
        if (count($installed) !== 4) {
            throw new RuntimeException(
                'Bộ 4 chặng đang không đầy đủ. Dừng để kiểm tra dữ liệu.'
            );
        }

        $grader = new ExerciseService();

        foreach ($stages as $position => [$key, $name, $rows]) {
            $matches = array_values(array_filter(
                $installed,
                static fn($t) => $t['name'] === $name
            ));

            if (
                count($matches) !== 1
                || (int) $matches[0]['is_active'] !== 1
                || (int) $matches[0]['position'] !== $position + 1
            ) {
                throw new RuntimeException(
                    'Thứ tự hoặc trạng thái bộ nội dung đã thay đổi.'
                );
            }

            $topicId = (int) $matches[0]['id'];

            $query = $db->prepare(
                'SELECT * FROM lessons
                 WHERE topic_id=? AND is_active=1
                 ORDER BY position'
            );
            $query->execute([$topicId]);
            $lessons = $query->fetchAll();

            if (count($lessons) !== 4) {
                throw new RuntimeException('Số ải của chặng không đúng.');
            }

            foreach ($lessons as $i => $lesson) {
                if (
                    (int) $lesson['position'] !== $i + 1
                    || (int) $lesson['question_count'] !== 12
                ) {
                    throw new RuntimeException('Cấu hình ải đã thay đổi.');
                }

                $query = $db->prepare(
                    'SELECT w.* FROM words w
                     JOIN lesson_words lw ON lw.word_id=w.id
                     WHERE lw.lesson_id=? AND w.is_active=1
                     ORDER BY w.id'
                );
                $query->execute([$lesson['id']]);
                $words = $query->fetchAll();

                $query = $db->prepare(
                    'SELECT * FROM exercises
                     WHERE lesson_id=? AND is_active=1
                     ORDER BY id'
                );
                $query->execute([$lesson['id']]);
                $exercises = $query->fetchAll();

                if (count($words) !== 12 || count($exercises) !== 12) {
                    throw new RuntimeException(
                        'Thiếu từ hoặc câu hỏi trong bộ đã cài.'
                    );
                }

                $counts = array_count_values(
                    array_column($exercises, 'type')
                );

                $expected = $i === 3
                    ? [
                        'choose_meaning' => 3,
                        'choose_image' => 3,
                        'listen_select' => 3,
                        'translate_text' => 3,
                    ]
                    : [
                        ['choose_meaning' => 12],
                        ['choose_image' => 12],
                        ['listen_select' => 12],
                    ][$i];

                ksort($counts);
                ksort($expected);

                if ($counts !== $expected) {
                    throw new RuntimeException(
                        'Dạng câu hỏi không khớp ải.'
                    );
                }

                foreach ($exercises as $exercise) {
                    if ($grader->snapshot($exercise, $words) === null) {
                        throw new RuntimeException(
                            'Có câu hỏi không hợp lệ trong bộ đã cài.'
                        );
                    }
                }
            }
        }

        $activeCount = count(array_filter(
            $topics,
            static fn($t) => (int) $t['is_active'] === 1
        ));

        if ($activeCount !== 4) {
            throw new RuntimeException(
                'Có chủ đề khác đang bật ngoài bộ 4 chặng.'
            );
        }

        $db->commit();

        echo "Bộ 4 chặng đã có đủ 48 từ, 16 ải, 192 câu. Không nạp trùng.\n";
    } else {
        // Chỉ tự động ẩn đúng bộ demo gốc.
        $demoIds = [];

        foreach ($topics as $topic) {
            if (!(int) $topic['is_active']) {
                continue;
            }

            $query = $db->prepare(
                'SELECT name FROM lessons
                 WHERE topic_id=?
                 ORDER BY position,id'
            );
            $query->execute([$topic['id']]);
            $names = $query->fetchAll(PDO::FETCH_COLUMN);

            if (
                $topic['name'] !== 'Đồ uống và giao tiếp'
                || $topic['description']
                    !== 'Bài mẫu để kiểm tra học từ qua trò chơi.'
                || $names !== [
                    'Đồ uống — cấp 1',
                    'Đồ uống — cấp 2',
                ]
            ) {
                throw new RuntimeException(
                    'Có nội dung khác ngoài demo gốc. '
                    . 'Dừng để tránh thay đổi nội dung của nhóm.'
                );
            }

            $demoIds[] = (int) $topic['id'];
        }

        // Dời vị trí cũ để dành 1..4 cho bốn chặng mới.
        $nextPosition = 10 + (int) $db->query(
            'SELECT COALESCE(MAX(position),0) FROM topics'
        )->fetchColumn();

        foreach ($topics as $topic) {
            if ((int) $topic['position'] <= 4) {
                $db->prepare(
                    'UPDATE topics SET position=? WHERE id=?'
                )->execute([
                    ++$nextPosition,
                    $topic['id'],
                ]);
            }
        }

        foreach ($demoIds as $id) {
            $db->prepare(
                'UPDATE topics SET is_active=0 WHERE id=?'
            )->execute([$id]);
        }

        $addTopic = $db->prepare(
            'INSERT INTO topics
             (name,description,image,position,is_active)
             VALUES (?,?,?,?,1)'
        );

        $addWord = $db->prepare(
            'INSERT INTO words
             (topic_id,english,vietnamese,part_of_speech,
              example,example_translation,image,audio,is_active)
             VALUES (?,?,?,?,?,?,?,?,1)'
        );

        $addLesson = $db->prepare(
            'INSERT INTO lessons
             (topic_id,name,level,position,
              question_count,pass_percent,xp_reward,is_active)
             VALUES (?,?,?,?,12,70,?,1)'
        );

        $link = $db->prepare(
            'INSERT INTO lesson_words (lesson_id,word_id)
             VALUES (?,?)'
        );

        $addExercise = $db->prepare(
            'INSERT INTO exercises
             (lesson_id,word_id,type,difficulty,
              content_json,answer_json,is_active)
             VALUES (?,?,?,?,?,?,1)'
        );

        $labels = [
            1 => 'Chọn nghĩa',
            2 => 'Chọn hình',
            3 => 'Luyện nghe',
            4 => 'Tổng hợp',
        ];

        $types = [
            'choose_meaning',
            'choose_image',
            'listen_select',
            'translate_text',
        ];

        $rewards = [
            1 => 10,
            2 => 15,
            3 => 20,
            4 => 25,
        ];

        $grader = new ExerciseService();

        foreach ($stages as $position => [$key, $name, $rows]) {
            $addTopic->execute([
                $name,
                $packDescription,
                $base . '/assets/media/images/' . $rows[0][0] . '.png',
                $position + 1,
            ]);

            $topicId = (int) $db->lastInsertId();
            $words = [];

            foreach ($rows as [$slug, $vietnamese, $part, $example, $translation]) {
                $english = $key === 'countries'
                    ? ucfirst($slug)
                    : $slug;

                $image = $base . '/media/images/' . $slug . '.png';
                $audio = $base . '/assets/media/audio//' . $slug . '.mp3';

                $addWord->execute([
                    $topicId,
                    $english,
                    $vietnamese,
                    $part,
                    $example,
                    $translation,
                    $image,
                    $audio,
                ]);

                $words[] = [
                    'id' => (int) $db->lastInsertId(),
                    'english' => $english,
                    'vietnamese' => $vietnamese,
                    'image' => $image,
                    'audio' => $audio,
                ];
            }

            for ($level = 1; $level <= 4; $level++) {
                $addLesson->execute([
                    $topicId,
                    'Ải ' . $level . ' · ' . $labels[$level],
                    $level,
                    $level,
                    $rewards[$level],
                ]);

                $lessonId = (int) $db->lastInsertId();

                foreach ($words as $word) {
                    $link->execute([$lessonId, $word['id']]);
                }

                foreach ($words as $i => $word) {
                    $type = $level === 4
                        ? $types[$i % 4]
                        : $types[$level - 1];

                    [$content, $answer] = questionData(
                        $type,
                        $words,
                        $i
                    );

                    $row = [
                        'word_id' => $word['id'],
                        'type' => $type,
                        'content_json' => jsonValue($content),
                        'answer_json' => jsonValue($answer),
                    ];

                    if ($grader->snapshot($row, $words) === null) {
                        throw new RuntimeException(
                            'Câu hỏi không hợp lệ: ' . $word['english']
                        );
                    }

                    $addExercise->execute([
                        $lessonId,
                        $word['id'],
                        $type,
                        $level,
                        $row['content_json'],
                        $row['answer_json'],
                    ]);
                }
            }
        }

        $db->commit();

        echo "Đã nạp 4 chặng, 48 từ, 16 ải, 192 câu hỏi.\n";
        echo "Demo cũ đã ẩn; lịch sử học và XP được giữ lại.\n";
    }
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(
        STDERR,
        "Chưa nạp dữ liệu: " . $error->getMessage() . "\n"
    );

    exit(1);
} finally {
    if ($locked && $db instanceof PDO) {
        $db->query(
            "SELECT RELEASE_LOCK('doulingo_content_4x4_v1')"
        );
    }
}