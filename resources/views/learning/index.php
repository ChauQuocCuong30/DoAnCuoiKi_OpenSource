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
                    
                    <?php 
                        // Khởi tạo Session và đọc tiến độ
                        if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                        }
                        if (isset($_GET['completed'])) {
                            $_SESSION['completedLessons'] = (int)$_GET['completed'];
                        }
                        $completedLessons = isset($_SESSION['completedLessons']) ? $_SESSION['completedLessons'] : 0; 
                        
                        // Khai báo 4 ải
                        $fourLevels = [
                            ['id' => 1, 'name' => 'Cấp 1: Nghe'],
                            ['id' => 2, 'name' => 'Cấp 2: Chọn hình'],
                            ['id' => 3, 'name' => 'Cấp 3: Chọn nghĩa'],
                            ['id' => 4, 'name' => 'Cấp 4: Tổng hợp']
                        ];
                    ?>

                    <?php foreach ($fourLevels as $index => $level): ?>
                        <?php 
                            // Logic tự động xét trạng thái khóa/mở
                            if ($index < $completedLessons) {
                                $statusClass = 'completed'; // Đã học xong -> Màu Vàng
                            } elseif ($index == $completedLessons) {
                                $statusClass = 'active';    // Đang tới lượt -> Màu Xanh lá nhấp nháy
                            } else {
                                $statusClass = 'locked';    // Chưa mở -> Màu Xám
                            }
                            
                            // Ziczac trái phải
                            $positionClass = ($index % 2 == 0) ? 'left' : 'right'; 
                            
                            // Điều hướng riêng cho từng cấp độ
                            if ($index === 1) {
                                $linkHref = "/doulingo/public/learning/lesson/1?mode=image"; // Cấp 2: Chọn hình
                            } else {
                                $linkHref = "/doulingo/public/learning/lesson/1"; // Các cấp khác
                            }
                        ?>
                        
                        <div class="lesson-node-wrapper <?= $positionClass ?> <?= $statusClass ?>">
                            <a href="<?= $linkHref ?>" class="lesson-node btn-duo" <?= $statusClass === 'locked' ? 'onclick="return false;"' : '' ?>>
                                <span class="lesson-icon">
                                    <?= $statusClass === 'locked' ? '🔒' : '⭐' ?>
                                </span>
                            </a>
                            <span class="lesson-name"><?= $level['name'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Chưa có dữ liệu bài học.</p>
    <?php endif; ?>
</div>