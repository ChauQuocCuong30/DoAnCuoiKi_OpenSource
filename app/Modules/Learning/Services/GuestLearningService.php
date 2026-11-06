<?php
declare(strict_types=1);

namespace App\Modules\Learning\Services;

use App\Core\{ApiException, Session};
use App\Modules\Learning\Repositories\LearningRepository;
use App\Modules\Learning\Repositories\GuestClaimRepository;
use App\Shared\HeartRules;

final class GuestLearningService
{
    private const KEY = 'learning_guest_v1';

    public function __construct(
        private LearningRepository $repo,
        private GuestClaimRepository $claims,
        private ExerciseService $grader = new ExerciseService()
    ) {}

    public function firstLesson(): ?array
    {
        $lessons = $this->repo->getActiveLessons();
        if (isset($lessons[0]['word_count'])) {
            foreach ($lessons as $lesson) {
                if ((int) $lesson['word_count'] >= 2) { return $lesson; }
            }
            return null;
        }
        $topic = $this->repo->getActiveTopics()[0] ?? null;

        if ($topic === null) {
            return null;
        }

        foreach ($lessons as $lesson) {
            if ((int) $lesson['topic_id'] === (int) $topic['id']) {
                return $lesson;
            }
        }

        return null;
    }

    public function catalogue(): array
    {
        $groups = [];

        foreach ($this->repo->getActiveLessons() as $lesson) {
            $groups[$lesson['topic_id']][] = $lesson;
        }

        return [
            'topics' => $this->repo->getActiveTopics(),
            'groups' => $groups,
            'first' => $this->firstLesson(),
        ];
    }

    public function lessonPage(int $id): array
    {
        $first = $this->firstLesson();

        if ($first === null || (int) $first['id'] !== $id) {
            throw new ApiException(
                'GUEST_LIMIT',
                'Khách được học thử bài mở đầu khả dụng. Đăng nhập để học các bài còn lại.',
                403
            );
        }

        [$main] = $this->bank($first);
        $words = $this->repo->getWordsByLessonId($id);

        foreach ($words as &$word) {
            $word['image'] = ExerciseService::media(
                $word['image'] ?? null
            );

            $word['audio'] = ExerciseService::media(
                $word['audio'] ?? null
            );
        }

        unset($word);

        return [
            'lesson' => $first,
            'words' => $words,
            'ready' => count($main) >= (int) $first['question_count'],
        ];
    }

    private function bank(array $lesson): array
    {
        $words = $this->repo->getWordsByLessonId(
            (int) $lesson['id']
        );

        $main = [];
        $matching = [];

        foreach ($this->repo->exercises((int) $lesson['id']) as $row) {
            $snapshot = $this->grader->snapshot($row, $words);

            if ($snapshot === null) {
                continue;
            }

            $item = [
                'exercise_id' => (int) $row['id'],
                'snapshot' => $snapshot,
            ];

            if ($row['type'] === 'match_pairs') {
                $matching[] = $item;
            } else {
                $main[] = $item;
            }
        }

        return [$main, $matching];
    }

    private function stored(): ?array
    {
        Session::start();

        $state = $_SESSION[self::KEY] ?? null;

        if (!is_array($state)) {
            return null;
        }

        if (time() - (int) ($state['updated_at'] ?? 0) > 7200) {
            unset($_SESSION[self::KEY]);
            return null;
        }

        $first = $this->firstLesson();
        $storedLesson = $state['lesson'] ?? null;

        if (
            $first === null
            || !is_array($storedLesson)
            || (int) ($storedLesson['id'] ?? 0) !== (int) $first['id']
        ) {
            unset($_SESSION[self::KEY]);
            return null;
        }

        return $state;
    }

    public function pending(): bool
    {
        $state = $this->stored();

        return $state !== null
            && $state['status'] === 'completed'
            && empty($state['claimed_user_id']);
    }

    private function save(array $state): void
    {
        $state['updated_at'] = time();
        $_SESSION[self::KEY] = $state;
    }

    public function state(): ?array
    {
        $state = $this->stored();

        return $state === null
            ? null
            : $this->publicState($state);
    }

    public function start(bool $restart = false): array
    {
        $old = $this->stored();

        if (
            $old !== null
            && ($old['status'] !== 'failed' || !$restart)
        ) {
            return $this->publicState($old);
        }

        $lesson = $this->firstLesson();

        if ($lesson === null) {
            throw new ApiException(
                'LESSON_NOT_FOUND',
                'Chưa có bài học.',
                404
            );
        }

        [$main, $matching] = $this->bank($lesson);
        $total = (int) $lesson['question_count'];

        if ($total < 1 || count($main) < $total) {
            throw new ApiException(
                'LESSON_NOT_READY',
                'Ải đầu chưa đủ câu hỏi hoặc tài nguyên.',
                409
            );
        }

        shuffle($main);
        shuffle($matching);

        $questions = [];

        $add = static function (
            array $item,
            string $phase,
            int $position
        ) use (&$questions): void {
            foreach (['options', 'tokens', 'right'] as $key) {
                if (isset($item['snapshot']['content'][$key])) {
                    shuffle($item['snapshot']['content'][$key]);
                }
            }

            $questions[] = $item + [
                'id' => count($questions) + 1,
                'phase' => $phase,
                'position' => $position,
                'resolved' => false,
                'attempts' => [],
                'source' => null,
            ];
        };

        foreach (QuestionSelection::main($main, $total) as $i => $item) {
            $add($item, 'main', $i + 1);
        }

        if ($matching !== []) {
            $add($matching[0], 'matching', 1);
        }

        $state = [
            'id' => bin2hex(random_bytes(16)),
            'lesson' => $lesson,
            'status' => 'in_progress',
            'total_questions' => $total,
            'correct_count' => 0,
            'hp' => HeartRules::MAX_HP,
            'questions' => $questions,
            'submissions' => [],
            'reviews_added' => false,
            'claimed_user_id' => null,
            'updated_at' => time(),
            'rules' => [
                'question_bank_version' => 'mixed-cefr-v4',
                'pass_percent' => (float) $lesson['pass_percent'],
                'xp_reward' => (int) $lesson['xp_reward'],
                'max_hp' => HeartRules::MAX_HP,
                'mistake_review' => true,
                'matching_required' => $matching !== [],
            ],
        ];

        $this->save($state);

        return $this->publicState($state);
    }

    private function current(array $state): ?int
    {
        foreach (['main', 'mistake_review', 'matching'] as $phase) {
            foreach ($state['questions'] as $i => $question) {
                if (
                    $question['phase'] === $phase
                    && !$question['resolved']
                ) {
                    return $i;
                }
            }
        }

        return null;
    }

    public function answer(array $body): array
    {
        $state = $this->stored();

        if ($state === null) {
            throw new ApiException(
                'GUEST_EXPIRED',
                'Phiên học thử đã hết hạn. Hãy học lại ải đầu.',
                409
            );
        }

        $token = $body['session_token'] ?? '';
        $key = $body['request_key'] ?? '';
        $id = $body['question_id'] ?? null;

        if (
            !is_string($token)
            || !hash_equals($state['id'], $token)
        ) {
            throw new ApiException(
                'GUEST_SESSION_CHANGED',
                'Phiên học đã thay đổi. Hãy tải lại trang.',
                409
            );
        }

        if (
            !is_string($key)
            || !preg_match('/^[A-Za-z0-9:_-]{16,100}$/D', $key)
            || !is_int($id)
            || $id < 1
            || !isset($state['questions'][$id - 1])
        ) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Câu trả lời không hợp lệ.',
                422
            );
        }

        $index = $id - 1;
        $snapshot = $state['questions'][$index]['snapshot'];

        $answer = $this->grader->normalize(
            $snapshot,
            $body['answer'] ?? null
        );

        $fingerprint = hash(
            'sha256',
            json_encode([$id, $answer], JSON_THROW_ON_ERROR)
        );

        if (isset($state['submissions'][$key])) {
            $old = $state['submissions'][$key];

            if ($old['fingerprint'] !== $fingerprint) {
                throw new ApiException(
                    'REQUEST_KEY_CONFLICT',
                    'Mã gửi lại đã dùng cho câu trả lời khác.',
                    409
                );
            }

            return [
                'is_correct' => $old['correct'],
                'replayed' => true,
                'feedback' => $this->grader->feedback($snapshot,$answer),
                'session' => $this->publicState($state),
            ];
        }

        if (
            $state['status'] !== 'in_progress'
            || $this->current($state) !== $index
        ) {
            throw new ApiException(
                'QUESTION_OUT_OF_ORDER',
                'Câu hỏi đã trả lời hoặc chưa đến lượt.',
                409
            );
        }

        if (count($state['submissions']) >= 500) {
            throw new ApiException(
                'GUEST_ATTEMPT_LIMIT',
                'Phiên này đã có quá nhiều lần trả lời.',
                429
            );
        }

        $correct = $this->grader->grade($snapshot, $answer);
        $phase = $state['questions'][$index]['phase'];

        $state['questions'][$index]['attempts'][] = $answer;

        $state['submissions'][$key] = [
            'fingerprint' => $fingerprint,
            'correct' => $correct,
        ];

        if ($correct || $phase === 'main') {
            $state['questions'][$index]['resolved'] = true;
        }

        if ($phase === 'main' && $correct) {
            $state['correct_count']++;
        }

        if (!$correct && $phase !== 'mistake_review') {
            $state['hp']--;
        }

        $mainDone = !array_filter(
            $state['questions'],
            static fn($q) => $q['phase'] === 'main'
                && !$q['resolved']
        );

        if (
            $state['hp'] <= 0
            || (
                $mainDone
                && !LearningService::passed(
                    $state,
                    $state['rules']
                )
            )
        ) {
            $state['status'] = 'failed';
        } elseif ($mainDone) {
            if (!$state['reviews_added']) {
                $position = 0;

                foreach ($state['questions'] as $q) {
                    if ($q['phase'] !== 'main') {
                        continue;
                    }

                    if ($this->grader->grade(
                        $q['snapshot'],
                        $q['attempts'][0]
                    )) {
                        continue;
                    }

                    $state['questions'][] = [
                        'id' => count($state['questions']) + 1,
                        'exercise_id' => $q['exercise_id'],
                        'snapshot' => $q['snapshot'],
                        'phase' => 'mistake_review',
                        'position' => ++$position,
                        'source' => $q['id'],
                        'resolved' => false,
                        'attempts' => [],
                    ];
                }

                $state['reviews_added'] = true;
            }

            if ($this->current($state) === null) {
                $state['status'] = 'completed';
            }
        }

        $this->save($state);

        return [
            'is_correct' => $correct,
            'replayed' => false,
            'feedback' => $this->grader->feedback($snapshot,$answer),
            'session' => $this->publicState($state),
        ];
    }

    private function publicState(array $state): array
    {
        $index = $state['status'] === 'in_progress'
            ? $this->current($state)
            : null;

        $question = $index === null
            ? null
            : $state['questions'][$index];

        return [
            'id' => $state['id'],
            'lesson_id' => (int) $state['lesson']['id'],
            'status' => $state['status'],
            'total_questions' => $state['total_questions'],
            'correct_count' => $state['correct_count'],
            'answered_main' => count(array_filter(
                $state['questions'],
                static fn($q) => $q['phase'] === 'main'
                    && $q['resolved']
            )),
            'current_hp' => $state['hp'],
            'max_hp' => HeartRules::MAX_HP,
            'question' => $question === null ? null : [
                'id' => $question['id'],
                'phase' => $question['phase'],
                'type' => $question['snapshot']['type'],
                'content' => $this->grader->publicContent($question['snapshot'],
                    in_array($question['snapshot']['type'], ['listen_type', 'listen_select'], true)
                        ? $this->repo->getWordsByLessonId((int) $state['lesson']['id']) : []),
            ],
        ];
    }

    public function review(int $lessonId): array
    {
        $state = $this->stored();

        if (
            $state === null
            || $state['status'] !== 'completed'
            || (int) $state['lesson']['id'] !== $lessonId
        ) {
            throw new ApiException(
                'RESULT_NOT_FOUND',
                'Ải này chưa có kết quả học thử hoàn thành để xem lại.',
                404
            );
        }

        $questions = [];

        foreach ($state['questions'] as $question) {
            if ($question['phase'] !== 'main') {
                continue;
            }

            $first = $question['attempts'][0] ?? null;

            $questions[] = [
                'position' => $question['position'],
                'exercise_id' => $question['exercise_id'],
                'snapshot_json' => json_encode(
                    $question['snapshot'],
                    JSON_THROW_ON_ERROR
                ),
                'first_answer_json' => $first === null
                    ? null
                    : json_encode($first, JSON_THROW_ON_ERROR),
                'first_is_correct' => $first === null
                    ? null
                    : $this->grader->grade(
                        $question['snapshot'],
                        $first
                    ),
            ];
        }

        return [
            'lesson' => $state['lesson'],
            'isGuest' => true,
            'questions' => $questions,
            'result' => [
                'total' => (int) $state['total_questions'],
                'correct' => (int) $state['correct_count'],
                'wrong' => max(
                    0,
                    (int) $state['total_questions']
                        - (int) $state['correct_count']
                ),
                'xp' => 0,
                'finished_at' => null,
            ],
        ];
    }

    public function claim(int $userId): array
    {
        $state = $this->stored();

        if ($state === null || $state['status'] !== 'completed') {
            return [
                'claimed' => false,
                'next_lesson_id' => null,
            ];
        }

        if (
            !empty($state['claimed_user_id'])
            && $state['claimed_user_id'] !== $userId
        ) {
            throw new ApiException(
                'FORBIDDEN',
                'Kết quả học thử đã được gắn vào tài khoản khác.',
                403
            );
        }

        $result = $this->repo->transaction(
            function () use ($state, $userId): array {
                $user = $this->repo->lockUser($userId);

                if ($user['status'] !== 'active') {
                    throw new ApiException(
                        'FORBIDDEN',
                        'Tài khoản không hoạt động.',
                        403
                    );
                }

                $lesson = $this->repo->lesson(
                    (int) $state['lesson']['id']
                );

                if ($lesson === null) {
                    throw new ApiException(
                        'GUEST_CONTENT_CHANGED',
                        'Bài học hiện không còn được mở.',
                        409
                    );
                }

                $this->claims->reserve(
                    $state['id'],
                    $userId,
                    (int) $lesson['id']
                );

                $already = in_array(
                    (int) $lesson['id'],
                    $this->repo->completedLessons($userId),
                    true
                );

                if (!$already) {
                    $rules = $state['rules'] + [
                        'guest_source' => $state['id'],
                    ];

                    $sessionId = $this->repo->createSession(
                        $userId,
                        (int) $lesson['id'],
                        $state['total_questions'],
                        $rules
                    );

                    $map = [];

                    foreach (
                        ['main', 'mistake_review', 'matching'] as $phase
                    ) {
                        foreach ($state['questions'] as $q) {
                            if ($q['phase'] !== $phase) {
                                continue;
                            }

                            $source = $q['source'] === null
                                ? null
                                : ($map[$q['source']] ?? null);

                            $this->repo->addQuestion(
                                $sessionId,
                                $q['exercise_id'],
                                $phase,
                                $q['position'],
                                json_encode(
                                    $q['snapshot'],
                                    JSON_THROW_ON_ERROR
                                ),
                                $source
                            );
                        }

                        foreach (
                            $this->repo->questions($sessionId) as $row
                        ) {
                            foreach ($state['questions'] as $q) {
                                if (
                                    $row['phase'] === $q['phase']
                                    && (int) $row['position']
                                        === $q['position']
                                ) {
                                    $map[$q['id']] = (int) $row['id'];
                                }
                            }
                        }
                    }

                    foreach ($state['questions'] as $q) {
                        if (!$q['resolved'] || $q['attempts'] === []) {
                            throw new ApiException(
                                'GUEST_INCOMPLETE',
                                'Kết quả học thử chưa hoàn tất.',
                                409
                            );
                        }

                        foreach ($q['attempts'] as $i => $answer) {
                            $correct = $this->grader->grade(
                                $q['snapshot'],
                                $answer
                            );

                            $this->repo->recordAnswer(
                                $map[$q['id']],
                                'guest:' . $state['id']
                                    . ':' . $q['id'] . ':' . $i,
                                $answer,
                                $correct
                            );
                        }

                        $this->repo->resolveQuestion(
                            $map[$q['id']]
                        );

                        if (
                            $q['phase'] === 'main'
                            && $this->grader->grade(
                                $q['snapshot'],
                                $q['attempts'][0]
                            )
                        ) {
                            $this->repo->addCorrect($sessionId);
                        }
                    }

                    $this->repo->finish(
                        $sessionId,
                        'completed'
                    );

                    $this->claims->attach(
                        $state['id'],
                        $userId,
                        $sessionId
                    );

                    $this->repo->reward(
                        $user,
                        ['id' => $sessionId],
                        (int) $rules['xp_reward']
                    );

                    $this->repo->initializeReviews($userId, $sessionId);
                }

                $next = null;

                foreach (
                    $this->repo->getActiveLessons() as $candidate
                ) {
                    if (
                        (int) $candidate['topic_id']
                            === (int) $lesson['topic_id']
                        && (int) $candidate['position']
                            > (int) $lesson['position']
                        && $this->repo->unlocked(
                            $userId,
                            $candidate
                        )
                    ) {
                        $next = (int) $candidate['id'];
                        break;
                    }
                }

                return [
                    'claimed' => true,
                    'already_completed' => $already,
                    'next_lesson_id' => $next,
                ];
            }
        );

        $state['claimed_user_id'] = $userId;
        $this->save($state);

        return $result;
    }
}
