<?php
/** @var string $title */
/** @var string $base */
/** @var string $page */
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?> - Doulingo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $e($base) ?>/assets/css/journey.css?v=2">
    <link rel="stylesheet" href="<?= $e($base) ?>/assets/css/transition.css">
    <script src="<?= $e($base) ?>/assets/js/transition.js"></script>
<link rel="stylesheet" href="<?= $e($base) ?>/assets/css/learning-interactions.css?v=3">
    <script defer src="<?= $e($base) ?>/assets/js/learning-media.js?v=1"></script>
    <script defer src="<?= $e($base) ?>/assets/js/learning-audio.js?v=2"></script>
<?php require dirname(__DIR__) . '/layouts/theme-head.php'; ?></head>
<body class="dq-demo" data-page="<?= $e($page) ?>" data-base="<?= $e($base) ?>">
<?php require dirname(__DIR__) . '/layouts/account-nav.php'; ?>
