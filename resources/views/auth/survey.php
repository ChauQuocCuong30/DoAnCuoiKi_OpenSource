<?php
declare(strict_types=1);
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$csrf=\App\Core\Session::csrfToken();
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bắt đầu học · Doulingo</title>
<link rel="stylesheet" href="<?= $e($base) ?>/assets/css/learning-interactions.css?v=3">
<script defer src="<?= $e($base) ?>/assets/js/onboarding-survey.js?v=1"></script><?php require dirname(__DIR__) . '/layouts/theme-head.php'; ?></head>
<body class="dq-survey" data-base="<?= $e($base) ?>" data-csrf="<?= $e($csrf) ?>">
<header class="dq-survey-header"><a class="dq-brand" href="<?= $e($base) ?>/">D<span>oulingo.</span></a><div class="theme-account-actions"><?php require dirname(__DIR__) . "/layouts/theme-toggle.php"; ?><a href="<?= $e($base) ?>/login">Tôi đã có tài khoản</a></div></header>
<main class="dq-survey-main"><div class="dq-survey-progress"><button id="ds-back" type="button" aria-label="Bước trước">←</button><progress id="ds-progress" value="0" max="7" aria-label="Tiến độ khảo sát"></progress></div>
<div class="dq-guide"><img src="<?= $e($base) ?>/assets/images/learning-ui/notebook.png" alt="" width="100" height="100"><h1 id="ds-title" tabindex="-1">Đang tải khảo sát…</h1></div>
<div id="ds-options" class="dq-survey-options"></div><p id="ds-note" class="dq-survey-note"></p><p id="ds-error" role="alert"></p>
<noscript>Bật JavaScript để trả lời khảo sát và lưu mục tiêu học.</noscript></main>
<footer class="dq-survey-footer"><span id="ds-step-label"></span><button id="ds-next" class="dq-primary" type="button" disabled>Tiếp tục</button></footer>
</body></html>
