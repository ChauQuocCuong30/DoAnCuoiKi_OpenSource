<?php require __DIR__ . '/_j_head.php'; ?>
<header class="j-top">
    <a class="x" href="<?= $e($base) ?>/level" aria-label="Thoát">×</a>
    <div class="bar"><i id="bar"></i></div>
    <div class="hearts" aria-label="Số tim còn lại"><svg viewBox="0 0 24 24"><path d="M12 21s-8-5.2-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.8-8 11-8 11z"/></svg><b id="hearts">5</b></div>
</header>
<main class="j-stage" id="stage"></main>
<footer class="j-foot" id="foot">
    <div class="fb" id="fb"></div>
    <button class="act" id="act" type="button" disabled>Kiểm tra</button>
</footer>
<?php require __DIR__ . '/_j_foot.php'; ?>
