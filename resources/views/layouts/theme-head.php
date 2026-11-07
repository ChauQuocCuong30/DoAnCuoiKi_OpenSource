<?php
declare(strict_types=1);
$themeBase = $base ?? $basePath ?? $b ?? $appBase ?? $reviewApiBase ?? '';
$themeEscape = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $themeEscape($themeBase) ?>/assets/css/theme.css?v=3">
<script src="<?= $themeEscape($themeBase) ?>/assets/js/theme.js?v=1"></script>
