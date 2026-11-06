<?php
declare(strict_types=1);

$pageTitle = 'Doulingo — Học từ mới mỗi ngày';
$navActive = 'home';

require __DIR__ . '/learning/dashboard-shell.php';

$topicsHome = [
    ['Đồ uống và giao tiếp', 'milk', 'mint', 'drinks'],
    ['Giao thông', 'bus', 'blue', 'transport'],
    ['Quốc gia', 'vietnam', 'lilac', 'countries'],
    ['Động vật', 'cat', 'peach', 'animals'],
];
?>

<section class="ld-hero ld-home-hero"
         aria-labelledby="ld-home-title">

    <div class="ld-hero-copy">
        <span class="ld-eyebrow ld-eyebrow-light">
            <i></i> TIẾNG ANH MỖI NGÀY
        </span>

        <h1 id="ld-home-title">
            Học từ mới.<br>
            <span>Dùng được ngay.</span>
        </h1>

        <p>
            Khám phá từ vựng qua hình ảnh, âm thanh
            và từng thử thách nhỏ.
        </p>

        <div class="ld-hero-actions">
            <a
                class="ld-btn ld-btn-yellow"
                href="<?= $e($b) ?>/learning/<?= $isGuest ? 'explore' : 'practice' ?>"
            >
                <?= $isGuest
                    ? 'Khám phá từ mới'
                    : 'Bắt đầu luyện tập' ?>

                <?= $icon('arrow') ?>
            </a>

            <a class="ld-hero-link"
               href="<?= $e($b) ?>/learning">
                Xem lộ trình <?= $icon('arrow') ?>
            </a>
        </div>

        <?php if ($isGuest): ?>
            <small class="ld-hero-note">
                <?= $icon('check') ?>
                Học thử ải đầu, chưa cần tài khoản.
            </small>
        <?php endif; ?>
    </div>

    <div class="ld-hero-visual" aria-hidden="true">
        <div class="ld-visual-scene" data-tilt>
            <div class="ld-orbit ld-orbit-one"></div>
            <div class="ld-orbit ld-orbit-two"></div>

            <div class="ld-book-wrap">
                <img
                    class="ld-floating-book"
                    src="<?= $e($b) ?>/assets/images/learning-ui/notebook.png"
                    alt=""
                    width="200"
                    height="200"
                >
            </div>

            <div class="ld-word-chip ld-word-one">
                <span class="ld-chip-sound">
                    <?= $icon('headphones') ?>
                </span>

                <strong>hello</strong>
                <small>xin chào</small>
            </div>

            <div class="ld-word-chip ld-word-two">
                <strong>milk</strong>
                <small>sữa</small>
                <i></i>
            </div>

            <span class="ld-spark ld-spark-one">✦</span>
            <span class="ld-spark ld-spark-two">✦</span>
        </div>
    </div>
</section>

<section class="ld-section" aria-labelledby="ld-ways-title">
    <div class="ld-section-head">
        <div>
            <span class="ld-eyebrow">BẮT ĐẦU TỪ ĐÂY</span>

            <h2 id="ld-ways-title">
                Chọn cách học của bạn
            </h2>
        </div>

        <span class="ld-section-note">
            Một bước nhỏ, một điều mới.
        </span>
    </div>

    <div class="ld-shortcuts">
        <a class="ld-shortcut ld-shortcut-mint"
           href="<?= $e($b) ?>/learning/explore">

            <span class="ld-shortcut-no">01</span>

            <img
                src="<?= $e($b) ?>/assets/images/learning-ui/notebook.png"
                alt=""
                width="110"
                height="110"
            >

            <h3>Khám phá từ mới</h3>
            <p>Nghĩa, ví dụ và phát âm.</p>

            <span class="ld-shortcut-bottom">
                Mở flashcard
                <i><?= $icon('arrow') ?></i>
            </span>
        </a>

        <a class="ld-shortcut ld-shortcut-lilac"
           href="<?= $e($b) ?>/learning/practice">

            <span class="ld-shortcut-no">02</span>

            <img
                src="<?= $e($b) ?>/assets/images/learning-ui/headphone.png"
                alt=""
                width="110"
                height="110"
            >

            <h3>Thử sức mỗi ngày</h3>
            <p>Bốn dạng bài, nhiều cách nhớ.</p>

            <span class="ld-shortcut-bottom">
                Vào luyện tập
                <i><?= $icon('arrow') ?></i>
            </span>
        </a>
    </div>
</section>

<section class="ld-section" aria-labelledby="ld-topics-title">
    <div class="ld-section-head">
        <div>
            <span class="ld-eyebrow">
                KHÁM PHÁ THẾ GIỚI
            </span>

            <h2 id="ld-topics-title">
                Bắt đầu từ điều quen thuộc
            </h2>
        </div>

        <a class="ld-text-link"
           href="<?= $e($b) ?>/learning/explore">
            Tất cả chủ đề <?= $icon('arrow') ?>
        </a>
    </div>

    <div class="ld-topics-grid">
        <?php foreach (
            $topicsHome as $i => [$name, $slug, $tone, $category]
        ): ?>
            <a
                class="ld-topic-card ld-tone-<?= $e($tone) ?>"
                href="<?= $e($b) ?>/learning/explore?category=<?= $e($category) ?>"
            >
                <div class="ld-topic-image">
                    <img
                        src="<?= $e($b) ?>/assets/media/images/<?= $e($slug) ?>.png"
                        alt=""
                        width="90"
                        height="90"
                        loading="lazy"
                    >
                </div>

                <span>
                    CHẶNG <?= str_pad(
                        (string) ($i + 1),
                        2,
                        '0',
                        STR_PAD_LEFT
                    ) ?>
                </span>

                <h3><?= $e($name) ?></h3>

                <div class="ld-topic-bottom">
                    <small>4 ải học</small>
                    <?= $icon('arrow') ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/learning/dashboard-end.php'; ?>