<link rel="stylesheet" href="/doulingo/public/assets/css/learning.css">

<div class="learning-path-container">
    <?php if (!empty($topics)): ?>
        <?php foreach ($topics as $topic): ?>
            <div class="topic-section">
                <div class="topic-header">
                    <h2><?= htmlspecialchars($topic['name']) ?></h2>
                </div>
                
                <div class="lesson-nodes-container">
                    <!-- Đường dây nối các bài học -->
                    <div class="path-line"></div>
                    
                    <?php if (isset($lessonsByTopic[$topic['id']])): ?>
                        <?php foreach ($lessonsByTopic[$topic['id']] as $index => $lesson): ?>
                            <?php 
                                // MÔ PHỎNG DỮ LIỆU TỪ BACKEND: Số bài học user đã hoàn thành trong Topic này
                                // Đổi số này thành 1, 2, 3... để kiểm tra các nút tự động đổi màu theo tiến độ
                                $completedLessons = 0; 
                                
                                // Logic tự động xét trạng thái khóa/mở
                                if ($index < $completedLessons) {
                                    $statusClass = 'completed'; // Đã học xong -> Màu Vàng
                                } elseif ($index == $completedLessons) {
                                    $statusClass = 'active';    // Bài đang tới lượt học -> Màu Xanh lá (nhấp nháy)
                                } else {
                                    $statusClass = 'locked';    // Chưa mở khóa -> Màu Xám (không bấm được)
                                }
                                
                                // Ziczac trái phải
                                $positionClass = ($index % 2 == 0) ? 'left' : 'right'; 
                            ?>
                            
                            <div class="lesson-node-wrapper <?= $positionClass ?> <?= $statusClass ?>">
                                <a href="/doulingo/public/learning/lesson/<?= $lesson['id'] ?>" class="lesson-node btn-duo" <?= $statusClass === 'locked' ? 'onclick="return false;"' : '' ?>>
                                    <span class="lesson-icon">
                                        <?= $statusClass === 'locked' ? '🔒' : '⭐' ?>
                                    </span>
                                </a>
                                <span class="lesson-name"><?= htmlspecialchars($lesson['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Chưa có dữ liệu bài học.</p>
    <?php endif; ?>
</div>