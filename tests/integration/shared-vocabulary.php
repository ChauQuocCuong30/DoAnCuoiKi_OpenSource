<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\{ApiException, Database};
use App\Modules\Admin\Services\{ContentCsvImporter, VocabularySamples};
use App\Modules\Learning\Repositories\{LearningRepository, GuestClaimRepository};
use App\Modules\Learning\Services\{LearningService, GuestLearningService, VocabularyLearningSynchronizer};
use App\Modules\Vocabulary\Repositories\ContentRepository;

function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
function rows(PDO $db, string $sql, array $params = []): array {
    $q = $db->prepare($sql); $q->execute($params); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function answerFor(array $snapshot): array {
    $a = $snapshot['answer'];
    if (isset($a['correct_option_ids'])) { return ['option_id' => $a['correct_option_ids'][0]]; }
    if (isset($a['accepted_answers'])) { return ['text' => $a['accepted_answers'][0]]; }
    if (isset($a['accepted_sequences'])) { return ['token_ids' => $a['accepted_sequences'][0]]; }
    return ['pairs' => array_map(static fn($p) => ['left_id'=>$p['left_id'], 'right_id'=>$p['right_id']], $a['pairs'])];
}
App\Core\Session::start();
$db = Database::connection();
check(str_ends_with((string)$db->query('SELECT DATABASE()')->fetchColumn(), '_test'), 'Disposable test database only');
check((new VocabularyLearningSynchronizer($db))->enabled(), 'Migration 005 installed');
$position = (int)$db->query('SELECT COALESCE(MAX(position),0)+1 FROM topics')->fetchColumn();
$topicInsert = $db->prepare('INSERT INTO topics(name,position,is_active) VALUES(?,?,?)');
$topicInsert->execute(['Shared vocabulary integration', $position, 1]); $topic = (int)$db->lastInsertId();
$topicInsert->execute(['Empty vocabulary integration', $position+1, 1]); $empty = (int)$db->lastInsertId();
$topicInsert->execute(['Hidden vocabulary integration', $position+2, 0]); $hidden = (int)$db->lastInsertId();
$db->prepare('INSERT INTO lessons(topic_id,name,level,position,question_count) VALUES(?,?,1,1,10)')->execute([$topic, 'Existing lesson ID']);
$adopted = (int)$db->lastInsertId();
$db->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)')->execute(['Shared learner', 'shared-'.bin2hex(random_bytes(5)).'@example.test', password_hash('shared-test-password-123', PASSWORD_DEFAULT)]);
$user = (int)$db->lastInsertId();
$content = new ContentRepository($db); $import = new ContentCsvImporter($content);
$preview = $import->preview((new VocabularySamples())->csv('drinks', $topic), 'skip', $topic);
check($import->commit($preview['rows'], 'skip')['imported'] === 20, 'Real admin sample importer stores 20 words');
$repo = new LearningRepository($db); $service = new LearningService($repo);
$lessons = array_values(array_filter($repo->getActiveLessons(), static fn($l) => (int)$l['topic_id']===$topic));
check(count($lessons)===5 && (int)$lessons[0]['id']===$adopted, 'Five levels adopt the existing lesson ID');
check(count(array_filter($repo->getActiveLessons(), static fn($l) => (int)$l['topic_id']===$hidden))===0, 'Hidden topics never enter the catalogue');
$exerciseCount = (int)$db->query('SELECT COUNT(*) FROM exercises')->fetchColumn();
$repo->getActiveLessons();
check((int)$db->query('SELECT COUNT(*) FROM exercises')->fetchColumn()===$exerciseCount, 'Unchanged content does not regenerate exercise rows');
check(count($repo->getWordsByLessonId($adopted))===20 && count($repo->getWordsByTopicId($topic))===20, 'Flashcards and lessons use the same topic pool');
foreach ($lessons as $lesson) {
    $targets = [];
    foreach ($repo->exercises((int)$lesson['id']) as $exercise) {
        $snapshot = (new App\Modules\Learning\Services\ExerciseService())->snapshot($exercise, $repo->getWordsByLessonId((int)$lesson['id']));
        if ($snapshot===null) { throw new RuntimeException('Invalid generated '.$exercise['type']); }
        $targets = array_merge($targets, $snapshot['target_word_ids']);
    }
    check(count(array_unique($targets))===20, 'All imported words are eligible at level '.$lesson['level']);
}
try { $service->start($user, (int)$lessons[1]['id']); throw new RuntimeException('Expected lock'); }
catch (ApiException $e) { check($e->errorCode==='LESSON_LOCKED', 'Next level requires the preceding level'); }
$session = $service->start($user, $adopted); $id = $session['id'];
check(!str_contains(json_encode($session), 'correct_option_ids'), 'Generated answers stay private');
$snapshots = rows($db, 'SELECT snapshot_json FROM session_questions WHERE session_id=? ORDER BY id', [$id]);
$word = $repo->getWordsByTopicId($topic)[0];
$hiddenWord = (int)$word['id'];
$word['is_active'] = 0;
$content->saveWord($hiddenWord, $word, []);
$csv = "english,vietnamese,part_of_speech,example,example_translation\nnewshared,từ mới đồng bộ,noun,I learn newshared.,Tôi học từ mới.\n";
$draft = $import->preview($csv, 'skip', $topic);
check($import->commit($draft['rows'], 'skip')['imported']===1, 'Real CSV importer adds a new word');
$repo->getActiveLessons();
check(rows($db, 'SELECT snapshot_json FROM session_questions WHERE session_id=? ORDER BY id', [$id])===$snapshots, 'Import and hide preserve every existing session snapshot');
check($service->start($user, $adopted)['id']===$id, 'An open session resumes its original question set');
$activeIds = array_map('intval', array_column($repo->getWordsByLessonId($adopted), 'id'));
check(count($activeIds)===20 && !in_array($hiddenWord,$activeIds,true), 'Fresh bank excludes hidden word and includes the CSV word');
while ($session['status']==='in_progress') {
    $snapshot = json_decode(rows($db, 'SELECT snapshot_json FROM session_questions WHERE id=?', [$session['question']['id']])[0]['snapshot_json'],true);
    $session = $service->answer($user,$id,['question_id'=>$session['question']['id'],'request_key'=>bin2hex(random_bytes(16)), 'answer'=>answerFor($snapshot)])['session'];
}
check($session['status']==='completed' && $session['xp_earned']>0, 'Generated main and matching rounds complete through PR scoring');
check(count(rows($db,'SELECT id FROM xp_transactions WHERE session_id=?',[$id]))===1, 'One XP reward per completed session');
check(count(rows($db,'SELECT id FROM user_review_states WHERE user_id=?',[$user]))>0, 'Generated lesson initializes the existing SM-2 review queue');
check($repo->unlocked($user, $lessons[1]), 'Completing the generated level unlocks the next level');
$next = $service->start($user, $adopted);
foreach (rows($db,'SELECT snapshot_json FROM session_questions WHERE session_id=?',[$next['id']]) as $row) {
    check(!in_array($hiddenWord,json_decode($row['snapshot_json'],true)['target_word_ids'],true), 'New attempt uses current vocabulary');
}
$emptyLesson = array_values(array_filter($repo->getActiveLessons(), static fn($l)=>(int)$l['topic_id']===$empty))[0];
try { $service->start($user,(int)$emptyLesson['id']); throw new RuntimeException('Empty topic was startable'); }
catch (ApiException $e) { check($e->errorCode==='LESSON_NOT_READY', 'Empty topics are visible but cannot start an invalid quiz'); }
$db->prepare('UPDATE topics SET name=? WHERE id=?')->execute(['Shared vocabulary renamed', $topic]);
check(str_starts_with($repo->lesson($adopted)['name'],'Shared vocabulary renamed'), 'Topic rename updates the lesson name without changing its ID');

// Guest snapshots also retain real exercise IDs when a new bank version replaces them.
$guest = new GuestLearningService($repo, new GuestClaimRepository($db));
$trial = $guest->start(); $first = $guest->firstLesson();
$guestQuestions = $_SESSION['learning_guest_v1']['questions'];
$db->prepare('UPDATE topics SET description=? WHERE id=?')->execute(['Guest fixture', $first['topic_id']]);
$db->prepare('UPDATE words SET example=? WHERE id=?')->execute(['A refreshed example.', $repo->getWordsByLessonId((int)$first['id'])[0]['id']]);
$repo->getActiveLessons();
check($_SESSION['learning_guest_v1']['questions']===$guestQuestions, 'Guest trial questions survive a bank refresh');
while ($trial['status']==='in_progress') {
    $q = $_SESSION['learning_guest_v1']['questions'][$trial['question']['id']-1];
    $trial = $guest->answer(['session_token'=>$trial['id'], 'question_id'=>$trial['question']['id'], 'request_key'=>bin2hex(random_bytes(16)), 'answer'=>answerFor($q['snapshot'])])['session'];
}
check($trial['status']==='completed', 'Generated guest quiz completes');
$claimed = $guest->claim($user);
check(count(rows($db,'SELECT token FROM learning_guest_claims WHERE user_id=?',[$user]))===1, 'Guest result claims real retired exercise IDs without foreign key errors');
echo "Shared vocabulary integration complete.\n";
