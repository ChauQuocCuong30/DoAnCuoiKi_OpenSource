<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bài tập: Chọn hình</title>
    <link rel="stylesheet" href="/doulingo/public/assets/css/learning.css">
    <style>
        /* CSS riêng cho dạng bài chọn hình */
        .exercise-container {
            width: 100%;
            max-width: 600px;
            height: 90vh;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .ex-question {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            text-align: center;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            flex-grow: 1;
            align-content: center;
        }
        .image-card {
            background-color: #1f2326;
            border: 3px solid #37464f;
            border-radius: 20px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 160px;
        }
        .image-card img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 10px;
        }
        .image-card span {
            font-size: 16px;
            font-weight: bold;
            color: #afafaf;
        }
        /* Trạng thái khi được chọn */
        .image-card.selected {
            border-color: #1cb0f6;
            background-color: rgba(28, 176, 246, 0.15);
        }
        .image-card.selected span {
            color: #1cb0f6;
        }
    </style>
</head>
<body class="dark-mode-bg">
    <div class="exercise-container">
        <!-- Header & Nút thoát -->
        <div class="flashcard-header">
            <a href="/doulingo/public/learning" class="btn-close">✖</a>
        </div>

        <!-- Nội dung câu hỏi -->
        <div class="flashcard-content">
            <h2 class="ex-question">Chọn hình tương ứng với: <span id="target-word" style="color: #58cc02;">milk</span></h2>
            
            <!-- Lưới chọn hình (4 đáp án) -->
            <div class="image-grid" id="image-options">
                <!-- Các ô hình ảnh sẽ được render bằng JavaScript -->
            </div>
        </div>

        <!-- Footer & Nút Kiểm tra -->
        <div class="flashcard-footer">
            <button class="btn-continue" id="btn-check" onclick="checkAnswer()" disabled style="opacity: 0.5; cursor: not-allowed;">KIỂM TRA</button>
        </div>
    </div>

    <script>
        // Dữ liệu câu hỏi mẫu (sau này lấy từ CSDL của nhóm)
        const questions = [
            {
                word: "milk",
                correctImage: "/doulingo/public/assets/img/milk.png",
                options: [
                    { name: "sữa", img: "/doulingo/public/assets/img/milk.png" },
                    { name: "trà", img: "/doulingo/public/assets/img/tea.png" },
                    { name: "cà phê", img: "/doulingo/public/assets/img/coffee.png" },
                    { name: "đường", img: "/doulingo/public/assets/img/sugar.png" }
                ]
            },
            {
                word: "tea",
                correctImage: "/doulingo/public/assets/img/tea.png",
                options: [
                    { name: "cà phê", img: "/doulingo/public/assets/img/coffee.png" },
                    { name: "trà", img: "/doulingo/public/assets/img/tea.png" },
                    { name: "sữa", img: "/doulingo/public/assets/img/milk.png" },
                    { name: "đường", img: "/doulingo/public/assets/img/sugar.png" }
                ]
            }
        ];

        let currentQIndex = 0;
        let selectedChoice = null;

        function loadQuestion() {
            if (currentQIndex >= questions.length) {
                // Hoàn thành ải 2 -> Chuyển về trang lộ trình và mở khóa Cấp 3 (?completed=2)
                alert("Chúc mừng bạn đã hoàn thành Chặng 2: Chọn hình!");
                window.location.href = '/doulingo/public/learning?completed=2';
                return;
            }

            const q = questions[currentQIndex];
            document.getElementById('target-word').textContent = q.word;
            
            const grid = document.getElementById('image-options');
            grid.innerHTML = '';
            selectedChoice = null;

            // Disable nút kiểm tra khi chưa chọn
            const btnCheck = document.getElementById('btn-check');
            btnCheck.disabled = true;
            btnCheck.style.opacity = '0.5';
            btnCheck.style.cursor = 'not-allowed';

            // Render 4 ô hình ảnh
            q.options.forEach((opt, idx) => {
                const card = document.createElement('div');
                card.className = 'image-card';
                card.innerHTML = `
                    <img src="${opt.img}" alt="${opt.name}" onerror="this.src='https://via.placeholder.com/90?text=Image'">
                    <span>${opt.name}</span>
                `;
                card.onclick = () => selectOption(card, opt.name);
                grid.appendChild(card);
            });
        }

        function selectOption(cardElement, name) {
            // Xóa chọn ở các ô khác
            document.querySelectorAll('.image-card').forEach(c => c.classList.remove('selected'));
            // Đánh dấu ô đang chọn
            cardElement.classList.add('selected');
            selectedChoice = name;

            // Kích hoạt nút kiểm tra
            const btnCheck = document.getElementById('btn-check');
            btnCheck.disabled = false;
            btnCheck.style.opacity = '1';
            btnCheck.style.cursor = 'pointer';
        }

        function checkAnswer() {
            const q = questions[currentQIndex];
            // Tìm tên tiếng Việt tương ứng với hình đúng
            const correctOpt = q.options.find(o => o.img === q.correctImage);
            
            if (selectedChoice === correctOpt.name) {
                alert("Chính xác! 🎉");
                currentQIndex++;
                loadQuestion();
            } else {
                alert("Chưa chính xác, hãy thử lại nhé! 💪");
            }
        }

        window.onload = function() {
            loadQuestion();
        };
    </script>
</body> 
</html>