<?php
/** @var string $title */
/** @var string $base */
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Doulingo - <?= $e($title) ?></title>
    <meta name="description" content="Học từ vựng mỗi ngày với flashcard, ôn tập đúng lúc và theo dõi tiến độ.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $e($base) ?>/assets/css/welcome.css">
    <script>document.documentElement.classList.add('js');</script>
    <link rel="stylesheet" href="<?= $e($base) ?>/assets/css/transition.css">
    <script src="<?= $e($base) ?>/assets/js/transition.js"></script>
    <link rel="stylesheet" href="<?= $e($base) ?>/assets/css/learning-interactions.css?v=3">
    <?php require dirname(__DIR__) . '/layouts/theme-head.php'; ?>
    <style>
        /* Keep the original landscape and card artwork legible in both themes. */
        body.welcome-page { --body: var(--theme-muted); }
        .welcome-page .hero, .welcome-page .pcard { --ink: #123524; --gold: #b8862b; }
        .welcome-page .hero { color: #123524; }
        @media (max-width: 600px) {
            .welcome-page .hero .nav { justify-content: flex-start; }
            .welcome-page .account-navigation summary { max-width: 45vw; }
        }
    </style>
</head>
<body class="welcome-page">
<?php require dirname(__DIR__) . '/layouts/account-nav.php'; ?>
<div class="grain" aria-hidden="true"></div>

<!-- 1 · HERO -->
<section class="hero" id="hero">
    <div class="stage" id="stage">
        <div class="sky"></div>
        <div class="sun"></div>
        <div class="hill h1"></div><div class="hill h2"></div><div class="hill h3"></div>

        <div class="title-block">
            <div class="eyebrow">Học mỗi ngày · Nhớ lâu hơn</div>
            <h1 class="hero-title" data-split aria-label="Từ vựng">Từ vựng</h1>
        </div>

        <div class="fan" id="fan" aria-hidden="true">
            <div class="fc c1"><b>friend</b><span>bạn bè</span></div>
            <div class="fc c3"><b>travel</b><span>du lịch</span></div>
            <div class="fc c2"><b>apple</b><span>quả táo</span></div>
        </div>

        <a class="map-pill" href="<?= $e($base) ?>/start">Bắt đầu học
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>

        <div class="melt" id="melt"></div>
    </div>

    <nav class="nav" aria-label="Chính">
        <a class="brand" href="<?= $e($base) ?>/welcome">Doulingo<small>Học từ vựng</small></a>
    </nav>
</section>

<!-- 2 · STATEMENT -->
<section class="statement reveal" id="statement">
    <div class="label">Vì sao Doulingo</div>
    <p class="stmt" data-split>Học <i class="pp p1" aria-hidden="true"><b>hello</b></i> từng từ<br>ôn đúng <i class="pp p2" aria-hidden="true"><b>+20 XP</b></i> lúc<br>nhớ lâu <i class="pp p3" aria-hidden="true"><b>dream</b></i> hơn</p>
    <svg class="emblem" viewBox="0 0 66 66" aria-hidden="true">
        <circle cx="33" cy="33" r="31" /><circle cx="33" cy="33" r="26" />
        <path d="M33 47V29M33 36c-7 0-11-4-12-10 7 0 11 3 12 10zM33 32c6 0 10-4 11-9-6 0-10 3-11 9z"/>
    </svg>
</section>

<!-- 3 · QUICK FACTS -->
<section class="facts reveal" id="facts">
    <div class="facts-grid">
        <div class="col-a">
            <div class="label">Doulingo</div>
            <h2 class="ftitle" data-split>Tổng<br>quan<br>lộ trình<br>học từ</h2>
            <a class="badge" href="<?= $e($base) ?>/start" aria-label="Bắt đầu học">
                <svg viewBox="0 0 84 84" aria-hidden="true">
                    <g class="ticks" id="ticks"></g>
                    <path d="M35 30l22 12-22 12z" class="play"/>
                </svg>
            </a>
            <div class="cap">3 phút mỗi ngày</div>
        </div>

        <div class="col-b">
            <p class="lead">Doulingo giúp bạn học từ vựng theo chủ đề bằng flashcard có hình, nghĩa, ví dụ và phát âm. Hệ thống tự lên lịch ôn đúng lúc để từ nào cũng được nhớ lâu, và ghi lại XP cùng chuỗi ngày học của bạn.</p>
            <dl class="table">
                <div><dt>Hình thức</dt><dd>Flashcard và các dạng bài tập</dd></div>
                <div><dt>Ôn tập</dt><dd>Thuật toán SM-2</dd></div>
                <div><dt>Mục tiêu</dt><dd>XP và chuỗi ngày học</dd></div>
                <div><dt>Nhắc học</dt><dd>Thông báo trong ứng dụng</dd></div>
            </dl>
            <div class="cta-row">
                <a class="btn solid" href="<?= $e($base) ?>/start">Bắt đầu học</a>
                <a class="btn line" href="<?= $e($base) ?>/login">Đăng nhập</a>
            </div>
        </div>

        <div class="col-c">
            <div class="portrait" id="portrait">
                <div class="pimg" id="pimg">
                    <div class="pcard"><em>Aa</em><b>apple</b><span>quả táo</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="<?= $e($base) ?>/assets/js/welcome.js"></script>
</body>
</html>
