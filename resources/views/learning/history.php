<?php if (!$isGuest && !empty($history)): ?>
<section class="ld-section" aria-labelledby="saved-history-title">
    <h2 id="saved-history-title">Lịch sử học của bạn</h2>
    <p>Tiến độ lưu theo tài khoản, tiếp tục được sau khi đăng nhập lại.</p>
    <div class="ld-stage" style="padding:20px">
    <?php foreach ($history as $entry): ?>
        <p data-history-session="<?= (int)$entry['id'] ?>">
            <small><?= $e((new \DateTimeImmutable($entry['started_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($hearts['timezone']??'Asia/Ho_Chi_Minh'))->format('H:i d/m/Y')) ?></small> · <strong><?= $e($entry['name']) ?></strong> ·
            <?= $entry['status']==='completed'?'Đã hoàn thành':($entry['status']==='in_progress'?'Đang học':'Lượt học đã kết thúc') ?> ·
            <?= (int)$entry['answered_main'] ?>/<?= (int)$entry['total_questions'] ?> câu · <?= (int)$entry['correct_count'] ?> câu đúng
            <?php if ($entry['status']==='in_progress'): ?><a class="ld-btn ld-btn-small ld-btn-outline" href="<?= $e($b) ?>/learning/practice?lesson=<?= (int)$entry['lesson_id'] ?>">Tiếp tục bài đang học</a><?php endif; ?>
        </p>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
