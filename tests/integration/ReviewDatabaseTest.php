<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once __DIR__ . '/ReviewTestDatabase.php';

use App\Modules\Reviews\Services\ReviewModuleFactory;
use App\Modules\Reviews\Requests\ReviewException;
use App\Modules\Reviews\Controllers\ReviewController;

$config = ReviewTestDatabase::configuration();
$pdo = ReviewTestDatabase::connect($config);
$services = ReviewModuleFactory::fromPdo($pdo);

$users = [];
$trigger = null;
$checks = 0;
$skips = 0;
$failed = false;
$bridgeTopic = null;

function dbCheck(bool $condition, string $message): void
{
    global $checks;

    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    $checks++;
    echo 'PASS: ' . $message . PHP_EOL;
}

function requestKey(): string
{
    $h = bin2hex(random_bytes(16));

    return substr($h, 0, 8)
        . '-' . substr($h, 8, 4)
        . '-4' . substr($h, 13, 3)
        . '-a' . substr($h, 17, 3)
        . '-' . substr($h, 20, 12);
}

function expectCode(callable $operation, string $code): void
{
    try {
        $operation();
    } catch (ReviewException $error) {
        dbCheck(
            $error->errorCode === $code,
            'Expected error ' . $code
        );
        return;
    }

    throw new RuntimeException('Missing error ' . $code);
}

function fetchState(PDO $pdo, int $id): array
{
    $query = $pdo->prepare(
        'SELECT * FROM user_review_states WHERE id=?'
    );
    $query->execute([$id]);

    return $query->fetch(PDO::FETCH_ASSOC);
}

// Chay cac worker de kiem tra gui ket qua dong thoi.
function parallelSubmit(array $jobs): array
{
    $processes = [];
    $paths = [];

    try {
        foreach ($jobs as $job) {
            $file = tempnam(sys_get_temp_dir(), 'tu_sm2_');
            $paths[] = $file;

            file_put_contents(
                $file,
                json_encode($job, JSON_THROW_ON_ERROR)
            );

            $pipes = [];

            $process = proc_open(
                [PHP_BINARY, __DIR__ . '/submit_worker.php', $file],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes
            );

            if (!is_resource($process)) {
                throw new RuntimeException('Cannot launch worker.');
            }

            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }

        $results = [];

        foreach ($processes as [$process, $pipes]) {
            $raw = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException('Worker failed: ' . $error);
            }

            $results[] = json_decode(
                $raw,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        }

        return $results;
    } finally {
        foreach ($processes as [$process, $pipes]) {
            if (is_resource($process)) {
                proc_terminate($process);

                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }

                proc_close($process);
            }
        }

        foreach ($paths as $file) {
            @unlink($file);
        }
    }
}

try {
    // 1. Kiem tra du lieu mau cua nhom truong.
    dbCheck(
        (int) $pdo->query(
            'SELECT COUNT(*) FROM words WHERE id IN (1,2)'
        )->fetchColumn() === 2,
        'Leader seed words exist'
    );

    // Tao hai tai khoan thu rieng.
    foreach (['Asia/Ho_Chi_Minh', 'UTC'] as $zone) {
        $query = $pdo->prepare(
            'INSERT INTO users (name,email,password_hash,timezone)
             VALUES (?,?,?,?)'
        );

        $query->execute([
            'SM2 integration only',
            'sm2-' . bin2hex(random_bytes(12)) . '@example.invalid',
            password_hash(
                bin2hex(random_bytes(16)),
                PASSWORD_DEFAULT
            ),
            $zone,
        ]);

        $users[] = (int) $pdo->lastInsertId();
    }

    [$u1, $u2] = $users;

    // Moc thoi gian gia lap, giu nguyen khi chay test.
    $now = new DateTimeImmutable('2030-01-02T17:01:00Z');
    $learned = $now->sub(new DateInterval('P2D'));

    // 2. Moi user/tu/chieu co trang thai rieng.
    $init = $services['initializer']->initialize(
        $u1,
        [1, 2],
        ['en_vi', 'vi_en'],
        $learned
    );

    dbCheck(
        $init['initialized'] === 4,
        'State per user + word + direction'
    );

    $init2 = $services['initializer']->initialize(
        $u2,
        [1],
        ['en_vi'],
        $learned
    );

    dbCheck(
        $init2['initialized'] === 1,
        'Second user owns separate state'
    );

    // 3. Khong tao the nghe khi chua co audio.
    $missingAudio = $services['initializer']->initialize(
        $u1,
        [1],
        ['listening'],
        $learned
    );

    dbCheck(
        count($missingAudio['skipped']) === 1,
        'Missing audio skips listening card'
    );

    $query = $pdo->prepare(
        'SELECT * FROM user_review_states
         WHERE user_id=? ORDER BY id'
    );
    $query->execute([$u1]);
    $before = $query->fetchAll(PDO::FETCH_ASSOC);

    // 4. Hoc lai khong reset lich.
    $again = $services['initializer']->initialize(
        $u1,
        [1, 2],
        ['en_vi', 'vi_en'],
        $now
    );

    $query->execute([$u1]);

    dbCheck(
        $before === $query->fetchAll(PDO::FETCH_ASSOC)
            && $again['initialized'] === 0,
        'Learning again preserves schedules'
    );

    $cards = [];

    foreach ($before as $state) {
        $key = $state['word_id'] . ':' . $state['direction'];
        $cards[$key] = (int) $state['id'];
    }

    // 5. The qua han phai duoc uu tien.
    $id = $cards['1:en_vi'];

    $pdo->prepare(
        'UPDATE user_review_states SET next_review_at=? WHERE id=?'
    )->execute(['2029-12-31 18:01:00', $id]);

    $page = $services['reviews']->due(
        $u1, 1, null, null, $now
    );

    dbCheck(
        $page['items'][0]['id'] === $id
            && $page['meta']['total_due'] === 4,
        'Overdue first, not only today'
    );

    // 6. Phan trang khong lap hoac bo sot the.
    $seen = [];
    $cursor = null;

    do {
        $page = $services['reviews']->due(
            $u1, 1, $cursor, null, $now
        );

        foreach ($page['items'] as $item) {
            $seen[] = $item['id'];
        }

        $cursor = $page['meta']['next_cursor'];
    } while ($cursor !== null);

    dbCheck(
        count($seen) === 4 && count(array_unique($seen)) === 4,
        'Pagination contains each due card once'
    );

    // 7. Khong on the cua user khac.
    expectCode(function () use ($services, $u2, $id, $now): void {
        $services['reviews']->start($u2, [
            'review_state_id' => $id,
            'request_key' => requestKey(),
        ], $now);
    }, 'NOT_FOUND');

    // 8. Gui lai start cung key khong tao phien moi.
    $startKey = requestKey();

    $start = $services['reviews']->start($u1, [
        'review_state_id' => $id,
        'request_key' => $startKey,
    ], $now);

    $startAgain = $services['reviews']->start($u1, [
        'review_state_id' => $id,
        'request_key' => $startKey,
    ], $now);

    dbCheck(
        $start === $startAgain,
        'Start retry reuses session'
    );

    dbCheck(
        strpos(json_encode($start), 'state_stamp') === false,
        'Private fingerprint stays on server'
    );

    // Tao san thong ke Learning de kiem tra khong bi ghi de.
    $pdo->prepare(
        'INSERT INTO user_daily_activity
         (user_id,activity_date,xp_total,completed_lessons,streak_qualified)
         VALUES (?,?,7,2,0)'
    )->execute([$u1, '2030-01-03']);

    // 9. Tu danh gia sai: giam EF va reset so lan nho.
    $body = [
        'session_question_id' => $start['session_question_id'],
        'request_key' => requestKey(),
        'quality' => 2,
    ];

    $result = $services['reviews']->submit($u1, $body, $now);

    dbCheck(
        $result['state']['ease_factor'] == 2.18
            && $result['state']['repetitions'] === 0
            && $result['state']['interval_days'] === 1,
        'Wrong answer resets and lowers EF'
    );

    dbCheck(
        $result['activity_date'] === '2030-01-03',
        'Activity uses user timezone'
    );

    // 10. Retry tra ket qua cu ke ca sau han va doi timezone.
    $pdo->prepare(
        "UPDATE users SET timezone='UTC' WHERE id=?"
    )->execute([$u1]);

    $retry = $services['reviews']->submit(
        $u1,
        $body,
        $now->add(new DateInterval('PT2H'))
    );

    dbCheck(
        json_encode($retry) === json_encode($result),
        'Retry returns original result'
    );

    $pdo->prepare(
        "UPDATE users SET timezone='Asia/Ho_Chi_Minh' WHERE id=?"
    )->execute([$u1]);

    // Cung key nhung noi dung khac phai bi tu choi.
    $changed = $body;
    $changed['quality'] = 5;

    expectCode(function () use (
        $services, $u1, $changed, $now
    ): void {
        $services['reviews']->submit($u1, $changed, $now);
    }, 'REQUEST_KEY_REUSED');

    // 11. Khong thay doi XP, so bai hoc va quy tac streak.
    $query = $pdo->prepare(
        'SELECT xp_total,completed_lessons,streak_qualified
         FROM user_daily_activity
         WHERE user_id=? AND activity_date=?'
    );
    $query->execute([$u1, '2030-01-03']);
    $activity = $query->fetch(PDO::FETCH_ASSOC);

    dbCheck(
        (int) $activity['xp_total'] === 7
            && (int) $activity['completed_lessons'] === 2
            && (int) $activity['streak_qualified'] === 0,
        'Review preserves Learning counters'
    );

    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM review_events WHERE review_state_id=?'
    );
    $query->execute([$id]);

    dbCheck(
        (int) $query->fetchColumn() === 1,
        'Only one event after retry'
    );

    // 12. Hai phien mo cung the: phien cu phai bi tu choi.
    $nextState = $cards['2:en_vi'];

    $a = $services['reviews']->start($u1, [
        'review_state_id' => $nextState,
        'request_key' => requestKey(),
    ], $now);

    $b = $services['reviews']->start($u1, [
        'review_state_id' => $nextState,
        'request_key' => requestKey(),
    ], $now);

    $services['reviews']->submit($u1, [
        'session_question_id' => $a['session_question_id'],
        'request_key' => requestKey(),
        'quality' => 4,
    ], $now);

    expectCode(function () use ($services, $u1, $b, $now): void {
        $services['reviews']->submit($u1, [
            'session_question_id' => $b['session_question_id'],
            'request_key' => requestKey(),
            'quality' => 4,
        ], $now);
    }, 'STALE_REVIEW');

    // 13. Kiem tra dang nhap va CSRF.
    $controller = new ReviewController($services);

    $response = $controller->dispatch(
        'GET', '/api/v1/reviews/bootstrap',
        [], [], 0, '', ''
    );

    dbCheck(
        $response['status'] === 401,
        'Authentication required'
    );

    $response = $controller->dispatch(
        'POST', '/api/v1/reviews/start',
        [], [], $u1, 'expected', 'wrong'
    );

    dbCheck(
        $response['status'] === 403,
        'CSRF required'
    );

    // 14. Tai khoan bi khoa khong duoc truy cap.
    $pdo->prepare(
        "UPDATE users SET status='locked' WHERE id=?"
    )->execute([$u2]);

    expectCode(function () use ($services, $u2, $now): void {
        $services['reviews']->due($u2, 20, null, null, $now);
    }, 'ACCOUNT_UNAVAILABLE');

    $pdo->prepare(
        "UPDATE users SET status='active' WHERE id=?"
    )->execute([$u2]);

    // 15. Chay hai worker dong thoi.
    if (function_exists('proc_open')) {
        $cid = $cards['1:vi_en'];

        $a = $services['reviews']->start($u1, [
            'review_state_id' => $cid,
            'request_key' => requestKey(),
        ], $now);

        $b = $services['reviews']->start($u1, [
            'review_state_id' => $cid,
            'request_key' => requestKey(),
        ], $now);

        $jobs = [];

        foreach ([$a, $b] as $opened) {
            $jobs[] = [
                'user_id' => $u1,
                'at' => $now->format(DATE_ATOM),
                'body' => [
                    'session_question_id' => $opened['session_question_id'],
                    'request_key' => requestKey(),
                    'quality' => 4,
                ],
            ];
        }

        $responses = parallelSubmit($jobs);
        $statuses = array_column($responses, 'status');
        sort($statuses);

        dbCheck(
            $statuses === [200, 409],
            'Two concurrent devices update once'
        );

        // Hai worker gui cung key phai nhan cung ket qua.
        $cid = $cards['2:vi_en'];

        $a = $services['reviews']->start($u1, [
            'review_state_id' => $cid,
            'request_key' => requestKey(),
        ], $now);

        $job = [
            'user_id' => $u1,
            'at' => $now->format(DATE_ATOM),
            'body' => [
                'session_question_id' => $a['session_question_id'],
                'request_key' => requestKey(),
                'quality' => 4,
            ],
        ];

        $responses = parallelSubmit([$job, $job]);

        dbCheck(
            $responses[0]['status'] === 200
                && $responses[1]['status'] === 200
                && json_encode($responses[0]['data'])
                    === json_encode($responses[1]['data']),
            'Concurrent same-key retries return same event'
        );
    } else {
        $skips++;
        echo 'SKIP parallel workers: proc_open unavailable.' . PHP_EOL;
    }

    // 16. Gay loi INSERT event de kiem tra rollback.
    $query = $pdo->prepare(
        'SELECT id FROM user_review_states
         WHERE user_id=? AND word_id=1'
    );
    $query->execute([$u2]);
    $faultState = (int) $query->fetchColumn();

    $opened = $services['reviews']->start($u2, [
        'review_state_id' => $faultState,
        'request_key' => requestKey(),
    ], $now);

    $original = fetchState($pdo, $faultState);
    $trigger = 'tu_sm2_fail_' . bin2hex(random_bytes(5));

    try {
        $pdo->exec(
            'CREATE TRIGGER `' . $trigger . '`
             BEFORE INSERT ON review_events
             FOR EACH ROW
             BEGIN
                 IF NEW.review_state_id=' . $faultState . " THEN
                     SIGNAL SQLSTATE '45000'
                     SET MESSAGE_TEXT='Tuan test fault';
                 END IF;
             END"
        );
    } catch (PDOException $error) {
        $trigger = null;
        $skips++;
        echo 'SKIP fault injection: cannot create test trigger.' . PHP_EOL;
    }

    if ($trigger !== null) {
        $faultBody = [
            'session_question_id' => $opened['session_question_id'],
            'request_key' => requestKey(),
            'quality' => 4,
        ];

        $threw = false;

        try {
            $services['reviews']->submit($u2, $faultBody, $now);
        } catch (PDOException $error) {
            $threw = true;
        }

        dbCheck(
            $threw && fetchState($pdo, $faultState) === $original,
            'DB failure rolls schedule back'
        );

        $query = $pdo->prepare(
            'SELECT COUNT(*) FROM answer_submissions WHERE request_key=?'
        );
        $query->execute([$faultBody['request_key']]);

        dbCheck(
            (int) $query->fetchColumn() === 0,
            'DB failure rolls submission back'
        );

        $pdo->exec('DROP TRIGGER `' . $trigger . '`');
        $trigger = null;

        $ok = $services['reviews']->submit($u2, $faultBody, $now);

        dbCheck(
            $ok['state']['repetitions'] === 1,
            'Same key succeeds after rollback'
        );
    }

    // 17. Real Learning -> Reviews integration with private immutable targets.
    $learning = new \App\Modules\Learning\Services\LearningService(
        new \App\Modules\Learning\Repositories\LearningRepository($pdo)
    );
    $topicPosition = 1 + (int) $pdo->query('SELECT COALESCE(MAX(position),0) FROM topics')->fetchColumn();
    $pdo->prepare('INSERT INTO topics (name,position) VALUES (?,?)')
        ->execute(['Tuan bridge integration ' . bin2hex(random_bytes(5)), $topicPosition]);
    $bridgeTopic = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO lessons (topic_id,name,position,question_count,pass_percent,xp_reward)
        VALUES (?,?,1,1,70,3)')->execute([$bridgeTopic, 'Tuan bridge integration']);
    $bridgeLesson = (int) $pdo->lastInsertId();
    $bridgeWords = [];
    foreach (['bridge-alpha', 'bridge-beta'] as $english) {
        $pdo->prepare('INSERT INTO words (topic_id,english,vietnamese,part_of_speech) VALUES (?,?,?,?)')
            ->execute([$bridgeTopic, $english, 'tu kiem thu', 'noun']);
        $bridgeWords[] = (int) $pdo->lastInsertId();
    }
    [$wordA, $wordB] = $bridgeWords;
    foreach ($bridgeWords as $wordId) {
        $pdo->prepare('INSERT INTO lesson_words (lesson_id,word_id) VALUES (?,?)')
            ->execute([$bridgeLesson, $wordId]);
    }
    $contentFor = static fn (int $wordId): string => json_encode([
        'prompt' => 'Bridge test', 'target_word_ids' => [$wordId],
        'options' => [['id' => 'a', 'text' => 'yes'], ['id' => 'b', 'text' => 'no']],
    ], JSON_THROW_ON_ERROR);
    $pdo->prepare('INSERT INTO exercises (lesson_id,word_id,type,content_json,answer_json)
        VALUES (?,?,\'choose_meaning\',?,?)')
        ->execute([$bridgeLesson, $wordA, $contentFor($wordA), '{"correct_option_ids":["a"]}']);
    $bridgeExercise = (int) $pdo->lastInsertId();

    $lesson = $learning->start($u1, $bridgeLesson);
    dbCheck(!str_contains(json_encode($lesson), 'target_word_ids'), 'Learning keeps target IDs private');
    $query = $pdo->prepare('SELECT snapshot_json FROM session_questions WHERE id=?');
    $query->execute([$lesson['question']['id']]);
    $frozen = json_decode($query->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    dbCheck($frozen['target_word_ids'] === [$wordA], 'Learning freezes target IDs in server snapshot');

    // An admin editing the exercise after start must not change the learned word.
    $pdo->prepare('UPDATE exercises SET word_id=?,content_json=? WHERE id=?')
        ->execute([$wordB, $contentFor($wordB), $bridgeExercise]);
    $lastAnswer = [
        'question_id' => $lesson['question']['id'],
        'request_key' => requestKey(),
        'answer' => ['option_id' => 'a'],
    ];
    $completed = $learning->answer($u1, $lesson['id'], $lastAnswer)['session'];
    dbCheck($completed['status'] === 'completed'
        && $completed['review_initialization']['initialized'] === 1,
        'Completed real lesson initializes review schedule');
    $query = $pdo->prepare('SELECT * FROM user_review_states WHERE user_id=? AND word_id IN (?,?) ORDER BY id');
    $query->execute([$u1, $wordA, $wordB]);
    $states = $query->fetchAll(PDO::FETCH_ASSOC);
    dbCheck(count($states) === 1 && (int) $states[0]['word_id'] === $wordA,
        'Bridge uses original targets after content edit');
    $firstState = $states[0];
    $due = new DateTimeImmutable($firstState['next_review_at'], new DateTimeZone('UTC'));
    $first = new DateTimeImmutable($firstState['first_learned_at'], new DateTimeZone('UTC'));
    dbCheck($due->getTimestamp() - $first->getTimestamp() === 86400,
        'First schedule is one day after server completion');
    $replayed = $learning->answer($u1, $lesson['id'], $lastAnswer);
    $query->execute([$u1, $wordA, $wordB]);
    $xpQuery = $pdo->prepare('SELECT COUNT(*) FROM xp_transactions WHERE session_id=?');
    $xpQuery->execute([$lesson['id']]);
    dbCheck($replayed['replayed'] && $query->fetchAll(PDO::FETCH_ASSOC) === $states
        && (int) $xpQuery->fetchColumn() === 1,
        'Lesson final-answer retry preserves schedule and rewards');

    // Relearning a known word must preserve every scheduling column.
    $pdo->prepare('UPDATE exercises SET word_id=?,content_json=? WHERE id=?')
        ->execute([$wordA, $contentFor($wordA), $bridgeExercise]);
    $againLesson = $learning->start($u1, $bridgeLesson);
    $learning->answer($u1, $againLesson['id'], [
        'question_id' => $againLesson['question']['id'], 'request_key' => requestKey(),
        'answer' => ['option_id' => 'a'],
    ]);
    dbCheck(fetchState($pdo, (int) $firstState['id']) === $firstState,
        'Real lesson replay does not reset existing review state');

    // The Learning endpoint must never open a shared-table SM-2 session.
    try {
        $learning->getSession($u1, $start['session_id']);
        throw new RuntimeException('Learning accepted an SM-2 session.');
    } catch (\App\Core\ApiException $error) {
        dbCheck($error->errorCode === 'SESSION_NOT_FOUND', 'Learning rejects SM-2 mode session');
    }

    $pdo->prepare('UPDATE users SET current_hp=1,next_hp_at=NULL WHERE id=?')->execute([$u2]);
    $failedLesson = $learning->start($u2, $bridgeLesson);
    $failedLesson = $learning->answer($u2, $failedLesson['id'], [
        'question_id' => $failedLesson['question']['id'], 'request_key' => requestKey(),
        'answer' => ['option_id' => 'b'],
    ])['session'];
    $countState = $pdo->prepare('SELECT COUNT(*) FROM user_review_states WHERE user_id=? AND word_id=?');
    $countState->execute([$u2, $wordA]);
    dbCheck($failedLesson['status'] === 'failed' && (int) $countState->fetchColumn() === 0,
        'Failed lesson creates no learned review state');

    // Reject cross-user calls even from the private integration hook.
    $pdo->beginTransaction();
    try {
        expectCode(static function () use ($pdo, $u2, $lesson): void {
            \App\Modules\Reviews\Services\LessonReviewHook::afterSessionCompleted($pdo, $u2, $lesson['id']);
        }, 'NOT_FOUND');
    } finally {
        $pdo->rollBack();
    }

    // Fault at schedule insertion must roll back the final answer, XP and completion.
    $pdo->prepare('UPDATE exercises SET word_id=?,content_json=? WHERE id=?')
        ->execute([$wordB, $contentFor($wordB), $bridgeExercise]);
    $rollbackLesson = $learning->start($u1, $bridgeLesson);
    $rollbackBody = [
        'question_id' => $rollbackLesson['question']['id'], 'request_key' => requestKey(),
        'answer' => ['option_id' => 'a'],
    ];
    $trigger = 'tu_bridge_fail_' . bin2hex(random_bytes(5));
    try {
        $pdo->exec('CREATE TRIGGER `' . $trigger . '` BEFORE INSERT ON user_review_states
            FOR EACH ROW BEGIN IF NEW.user_id=' . $u1 . ' AND NEW.word_id=' . $wordB . " THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Tuan bridge rollback test';
            END IF; END");
    } catch (PDOException $error) {
        $trigger = null;
        $skips++;
        echo 'SKIP bridge rollback fault injection: cannot create test trigger.' . PHP_EOL;
    }
    if ($trigger !== null) {
        $threw = false;
        try { $learning->answer($u1, $rollbackLesson['id'], $rollbackBody); }
        catch (PDOException $error) { $threw = true; }
        $query = $pdo->prepare('SELECT status FROM learning_sessions WHERE id=?');
        $query->execute([$rollbackLesson['id']]);
        $submissionCount = $pdo->prepare('SELECT COUNT(*) FROM answer_submissions WHERE request_key=?');
        $submissionCount->execute([$rollbackBody['request_key']]);
        $xpQuery->execute([$rollbackLesson['id']]);
        dbCheck($threw && $query->fetchColumn() === 'in_progress'
            && (int) $submissionCount->fetchColumn() === 0 && (int) $xpQuery->fetchColumn() === 0,
            'Bridge failure rolls back final answer, completion and XP together');
        $pdo->exec('DROP TRIGGER `' . $trigger . '`');
        $trigger = null;
    }
    $retried = $learning->answer($u1, $rollbackLesson['id'], $rollbackBody);
    dbCheck($retried['session']['status'] === 'completed'
        && $retried['session']['review_initialization']['initialized'] === 1,
        'Same lesson answer succeeds after bridge rollback');

    // Older in-progress snapshots remain finishable; never guess their missing target IDs.
    $legacy = $learning->start($u1, $bridgeLesson);
    $pdo->prepare("UPDATE session_questions SET snapshot_json=JSON_REMOVE(snapshot_json,'$.target_word_ids')
        WHERE session_id=?")->execute([$legacy['id']]);
    $legacy = $learning->answer($u1, $legacy['id'], [
        'question_id' => $legacy['question']['id'], 'request_key' => requestKey(),
        'answer' => ['option_id' => 'a'],
    ])['session'];
    dbCheck($legacy['status'] === 'completed'
        && $legacy['review_initialization']['legacy_questions'] === 1
        && $legacy['review_initialization']['initialized'] === 0,
        'Legacy snapshot completes with explicit missing-target report');

} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
} finally {
    // Chi don trigger va du lieu cua tai khoan thu vua tao.
    if ($trigger !== null) {
        $pdo->exec('DROP TRIGGER IF EXISTS `' . $trigger . '`');
    }

    foreach ($users as $userId) {
        $pdo->prepare(
            'DELETE e FROM review_events e
             JOIN user_review_states s ON s.id=e.review_state_id
             WHERE s.user_id=?'
        )->execute([$userId]);

        $pdo->prepare(
            'DELETE a FROM answer_submissions a
             JOIN session_questions q ON q.id=a.session_question_id
             JOIN learning_sessions s ON s.id=q.session_id
             WHERE s.user_id=?'
        )->execute([$userId]);

        $pdo->prepare(
            'DELETE q FROM session_questions q
             JOIN learning_sessions s ON s.id=q.session_id
             WHERE s.user_id=?'
        )->execute([$userId]);

        foreach ([
            'xp_transactions',
            'learning_sessions',
            'user_review_states',
            'notifications',
            'user_daily_activity',
            'user_quests',
        ] as $table) {
            $pdo->prepare(
                'DELETE FROM ' . $table . ' WHERE user_id=?'
            )->execute([$userId]);
        }

        $pdo->prepare(
            'DELETE FROM users WHERE id=?'
        )->execute([$userId]);
    }

    if ($bridgeTopic !== null) {
        $pdo->prepare('DELETE e FROM exercises e JOIN lessons l ON l.id=e.lesson_id WHERE l.topic_id=?')
            ->execute([$bridgeTopic]);
        $pdo->prepare('DELETE lw FROM lesson_words lw JOIN lessons l ON l.id=lw.lesson_id WHERE l.topic_id=?')
            ->execute([$bridgeTopic]);
        $pdo->prepare('DELETE FROM lessons WHERE topic_id=?')->execute([$bridgeTopic]);
        $pdo->prepare('DELETE FROM words WHERE topic_id=?')->execute([$bridgeTopic]);
        $pdo->prepare('DELETE FROM topics WHERE id=?')->execute([$bridgeTopic]);
    }
}

echo 'Integration checks=' . $checks
    . ' skips=' . $skips
    . ' result=' . ($failed ? 'FAIL' : 'PASS')
    . '. Separate test DB only.'
    . PHP_EOL;

exit($failed ? 1 : 0);
