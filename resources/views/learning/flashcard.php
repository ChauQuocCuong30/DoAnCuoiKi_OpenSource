<link rel="stylesheet" href="/assets/css/learning.css">

<!-- Giao diện Flashcard kiểu Duolingo -->
<div class="flashcard-container">
    <div class="progress-bar-container">
        <a href="/learning" class="btn-close">✖</a>
        <div class="progress-bar"><div class="progress-fill" style="width: 10%;"></div></div>
    </div>

    <?php if (!empty($words)): ?>
        <?php $currentWord = $words[0]; // Tạm thời hiển thị từ đầu tiên ?>
        
        <div class="flashcard">
            <?php if (!empty($currentWord['image'])): ?>
                <img src="<?= htmlspecialchars($currentWord['image']) ?>" alt="word image" class="word-image">
            <?php endif; ?>
            
            <h1 class="word-english"><?= htmlspecialchars($currentWord['english']) ?></h1>
            <p class="word-ipa"><?= htmlspecialchars($currentWord['ipa']) ?></p>
            
            <div class="word-audio">
                <button class="btn-audio" onclick="playAudio('<?= htmlspecialchars($currentWord['audio']) ?>')">🔊 Nghe</button>
            </div>

            <hr>

            <h3 class="word-vietnamese"><?= htmlspecialchars($currentWord['vietnamese']) ?></h3>
            <div class="word-example">
                <p><strong>Ví dụ:</strong> <?= htmlspecialchars($currentWord['example']) ?></p>
                <p><em><?= htmlspecialchars($currentWord['example_translation']) ?></em></p>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="bottom-bar">
        <button class="btn-continue">Tiếp tục</button>
    </div>
</div>

<script>
    function playAudio(audioUrl) {
        if (audioUrl) {
            let audio = new Audio(audioUrl);
            audio.play();
        } else {
            alert("Từ này chưa có file ghi âm.");
        }
    }
</script>