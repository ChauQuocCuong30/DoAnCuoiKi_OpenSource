<?php
if ($isGuest || empty($history)) {
    return;
}

$historyZone = new \DateTimeZone($hearts['timezone'] ?? 'Asia/Ho_Chi_Minh');
$historyTime = static function (?string $value) use ($historyZone): string {
    if (!$value) return 'Chưa ghi nhận';
    return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
        ->setTimezone($historyZone)->format('H:i · d/m/Y');
};
?>

<style>
.saved-history {
    margin-top: 32px; padding: 24px;
    border: 2px solid var(--theme-line); border-radius: 24px;
    background: var(--theme-surface); color: var(--theme-ink);
}
.saved-history .history-heading {
    display: flex; align-items: flex-start; justify-content: space-between;
    flex-wrap: wrap; gap: 16px; margin-bottom: 22px;
}
.saved-history h2 { margin: 0; font-size: 23px; }
.saved-history .history-description {
    margin-top: 8px; color: var(--theme-muted); font-size: 14px; line-height: 1.7;
}
.saved-history .history-count, .saved-history .history-status {
    display: inline-flex; align-items: center; padding: 6px 11px;
    border-radius: 9px; font-size: 12px; font-weight: 750;
    background: var(--theme-soft); color: var(--theme-muted);
}
.saved-history .history-list { display: grid; gap: 14px; }
.saved-history .history-item {
    --history-bar: var(--theme-muted);
    padding: 20px; border: 1px solid var(--theme-line);
    border-radius: 17px; background: var(--theme-surface);
}
.saved-history .history-item.is-completed { --history-bar: var(--dq-green); }
.saved-history .history-item.is-progress { --history-bar: var(--dq-blue); }
.saved-history .history-top { display: flex; align-items: flex-start; gap: 14px; }
.saved-history .history-icon {
    display: grid; place-items: center; flex: 0 0 44px; height: 44px;
    border-radius: 13px; background: var(--theme-soft); color: var(--history-bar);
}
.saved-history .history-main { flex: 1; min-width: 0; }
.saved-history .history-title-row {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
}
.saved-history h3 { margin: 0; font-size: 17px; line-height: 1.5; overflow-wrap: anywhere; }
.saved-history .is-completed .history-status {
    background: var(--theme-good-bg); color: var(--theme-good);
}
.saved-history .is-progress .history-status { color: var(--dq-blue); }
.saved-history .history-times {
    display: flex; flex-wrap: wrap; gap: 6px 20px;
    margin-top: 9px; color: var(--theme-muted); font-size: 12px; line-height: 1.7;
}
.saved-history .history-progress { margin-top: 18px; }
.saved-history .history-stats {
    display: flex; flex-wrap: wrap; justify-content: space-between;
    gap: 8px; margin-bottom: 9px; color: var(--theme-muted); font-size: 13px;
}
.saved-history .history-correct { color: var(--theme-good); }
.saved-history progress {
    display: block; width: 100%; height: 8px; border: 0; border-radius: 20px;
    overflow: hidden; appearance: none; background: var(--theme-soft); color: var(--history-bar);
}
.saved-history progress::-webkit-progress-bar { background: var(--theme-soft); border-radius: 20px; }
.saved-history progress::-webkit-progress-value { background: var(--history-bar); border-radius: 20px; }
.saved-history progress::-moz-progress-bar { background: var(--history-bar); border-radius: 20px; }
.saved-history .history-actions { display: flex; justify-content: flex-end; margin-top: 16px; }
.saved-history .history-resume {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    min-height: 44px; padding: 10px 16px; border-radius: 12px;
    background: var(--dq-green); color: var(--dq-on-green); font-weight: 750;
    font-size: 14px; text-decoration: none; box-shadow: 0 3px 0 var(--dq-green-edge);
}
.saved-history .history-resume:hover { filter: brightness(.96); }
.saved-history .history-resume:focus-visible { outline: 3px solid var(--dq-blue); outline-offset: 4px; }
@media (max-width: 680px) {
    .saved-history { padding: 18px; }
    .saved-history .history-item { padding: 16px; }
    .saved-history .history-top { gap: 10px; }
    .saved-history h2 { font-size: 21px; }
    .saved-history .history-title-row { align-items: flex-start; flex-direction: column; }
    .saved-history .history-times { flex-direction: column; }
    .saved-history .history-resume { width: 100%; }
}
</style>

<section class="saved-history" aria-labelledby="saved-history-title">
    <div class="history-heading">
        <div>
            <h2 id="saved-history-title">Lịch sử học của bạn</h2>
            <p class="history-description">Tiến độ lưu theo tài khoản. Bạn có thể tiếp tục bài đang học sau khi đăng nhập lại.</p>
        </div>
        <span class="history-count"><?= count($history) ?> lượt gần nhất</span>
    </div>

    <div class="history-list">
        <?php foreach ($history as $entry):
            $completed = $entry['status'] === 'completed';
            $inProgress = $entry['status'] === 'in_progress';
            $stateClass = $completed ? 'is-completed' : ($inProgress ? 'is-progress' : 'is-ended');
            $stateLabel = $completed ? 'Đã hoàn thành' : ($inProgress ? 'Đang học' : 'Đã kết thúc');
            $total = max(0, (int) $entry['total_questions']);
            $answered = max(0, (int) $entry['answered_main']);
            $percent = $total > 0 ? min(100, (int) round($answered * 100 / $total)) : 0;
        ?>
            <article class="history-item <?= $stateClass ?>" data-history-session="<?= (int) $entry['id'] ?>">
                <div class="history-top">
                    <span class="history-icon" aria-hidden="true"><?= $icon($completed ? 'check' : 'book') ?></span>
                    <div class="history-main">
                        <div class="history-title-row">
                            <h3><?= $e($entry['name']) ?></h3>
                            <span class="history-status"><?= $stateLabel ?></span>
                        </div>
                        <div class="history-times">
                            <span>Bắt đầu: <?= $e($historyTime($entry['started_at'] ?? null)) ?></span>
                            <?php if ($completed || !empty($entry['finished_at'])): ?>
                                <span><?= $completed ? 'Hoàn thành' : 'Kết thúc' ?>: <?= $e($historyTime($entry['finished_at'] ?? null)) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="history-progress">
                    <div class="history-stats">
                        <span>Đã trả lời <strong><?= $answered ?>/<?= $total ?></strong> câu</span>
                        <span class="history-correct"><strong><?= (int) $entry['correct_count'] ?></strong> câu đúng</span>
                    </div>
                    <progress value="<?= $percent ?>" max="100" aria-label="Tiến độ trả lời của lượt học"></progress>
                </div>

                <?php if ($inProgress): ?>
                    <div class="history-actions">
                        <a class="history-resume" href="<?= $e($b) ?>/learning/practice?lesson=<?= (int) $entry['lesson_id'] ?>">
                            Tiếp tục bài đang học <?= $icon('arrow') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
