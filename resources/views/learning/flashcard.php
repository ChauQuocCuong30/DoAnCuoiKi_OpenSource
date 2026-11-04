<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bài tập Chọn hình</title>
    <link rel="stylesheet" href="/doulingo/public/assets/css/learning.css">
    <style>
        .exercise-container {
            width: 100%;
            max-width: 600px;
            height: 95vh;
            margin: 0 auto;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .exercise-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .hearts-container {
            font-size: 20px;
            letter-spacing: 5px;
        }
        .ex-question {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
            color: #ffffff;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
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
            height: 150px;
        }
        .image-card img {
            width: 95px;
            height: 95px;
            object-fit: contain;
            border-radius: 12px;
        }
        .image-card.selected {
            border-color: #1cb0f6;
            background-color: rgba(28, 176, 246, 0.15);
        }
        #feedback-msg {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            min-height: 30px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="dark-mode-bg">

    <?php 
        $isImageExercise = isset($_GET['mode']) && $_GET['mode'] === 'image';
    ?>

    <?php if ($isImageExercise): ?>
        <!-- ================= CHẶN 2: CHỌN HÌNH ================= -->
        <div class="exercise-container">
            <div class="exercise-top-bar">
                <a href="/doulingo/public/learning" class="btn-close" style="font-size: 24px; text-decoration: none; color: #afafaf;">✖</a>
                <div class="hearts-container" id="hearts-display">❤️❤️❤️❤️❤️</div>
            </div>

            <div class="flashcard-content" style="display: flex; flex-direction: column; flex-grow: 1;">
                <h2 class="ex-question">Chọn hình tương ứng với: <span id="target-word" style="color: #58cc02;">milk</span></h2>
                <div class="image-grid" id="image-options"></div>
                <div id="feedback-msg"></div>
            </div>

            <div class="flashcard-footer">
                <button class="btn-continue" id="btn-check" onclick="checkAnswer()" disabled style="opacity: 0.5; cursor: not-allowed; width: 100%; padding: 12px; border-radius: 16px; background: #58cc02; color: white; font-weight: bold; font-size: 16px; border: none;">KIỂM TRA</button>
            </div>
        </div>

        <script>
            // Kho dữ liệu chuẩn, icon đúng nghĩa và ĐÃ BỎ chữ tiếng Việt bên dưới ảnh
            const questions = [
                {
                    word: "milk",
                    correctName: "sữa",
                    options: [
                        { name: "sữa", img: "https://cdn-icons-png.flaticon.com/512/869/869692.png" }, // Hộp sữa
                        { name: "trà", img: "https://cdn-icons-png.flaticon.com/512/2935/2935300.png" }, // Tách trà
                        { name: "cà phê", img: "https://cdn-icons-png.flaticon.com/512/924/924514.png" }, // Cốc cà phê
                        { name: "đường", img: "https://cdn-icons-png.flaticon.com/512/3014/3014520.png" }  // Lọ đường
                    ]
                },
                {
                    word: "tea",
                    correctName: "trà",
                    options: [
                        { name: "cà phê", img: "https://cdn-icons-png.flaticon.com/512/924/924514.png" },
                        { name: "trà", img: "https://cdn-icons-png.flaticon.com/512/2935/2935300.png" },
                        { name: "sữa", img: "https://cdn-icons-png.flaticon.com/512/869/869692.png" },
                        { name: "đường", img: "https://cdn-icons-png.flaticon.com/512/3014/3014520.png" }
                    ]
                }
            ];

            let currentQIndex = 0;
            let selectedChoice = null;
            let lives = 5;

            function updateHearts() {
                let heartsStr = "";
                for (let i = 0; i < lives; i++) heartsStr += "❤️";
                for (let i = lives; i < 5; i++) heartsStr += "🖤";
                document.getElementById('hearts-display').textContent = heartsStr;
            }

            function loadQuestion() {
                if (currentQIndex >= questions.length) {
                    alert("Tuyệt vời! Bạn đã hoàn thành xuất sắc bài tập Chọn hình.");
                    window.location.href = '/doulingo/public/learning?completed=2';
                    return;
                }

                const q = questions[currentQIndex];
                document.getElementById('target-word').textContent = q.word;
                document.getElementById('feedback-msg').textContent = "";
                
                const grid = document.getElementById('image-options');
                grid.innerHTML = '';
                selectedChoice = null;

                const btnCheck = document.getElementById('btn-check');
                btnCheck.disabled = true;
                btnCheck.style.opacity = '0.5';
                btnCheck.style.cursor = 'not-allowed';
                btnCheck.textContent = "KIỂM TRA";

                q.options.forEach((opt) => {
                    const card = document.createElement('div');
                    card.className = 'image-card';
                    // Chỉ hiển thị hình ảnh, không in chữ tiếng Việt bên dưới
                    card.innerHTML = `<img src="${opt.img}" alt="${opt.name}">`;
                    card.onclick = () => selectOption(card, opt.name);
                    grid.appendChild(card);
                });
            }

            function selectOption(cardElement, name) {
                document.querySelectorAll('.image-card').forEach(c => c.classList.remove('selected'));
                cardElement.classList.add('selected');
                selectedChoice = name;

                const btnCheck = document.getElementById('btn-check');
                btnCheck.disabled = false;
                btnCheck.style.opacity = '1';
                btnCheck.style.cursor = 'pointer';
            }

            function checkAnswer() {
                const q = questions[currentQIndex];
                const msgEl = document.getElementById('feedback-msg');
                const btnCheck = document.getElementById('btn-check');

                if (selectedChoice === q.correctName) {
                    msgEl.style.color = "#58cc02";
                    msgEl.textContent = "Chính xác! 🎉";
                    btnCheck.textContent = "TIẾP TỤC";
                    btnCheck.onclick = function() {
                        currentQIndex++;
                        btnCheck.onclick = checkAnswer;
                        loadQuestion();
                    };
                } else {
                    lives--;
                    updateHearts();
                    msgEl.style.color = "#ff4b4b";
                    msgEl.textContent = "Chưa chính xác, hãy quan sát kỹ và thử lại nhé! 💪";

                    if (lives <= 0) {
                        alert("Bạn đã sử dụng hết lượt thử cho lần này. Hãy cố gắng hơn ở lần sau nhé!");
                        window.location.reload();
                    }
                }
            }

            window.onload = function() {
                updateHearts();
                loadQuestion();
            };
        </script>

    <?php else: ?>
        <!-- ================= CẤP 1: FLASHCARD GỐC ================= -->
        <div class="flashcard-container" style="max-width: 500px; margin: auto; padding: 20px; height: 90vh; display: flex; flex-direction: column; justify-content: space-between;">
            <div class="flashcard-header">
                <a href="/doulingo/public/learning" class="btn-close" style="font-size: 24px; text-decoration: none; color: #afafaf;">✖</a>
            </div>

            <div class="flashcard-content" style="text-align: center;">
                <h1 class="fc-word" id="fc-word" style="font-size: 32px; color: white;">...</h1>
                <p class="fc-pronunciation" id="fc-pronunciation" style="color: #8af;"></p>
                
                <button class="btn-listen" onclick="playSound()" style="background: #1cb0f6; border: none; padding: 10px 20px; border-radius: 12px; color: white; font-weight: bold; cursor: pointer; margin: 15px 0;">
                    🔊 Nghe
                </button>

                <hr style="border-color: #37464f; margin: 20px 0;">

                <h2 class="fc-translation" id="fc-translation" style="font-size: 24px; color: #58cc02;">...</h2>
                <p class="ex-en" id="fc-ex-en" style="color: white; margin-top: 10px;"></p>
            </div>

            <div class="flashcard-footer">
                <button class="btn-continue" onclick="nextWord()" style="width: 100%; padding: 12px; border-radius: 16px; background: #58cc02; color: white; font-weight: bold; font-size: 16px; border: none; cursor: pointer;">Tiếp tục</button>
            </div>
        </div>

        <script>
            const words = <?php echo isset($words) && !empty($words) ? json_encode($words) : '[]'; ?>;
            let currentIndex = 0;

            function renderWord(index) {
                if (index >= words.length) {
                    window.location.href = '/doulingo/public/learning?completed=1';
                    return; 
                }
                const currentWord = words[index];
                document.getElementById('fc-word').textContent = currentWord.english;
                document.getElementById('fc-pronunciation').textContent = currentWord.ipa ? currentWord.ipa : '';
                document.getElementById('fc-translation').textContent = currentWord.vietnamese;
                if (currentWord.example) {
                    document.getElementById('fc-ex-en').innerHTML = `<strong>Ví dụ:</strong> ${currentWord.example}`;
                }
                const btnNext = document.querySelector('.btn-continue');
                btnNext.textContent = (index === words.length - 1) ? 'KẾT THÚC' : 'TIẾP TỤC';
            }

            function playSound() {
                if (words.length > 0 && currentIndex < words.length) {
                    const utterance = new SpeechSynthesisUtterance(words[currentIndex].english);
                    utterance.lang = 'en-US';
                    window.speechSynthesis.speak(utterance);
                }
            }

            function nextWord() {
                currentIndex++;
                renderWord(currentIndex);
            }

            window.onload = function() {
                if (words && words.length > 0) renderWord(0);
            };
        </script>
    <?php endif; ?>

</body>
</html>