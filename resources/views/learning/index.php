<?php
declare(strict_types=1);

use App\Core\Session;

$isGuest = (bool) ($isGuest ?? (Session::userId() === null));
$guestCompleted = $isGuest && (bool) ($guestCompleted ?? false);
$firstLessonId = (int) ($firstLessonId ?? 0);

$screen = ($screen ?? 'path') === 'progress'
    ? 'progress'
    : 'path';

$navActive = $screen === 'progress'
    ? 'progress'
    : 'path';

$pageTitle = (
    $screen === 'progress'
        ? 'Tiến độ'
        : 'Lộ trình học'
) . ' — Doulingo';

$stages = [];
$finished = [];
$next = null;
$total = 0;
$done = 0;

$covers = [
    'Đồ uống và giao tiếp' => 'milk',
    'Giao thông' => 'bus',
    'Quốc gia' => 'vietnam',
    'Động vật' => 'cat',
];

$tones = ['mint', 'blue', 'lilac', 'peach'];

foreach (($topics ?? []) as $topic) {
    if (
        isset($topic['is_active'])
        && !(bool) $topic['is_active']
    ) {
        continue;
    }

    $lessons = [];
    $stageDone = 0;

    foreach (($lessonsByTopic[$topic['id']] ?? []) as $lesson) {
        if (
            isset($lesson['is_active'])
            && !(bool) $lesson['is_active']
        ) {
            continue;
        }

        $id = (int) $lesson['id'];

        $lesson['ui_done'] = $isGuest
            ? ($guestCompleted && $id === $firstLessonId)
            : !empty($lesson['completed']);

        $lesson['ui_open'] = !empty($lesson['ready'])
            && (
                $isGuest
                    ? $id === $firstLessonId
                    : !empty($lesson['unlocked'])
            );

        $total++;

        if ($lesson['ui_done']) {
            $done++;
            $stageDone++;

            $finished[] = [
                'topic' => $topic['name'],
                'lesson' => $lesson,
            ];
        } elseif ($lesson['ui_open'] && $next === null) {
            $next = [
                'topic' => $topic['name'],
                'lesson' => $lesson,
            ];
        }

        $lessons[] = $lesson;
    }

    $count = count($lessons);
    $slug = $covers[$topic['name']] ?? null;

    $stages[] = [
        'topic' => $topic,
        'lessons' => $lessons,
        'done' => $stageDone,
        'total' => $count,

        'percent' => $count > 0
            ? (int) round(100 * $stageDone / $count)
            : 0,

        'tone' => $tones[count($stages) % 4],

        'anchor' => 'stage-' . (int) (
            $topic['position'] ?? count($stages) + 1
        ),

        'cover' => $slug === null
            ? '/assets/images/learning-ui/notebook.png'
            : '/assets/media/images/' . $slug . '.png',
    ];
}

$percent = $total > 0
    ? (int) round(100 * $done / $total)
    : 0;

$xp = $isGuest
    ? 0
    : max(0, (int) ($stats['xp'] ?? 0));

$streak = $isGuest
    ? 0
    : max(0, (int) ($stats['streak'] ?? 0));

$allDone = $total > 0 && $done === $total;

require __DIR__ . '/dashboard-shell.php';
?>

<?php if (!empty($loadError)): ?>

    <section class="ld-empty">
        <span class="ld-empty-icon">
            <?= $icon('book') ?>
        </span>

        <h1>Chưa mở được hành trình</h1>

        <p><?= $e($loadError) ?></p>

        <?php if (!empty($errorReference)): ?>
            <small><?= $e($errorReference) ?></small>
        <?php endif; ?>

        <a
            class="ld-btn ld-btn-green"
            href="<?= $e($b) ?>/learning"
        >
            Thử lại
            <?= $icon('arrow') ?>
        </a>
    </section>

<?php else: ?>

    <div class="ld-page-heading">
        <div>
            <span class="ld-eyebrow">
                <?= $isGuest
                    ? 'KHÔNG GIAN HỌC THỬ'
                    : 'TIẾN BỘ TỪNG NGÀY' ?>
            </span>

            <h1>
                <?= $screen === 'progress'
                    ? 'Nhìn lại hành trình của bạn'
                    : 'Lộ trình học tập' ?>
            </h1>
        </div>

        <span class="ld-heading-badge">
            <?= $icon('check') ?>

            <?= $done ?>/<?= $total ?>
            ải <?= $isGuest ? 'học thử' : 'hoàn thành' ?>
        </span>
    </div>

    <?php if ($screen === 'path' && $stages): ?>
        <section class="ld-hero ld-path-hero">
            <div class="ld-hero-copy">
                <span class="ld-eyebrow ld-eyebrow-light">
                    <i></i>

                    <?= $next !== null
                        ? 'BÀI HỌC TIẾP THEO'
                        : ($allDone
                            ? 'MỘT CỘT MỐC MỚI'
                            : 'HÀNH TRÌNH CỦA BẠN') ?>
                </span>

                <h2>
                    <?= $e(
                        $next['topic']
                        ?? (
                            $allDone
                                ? 'Bạn đã hoàn thành lộ trình!'
                                : (
                                    $guestCompleted
                                        ? 'Ải đầu đã xong. Giỏi lắm!'
                                        : 'Sẵn sàng cho bước tiếp theo?'
                                )
                        )
                    ) ?>
                </h2>

                <p>
                    <?= $e(
                        $next['lesson']['name']
                        ?? (
                            $guestCompleted
                                ? 'Lưu kết quả để tiếp tục sang ải mới.'
                                : (
                                    $allDone
                                        ? 'Ôn lại những từ đã học và xem thành tích của bạn.'
                                        : 'Các bài học sẽ mở khi có đủ nội dung.'
                                )
                        )
                    ) ?>
                </p>

                <div class="ld-hero-actions">
                    <?php if ($next !== null): ?>
                        <a
                            class="ld-btn ld-btn-yellow"
                            href="<?= $e($b) ?>/learning/lesson/<?= (int) $next['lesson']['id'] ?>?focus=practice"
                        >
                            Tiếp tục học
                            <?= $icon('arrow') ?>
                        </a>

                    <?php elseif ($guestCompleted): ?>
                        <a
                            class="ld-btn ld-btn-yellow"
                            href="<?= $e($b) ?>/login?next=guest"
                        >
                            Lưu tiến độ
                            <?= $icon('arrow') ?>
                        </a>

                    <?php else: ?>
                        <a
                            class="ld-btn ld-btn-yellow"
                            href="<?= $e($b) ?>/learning/progress"
                        >
                            Xem tiến độ
                            <?= $icon('arrow') ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div
                class="ld-hero-visual ld-path-visual"
                aria-hidden="true"
            >
                <div class="ld-visual-scene" data-tilt>
                    <div class="ld-orbit ld-orbit-one"></div>

                    <div class="ld-book-wrap">
                        <img
                            class="ld-floating-book"
                            src="<?= $e($b) ?>/assets/images/learning-ui/<?= $allDone || $guestCompleted ? 'trophy' : 'notebook' ?>.png"
                            alt=""
                            width="170"
                            height="170"
                        >
                    </div>

                    <?php if ($next !== null): ?>
                        <div class="ld-reward-chip">
                            <span><?= $icon('bolt') ?></span>

                            <div>
                                <strong>
                                    <?= (int) $next['lesson']['xp_reward'] ?> XP
                                </strong>

                                <small>khi hoàn thành</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <span class="ld-spark ld-spark-one">✦</span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="ld-stats">
        <div class="ld-stat">
            <span class="ld-stat-icon gold">
                <?= $icon('bolt') ?>
            </span>

            <div>
                <strong>
                    <?= number_format($xp) ?>
                    <small>XP</small>
                </strong>

                <span>
                    <?= $isGuest
                        ? 'Đăng nhập để lưu XP'
                        : 'Tổng XP đã tích lũy' ?>
                </span>
            </div>
        </div>

        <div class="ld-stat">
            <span class="ld-stat-icon peach">
                <?= $icon('flame') ?>
            </span>

            <div>
                <strong>
                    <?= $streak ?>
                    <small>ngày</small>
                </strong>

                <span>Chuỗi ngày học</span>
            </div>
        </div>

        <div class="ld-stat">
            <span class="ld-stat-icon mint">
                <?= $icon('check') ?>
            </span>

            <div>
                <strong>
                    <?= $done ?>
                    <small>/ <?= $total ?> ải</small>
                </strong>

                <span>
                    <?= $isGuest
                        ? 'Tiến độ học thử'
                        : 'Đã hoàn thành' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="ld-learning-layout">
        <div class="ld-stage-column">

            <?php if ($screen === 'progress'): ?>

                <div class="ld-section-head">
                    <h2>Tiến độ từng chặng</h2>

                    <span class="ld-section-note">
                        <?= count($stages) ?> chặng học
                    </span>
                </div>

                <div class="ld-progress-topics">
                    <?php foreach ($stages as $stage): ?>
                        <a
                            class="ld-topic-progress ld-tone-<?= $e($stage['tone']) ?>"
                            href="<?= $e($b) ?>/learning#<?= $e($stage['anchor']) ?>"
                        >
                            <img
                                src="<?= $e($b . $stage['cover']) ?>"
                                alt=""
                                width="62"
                                height="62"
                            >

                            <div>
                                <h3>
                                    <?= $e($stage['topic']['name']) ?>
                                </h3>

                                <small>
                                    <?= $stage['done'] ?>/<?= $stage['total'] ?>
                                    ải hoàn thành
                                </small>

                                <div class="ld-meter">
                                    <span style="width:<?= $stage['percent'] ?>%"></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="ld-section-head ld-section-spaced">
                    <h2>Ải đã hoàn thành</h2>

                    <span class="ld-section-note">
                        <?= count($finished) ?> ải
                    </span>
                </div>

                <div class="ld-finished-list">
                    <?php foreach ($finished as $entry): ?>
                        <a
                            class="ld-finished-row"
                            href="<?= $e($b) ?>/learning/lesson/<?= (int) $entry['lesson']['id'] ?>?focus=result"
                        >
                            <span class="ld-level-icon is-done">
                                <?= $icon('check') ?>
                            </span>

                            <div>
                                <small>
                                    <?= $e($entry['topic']) ?>
                                </small>

                                <h3>
                                    <?= $e($entry['lesson']['name']) ?>
                                </h3>
                            </div>

                            <span class="ld-finished-link">
                                Xem kết quả
                                <?= $icon('arrow') ?>
                            </span>
                        </a>
                    <?php endforeach; ?>

                    <?php if (!$finished): ?>
                        <div class="ld-empty ld-empty-small">
                            <?= $icon('trophy') ?>

                            <h3>Thành tích đầu tiên đang chờ bạn</h3>

                            <p>
                                Hoàn thành một ải để thấy kết quả tại đây.
                            </p>

                            <a
                                class="ld-btn ld-btn-green"
                                href="<?= $e($b) ?>/learning"
                            >
                                Bắt đầu học
                                <?= $icon('arrow') ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <div class="ld-section-head">
                    <h2>Các chặng học</h2>

                    <span class="ld-section-note">
                        <?= count($stages) ?> chặng
                        · <?= $total ?> ải
                    </span>
                </div>

                <?php if ($stages): ?>
                    <nav
                        class="ld-topic-tabs"
                        aria-label="Đi đến chặng học"
                    >
                        <?php foreach ($stages as $i => $stage): ?>
                            <a href="#<?= $e($stage['anchor']) ?>">
                                <span>
                                    <?= str_pad(
                                        (string) ($i + 1),
                                        2,
                                        '0',
                                        STR_PAD_LEFT
                                    ) ?>
                                </span>

                                <?= $e($stage['topic']['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>

                <?php foreach ($stages as $i => $stage): ?>
                    <section
                        class="ld-stage"
                        id="<?= $e($stage['anchor']) ?>"
                        aria-labelledby="title-<?= $e($stage['anchor']) ?>"
                    >
                        <header class="ld-stage-head">
                            <div class="ld-stage-art ld-tone-<?= $e($stage['tone']) ?>">
                                <img
                                    src="<?= $e($b . $stage['cover']) ?>"
                                    alt=""
                                    width="64"
                                    height="64"
                                    loading="lazy"
                                >
                            </div>

                            <div class="ld-stage-title">
                                <span>
                                    CHẶNG <?= str_pad(
                                        (string) ($i + 1),
                                        2,
                                        '0',
                                        STR_PAD_LEFT
                                    ) ?>
                                </span>

                                <h3 id="title-<?= $e($stage['anchor']) ?>">
                                    <?= $e($stage['topic']['name']) ?>
                                </h3>
                            </div>

                            <span class="ld-stage-count <?= $stage['done'] === $stage['total'] && $stage['total'] > 0 ? 'is-full' : '' ?>">
                                <?= $stage['done'] ?>/<?= $stage['total'] ?>
                            </span>
                        </header>

                        <div class="ld-stage-meter ld-meter">
                            <span style="width:<?= $stage['percent'] ?>%"></span>
                        </div>

                        <ol class="ld-lessons">
                            <?php foreach ($stage['lessons'] as $lesson):
                                $current = $next !== null
                                    && (int) $next['lesson']['id']
                                        === (int) $lesson['id'];

                                $glyph = $lesson['ui_done']
                                    ? 'check'
                                    : (
                                        !$lesson['ui_open']
                                            ? 'lock'
                                            : (
                                                [
                                                    1 => 'book',
                                                    2 => 'image',
                                                    3 => 'headphones',
                                                    4 => 'target',
                                                ][(int) $lesson['position']]
                                                ?? 'book'
                                            )
                                    );
                            ?>
                                <li class="ld-lesson <?= $lesson['ui_done'] ? 'is-done' : ($current ? 'is-current' : (!$lesson['ui_open'] ? 'is-locked' : '')) ?>">
                                    <span class="ld-level-icon <?= $lesson['ui_done'] ? 'is-done' : '' ?>">
                                        <?= $icon($glyph) ?>
                                    </span>

                                    <div class="ld-lesson-copy">
                                        <h4>
                                            <?= $e($lesson['name']) ?>

                                            <?php if ($current): ?>
                                                <span
                                                    class="ld-current-dot"
                                                    aria-label="Bài học tiếp theo"
                                                ></span>
                                            <?php endif; ?>
                                        </h4>

                                        <p>
                                            <?= (int) $lesson['question_count'] ?>
                                            câu
                                            <i></i>
                                            <?= (int) $lesson['xp_reward'] ?>
                                            XP
                                        </p>
                                    </div>

                                    <?php if ($lesson['ui_done']): ?>
                                        <a
                                            class="ld-btn ld-btn-small ld-btn-outline"
                                            href="<?= $e($b) ?>/learning/lesson/<?= (int) $lesson['id'] ?>?focus=result"
                                        >
                                            Xem lại
                                            <?= $icon('arrow') ?>
                                        </a>

                                    <?php elseif ($lesson['ui_open']): ?>
                                        <a
                                            class="ld-btn ld-btn-small <?= $current ? 'ld-btn-green' : 'ld-btn-outline' ?>"
                                            href="<?= $e($b) ?>/learning/lesson/<?= (int) $lesson['id'] ?>?focus=practice"
                                        >
                                            <?= $current ? 'Tiếp tục' : 'Vào học' ?>
                                            <?= $icon('arrow') ?>
                                        </a>

                                    <?php elseif (
                                        !$isGuest
                                        && !empty($lesson['unlocked'])
                                        && empty($lesson['ready'])
                                    ): ?>
                                        <button
                                            class="ld-btn ld-btn-small ld-btn-muted"
                                            type="button"
                                            disabled
                                        >
                                            Đang chuẩn bị
                                        </button>

                                    <?php else: ?>
                                        <button
                                            class="ld-btn ld-btn-small ld-btn-muted"
                                            type="button"
                                            data-locked
                                        >
                                            <?= $icon('lock') ?>
                                            Chưa mở
                                        </button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>

                        <?php if (!$stage['lessons']): ?>
                            <p class="ld-stage-empty">
                                Chặng học đang được chuẩn bị.
                            </p>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <?php if (!$stages): ?>
                    <div class="ld-empty">
                        <h2>Lộ trình đang được chuẩn bị</h2>

                        <p>
                            Các chặng sẽ xuất hiện khi nội dung được mở.
                        </p>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <aside class="ld-rail">
            <section class="ld-rail-card ld-overall">
                <span class="ld-eyebrow">
                    TỪNG BƯỚC TIẾN BỘ
                </span>

                <div
                    class="ld-ring"
                    style="--progress:<?= $percent ?>%"
                    role="img"
                    aria-label="Hoàn thành <?= $percent ?> phần trăm lộ trình"
                >
                    <div>
                        <strong><?= $percent ?>%</strong>
                        <span>lộ trình</span>
                    </div>
                </div>

                <h3>
                    <?= $done ?> / <?= $total ?> ải đã xong
                </h3>

                <p>
                    <?= $isGuest
                        ? 'Kết quả học thử được giữ trong phiên này.'
                        : 'Mỗi ải hoàn thành là một bước tiến.' ?>
                </p>
            </section>

            <section class="ld-rail-card">
                <h3>Góc học tập</h3>

                <div class="ld-tools">
                    <a href="<?= $e($b) ?>/learning/launch?mode=words">
                        <span class="ld-tool-art mint">
                            <img
                                src="<?= $e($b) ?>/assets/images/learning-ui/notebook.png"
                                alt=""
                                width="40"
                                height="40"
                            >
                        </span>

                        <span>
                            <strong>Flashcard</strong>
                            <small>Xem và nghe từ mới</small>
                        </span>

                        <?= $icon('arrow') ?>
                    </a>

                    <a href="<?= $e($b) ?>/learning/launch?mode=practice">
                        <span class="ld-tool-art lilac">
                            <img
                                src="<?= $e($b) ?>/assets/images/learning-ui/headphone.png"
                                alt=""
                                width="40"
                                height="40"
                            >
                        </span>

                        <span>
                            <strong>Luyện tập</strong>
                            <small>Thử sức từng câu hỏi</small>
                        </span>

                        <?= $icon('arrow') ?>
                    </a>

                    <a href="<?= $e($b) ?>/learning/progress">
                        <span class="ld-tool-art peach">
                            <img
                                src="<?= $e($b) ?>/assets/images/learning-ui/trophy.png"
                                alt=""
                                width="40"
                                height="40"
                            >
                        </span>

                        <span>
                            <strong>Thành tích</strong>
                            <small>Nhìn lại kết quả</small>
                        </span>

                        <?= $icon('arrow') ?>
                    </a>
                </div>
            </section>

            <?php if ($isGuest): ?>
                <section class="ld-rail-card ld-save-card">
                    <span class="ld-save-icon">
                        <?= $icon('user') ?>
                    </span>

                    <h3>Giữ lại hành trình của bạn</h3>

                    <p>
                        Đăng nhập để lưu kết quả và học tiếp.
                    </p>

                    <a
                        class="ld-btn ld-btn-green"
                        href="<?= $e($b) ?>/login<?= $guestCompleted ? '?next=guest' : '' ?>"
                    >
                        Đăng nhập
                        <?= $icon('arrow') ?>
                    </a>
                </section>
            <?php endif; ?>
        </aside>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/dashboard-end.php'; ?>