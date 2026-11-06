<?php declare(strict_types=1); ?>
<meta name="theme-color" content="#eaf0dc">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<?php
$adminStyles = ['admin-books'];
if ($activeAdminPage === 'topics') {
    $adminStyles[] = 'admin-topics';
}
$adminStyles[] = 'admin-welcome-theme';
if (in_array($activeAdminPage, ['learners'], true)) {
    $adminStyles[] = 'admin-tools';
}
foreach ($adminStyles as $adminStyle):
    $adminAsset = '/assets/css/' . $adminStyle . '.css';
    $adminVersion = (string) filemtime(dirname(__DIR__, 3) . '/public' . $adminAsset);
?>
<link rel="stylesheet" href="<?= $escape($appBase . $adminAsset . '?v=' . $adminVersion) ?>">
<?php endforeach; ?>
