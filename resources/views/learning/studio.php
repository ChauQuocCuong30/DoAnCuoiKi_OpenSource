<?php
declare(strict_types=1);

$studioMode = ($studioMode ?? 'explore') === 'practice'
    ? 'practice'
    : 'explore';

$practice = $studioMode === 'practice';
$navActive = $studioMode;

$pageTitle = (
    $practice ? 'Luyện tập' : 'Khám phá từ mới'
) . ' · Doulingo';

$topics = $topics ?? [];
$groups = $groups ?? [];
$selectedTopic = $selectedTopic ?? null;
$exploreWords = $exploreWords ?? [];
$selectedBand = $selectedBand ?? '';

require __DIR__ . '/dashboard-shell.php';

$studioUrl = $b . '/learning/' . $studioMode;
$art = $practice ? 'headphone' : 'notebook';

$previews = [
    'Đồ uống và giao tiếp' => 'milk',
    'Giao thông' => 'bus',
    'Quốc gia' => 'vietnam',
    'Động vật' => 'cat',
];
?>

<style>
.ld .ls-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 28px 32px;
    border: 1px solid #d9eddf;
    border-radius: 24px;
    background: linear-gradient(115deg, #e7faed, #f8fff1);
}

.ld .ls-hero h1 {
    margin: 8px 0 10px;
    font-size: clamp(27px, 3vw, 38px);
}

.ld .ls-hero p,
.ld .ls-muted {
    color: #65796b;
}

.ld .ls-hero > img {
    width: 125px;
    height: 125px;
    filter: drop-shadow(0 12px 12px #17472b22);
}

.ld .ls-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin: 24px 0;
}

.ld .ls-tabs a {
    padding: 10px 15px;
    border: 1px solid #dfeae3;
    border-radius: 12px;
    background: #fff;
    font-size: 13px;
}

.ld .ls-tabs a[aria-current] {
    background: #e5f7ea;
    border-color: #92d6a8;
    color: #128446;
    font-weight: 700;
}

.ld .ls-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 22px;
}

.ld .ls-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 24px;
    border: 1px solid #e1ebe5;
    border-radius: 20px;
    background: #fff;
}

.ld .ls-card h3 {
    font-size: 20px;
}

.ld .ls-card .ld-btn {
    align-self: flex-start;
    margin-top: auto;
}

.ld .ls-card p {
    font-size: 13px;
    color: #65796b;
}

.ld .ls-number {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #e8f8ed;
    color: #16974d;
    font-weight: 750;
}

.ld .ls-notice {
    margin: 20px 0;
    padding: 18px 20px;
    border: 1px solid #f0df9f;
    border-radius: 16px;
    background: #fffbeb;
}

.ld .ls-notice .ld-btn {
    margin-top: 12px;
}

.ld .ls-reader {
    display: grid;
    grid-template-columns: 160px minmax(0, 1fr);
    gap: 22px;
    margin-top: 24px;
}

.ld .ls-word-list {
    display: flex;
    flex-direction: column;
    gap: 7px;
    max-height: 540px;
    overflow: auto;
    padding: 4px;
}

.ld .ls-word-list button {
    padding: 12px 14px;
    border: 1px solid #e3ece6;
    border-radius: 12px;
    background: #fff;
    color: #4b6858;
    text-align: left;
}

.ld .ls-word-list button[aria-current] {
    border-color: #7bcc98;
    background: #eaf9ef;
    color: #118547;
    font-weight: 700;
}

.ld .ls-flashcard {
    padding: 28px;
    border: 1px solid #dfeae3;
    border-radius: 24px;
    background: #fff;
}

.ld .ls-word-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
    align-items: center;
    gap: 28px;
}

.ld .ls-picture {
    display: grid;
    place-items: center;
    min-height: 265px;
    padding: 25px;
    border: 1px solid #e0eee4;
    border-radius: 20px;
    background: #f3fbf5;
}

.ld .ls-picture img {
    width: 100%;
    height: 220px;
    object-fit: contain;
}

.ld .ls-word-copy h2 {
    margin-bottom: 10px;
    color: #12924a;
    font-size: clamp(32px, 4vw, 52px);
}

.ld .ls-meaning {
    margin: 14px 0 !important;
    font-size: 23px;
}

.ld .ls-example {
    margin-top: 20px;
    padding: 17px;
    border-left: 3px solid #ffd04d;
    border-radius: 12px;
    background: #f2faf4;
}

.ld .ls-example p {
    margin-top: 9px;
}

.ld .ls-example small {
    display: block;
    margin-top: 8px;
    color: #65796b;
}

.ld .ls-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 26px;
}

.ld .ls-audio-note {
    min-height: 23px;
    margin-top: 14px;
    font-size: 12px;
    color: #65796b;
}

@media (max-width: 1050px) {
    .ld .ls-reader {
        grid-template-columns: 1fr;
    }

    .ld .ls-word-list {
        flex-direction: row;
        max-height: none;
        overflow: auto;
    }

    .ld .ls-word-list button {
        white-space: nowrap;
    }

    .ld .ls-word-grid {
        gap: 20px;
    }
}

@media (max-width: 680px) {
    .ld .ls-hero {
        padding: 23px;
        gap: 12px;
    }

    .ld .ls-hero > img {
        width: 78px;
        height: 78px;
    }

    .ld .ls-hero p {
        font-size: 12px;
    }

    .ld .ls-list,
    .ld .ls-word-grid {
        grid-template-columns: 1fr;
    }

    .ld .ls-flashcard {
        padding: 20px;
    }

    .ld .ls-picture {
        min-height: 185px;
    }

    .ld .ls-picture img {
        height: 160px;
    }

    .ld .ls-controls {
        flex-wrap: wrap;
    }

    .ld .ls-tabs a {
        font-size: 12px;
    }
}
</style>

<section class="ls-hero">
    <div>
        <span class="ld-eyebrow">
            <?= $practice
                ? 'THỬ SỨC MỖI NGÀY'
                : 'THƯ VIỆN TỪ VỰNG' ?>
        </span>

        <h1>
            <?= $practice
                ? 'Luyện tập'
                : 'Khám phá từ mới' ?>
        </h1>

        <p>
            <?= $practice
                ? 'Chọn chủ đề và dạng bài bạn muốn luyện.'
                : 'Chọn chủ đề để xem hình, nghe từ và học cách sử dụng.' ?>
        </p>
    </div>

    <img
        src="<?= $e($b) ?>/assets/images/learning-ui/<?= $e($art) ?>.png"
        alt=""
    >
</section>

<?php if (!empty($loadError)): ?>

    <section class="ld-empty" role="alert">
        <h2>Chưa mở được trang này</h2>

        <p><?= $e($loadError) ?></p>

        <a class="ld-btn ld-btn-outline"
           href="<?= $e($studioUrl) ?>">
            Chọn lại chủ đề
        </a>
    </section>

<?php else: ?>

    <?php if (!empty($pendingGuest)): ?>
        <div class="ls-notice">
            <p>Bạn còn kết quả học thử cần lưu vào tài khoản.</p>

            <a class="ld-btn ld-btn-green"
               href="<?= $e($b) ?>/learning/continue?focus=<?= $practice ? 'practice' : 'words' ?>">
                Lưu kết quả học thử
            </a>
        </div>
    <?php endif; ?>

    <nav class="ls-tabs" aria-label="Chọn chủ đề">
        <a href="<?= $e($studioUrl) ?>"
           <?= $selectedTopic === null
               ? 'aria-current="page"'
               : '' ?>>
            Tất cả chủ đề
        </a>

        <?php foreach ($topics as $topic): ?>
            <a
                href="<?= $e($studioUrl) ?>?topic=<?= (int) $topic['id'] ?>"
                <?= (int) ($selectedTopic['id'] ?? 0)
                    === (int) $topic['id']
                        ? 'aria-current="page"'
                        : '' ?>
            >
                <?= $e($topic['name']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$practice && $selectedTopic!==null): ?>
        <nav class="ls-tabs" aria-label="Chọn mức từ vựng">
            <a href="<?= $e($studioUrl) ?>?topic=<?= (int)$selectedTopic['id'] ?>" <?= $selectedBand===''?'aria-current="page"':'' ?>>Tất cả mức</a>
            <?php foreach (\App\Shared\Cefr::LEVELS as $band): ?><a href="<?= $e($studioUrl) ?>?topic=<?= (int)$selectedTopic['id'] ?>&amp;level=<?= $band ?>" <?= $selectedBand===$band?'aria-current="page"':'' ?>><?= $band ?></a><?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($selectedTopic === null): ?>

        <div class="ld-topics-grid">
            <?php foreach ($topics as $topic): ?>
                <?php
                $preview = isset($previews[$topic['name']])
                    ? '/assets/media/images/'
                        . $previews[$topic['name']]
                        . '.png'
                    : '/assets/images/learning-ui/notebook.png';
                ?>

                <a class="ld-topic-card"
                   href="<?= $e($studioUrl) ?>?topic=<?= (int) $topic['id'] ?>">

                    <div class="ld-topic-image">
                        <img src="<?= $e($b . $preview) ?>"
                             alt=""
                             width="90"
                             height="90">
                    </div>

                    <h3><?= $e($topic['name']) ?></h3>

                    <div class="ld-topic-bottom">
                        <small>
                            <?= $practice
                                ? 'Chọn dạng bài'
                                : 'Khám phá từ vựng' ?>
                        </small>

                        <?= $icon('arrow') ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($topics === []): ?>
            <section class="ld-empty">
                <p>Chưa có chủ đề đang mở.</p>
            </section>
        <?php endif; ?>

    <?php elseif ($practice): ?>

        <h2><?= $e($selectedTopic['name']) ?></h2>

        <div class="ls-list">
            <?php foreach (
                $groups[(int) $selectedTopic['id']] ?? []
                as $i => $lesson
            ): ?>
                <?php
                $label = preg_replace(
                    '/^Ải\s+\d+\s*[·:—–-]\s*/u',
                    '',
                    (string) $lesson['name']
                );
                ?>

                <article class="ls-card">
                    <span class="ls-number"><?= $i + 1 ?></span>

                    <h3><?= $e($label) ?></h3>

                    <p>
                        <?= (int) $lesson['question_count'] ?> câu hỏi
                        <?php if (!empty($lesson['active_session'])): ?> · Đang học <?= (int)$lesson['active_session']['answered_main'] ?>/<?= (int)$lesson['active_session']['total_questions'] ?> câu<?php endif; ?>
                        <?= !empty($lesson['completed'])
                            ? ' · Đã hoàn thành'
                            : '' ?>
                    </p>

                    <?php if (!empty($pendingGuest)): ?>

                        <button class="ld-btn ld-btn-muted" disabled>
                            Lưu kết quả học thử trước
                        </button>

                    <?php elseif (empty($lesson['ready'])): ?>

                        <p>Chủ đề cần ít nhất 2 từ đang hiện để tạo câu hỏi.</p>

                    <?php elseif (!empty($lesson['unlocked'])): ?>

                        <a
                            class="ld-btn ld-btn-green"
                            href="<?= $e($b) ?>/learning/practice?lesson=<?= (int) $lesson['id'] ?>"
                        >
                            <?= !empty($lesson['completed'])
                                ? (
                                    $isGuest
                                        ? 'Xem kết quả'
                                        : 'Luyện lại'
                                )
                                : 'Vào luyện tập' ?>

                            <?= $icon('arrow') ?>
                        </a>

                    <?php elseif ($isGuest): ?>

                        <a class="ld-btn ld-btn-outline"
                           href="<?= $e($b) ?>/login?next=guest">
                            Đăng nhập để học
                        </a>

                    <?php else: ?>

                        <button class="ld-btn ld-btn-muted" disabled>
                            Hoàn thành bài trước
                        </button>

                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

    <?php elseif ($exploreWords === []): ?>

        <section class="ld-empty">
            <h2><?= $e($selectedTopic['name']) ?></h2>

            <p>
                <?= $isGuest
                    ? 'Đăng nhập để khám phá thêm chủ đề này.'
                    : 'Chủ đề chưa có từ vựng đang mở.' ?>
            </p>

            <?php if ($isGuest): ?>
                <a class="ld-btn ld-btn-green"
                   href="<?= $e($b) ?>/login?next=guest">
                    Đăng nhập
                </a>
            <?php endif; ?>
        </section>

    <?php else: ?>

        <h2><?= $e($selectedTopic['name']) ?></h2>

        <div class="ls-reader">
            <nav class="ls-word-list" aria-label="Danh sách từ vựng">
                <?php foreach ($exploreWords as $i => $word): ?>
                    <button type="button"
                            data-word-index="<?= $i ?>">
                        <?= $e($word['english']) ?> · <?= $e($word['cefr_level']??'A1') ?>
                    </button>
                <?php endforeach; ?>
            </nav>

            <section class="ls-flashcard" aria-label="Thẻ từ vựng">
                <div class="ls-word-grid">
                    <div class="ls-picture">
                        <img id="ls-image" alt="" hidden>

                        <p id="ls-no-image" class="ls-muted" hidden>
                            Chưa có hình minh họa
                        </p>
                    </div>

                    <div class="ls-word-copy">
                        <h2 id="ls-word"></h2>

                        <p id="ls-ipa" class="ls-muted"></p>

                        <p id="ls-meaning" class="ls-meaning"></p>

                        <button id="ls-sound"
                                type="button"
                                class="ld-btn ld-btn-outline">
                            Nghe phát âm ♪
                        </button>

                        <div id="ls-example" class="ls-example">
                            <span class="ld-eyebrow">VÍ DỤ</span>

                            <p id="ls-example-en"></p>

                            <small id="ls-example-vi"></small>
                        </div>
                    </div>
                </div>

                <div class="ls-controls">
                    <button class="ld-btn ld-btn-outline"
                            id="ls-prev"
                            type="button">
                        ← Từ trước
                    </button>

                    <span id="ls-count" class="ls-muted"></span>

                    <button class="ld-btn ld-btn-green"
                            id="ls-next"
                            type="button">
                        Từ tiếp →
                    </button>
                </div>

                <p id="ls-note"
                   class="ls-audio-note"
                   role="status"
                   aria-live="polite"></p>
            </section>
        </div>

        <script id="ls-data" type="application/json"><?= json_encode(
            [
                'base' => $b,
                'words' => $exploreWords,
            ],
            JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
        ) ?></script>

        <script>
        (() => {
            'use strict';

            const $ = id => document.getElementById(id);
            const data = JSON.parse($('ls-data').textContent);
            const words = data.words;

            const buttons = [
                ...document.querySelectorAll('[data-word-index]')
            ];

            let index = 0;
            let audio = null;
            let version = 0;

            const synth = window.speechSynthesis;

            const text = (id, value) => {
                $(id).textContent = String(value ?? '').normalize('NFC');
            };

            function media(value) {
                if (
                    typeof value !== 'string'
                    || /[\x00-\x20<>"'\\]/.test(value)
                ) {
                    return null;
                }

                if (
                    value.startsWith('/')
                    && !value.startsWith('//')
                ) {
                    const base = data.base;

                    let path = base && !value.startsWith(base + '/')
                        ? base + value
                        : value;

                    const legacy = base + '/media/';

                    if (path.startsWith(legacy)) {
                        path = base
                            + '/assets/media/'
                            + path.slice(legacy.length);
                    }

                    return path;
                }

                try {
                    return new URL(value).protocol === 'https:'
                        ? value
                        : null;
                } catch {
                    return null;
                }
            }

            function stop() {
                version++;

                if (audio) {
                    audio.pause();
                    audio.removeAttribute('src');
                    audio.load();
                    audio = null;
                }

                synth?.cancel();
            }

            function play() {
                stop();
                text('ls-note', '');

                const current = version;
                const word = words[index];

                const active = () =>
                    current === version
                    && document.visibilityState !== 'hidden';

                const speak = () => {
                    if (!active()) {
                        return;
                    }

                    if (
                        !synth
                        || !('SpeechSynthesisUtterance' in window)
                    ) {
                        text(
                            'ls-note',
                            'Từ này cần bổ sung file âm thanh.'
                        );

                        return;
                    }

                    const utterance = new SpeechSynthesisUtterance(
                        word.english
                    );

                    const voices = synth.getVoices();

                    const voice = voices.find(
                        item => /^en(?:[-_]|$)/i.test(item.lang)
                    );

                    if (!voice && voices.length > 0) {
                        text(
                            'ls-note',
                            'Thiết bị chưa có giọng tiếng Anh cho từ này.'
                        );

                        return;
                    }

                    utterance.lang = voice?.lang || 'en-US';

                    if (voice) {
                        utterance.voice = voice;
                    }

                    utterance.rate = .9;

                    utterance.onerror = event => {
                        if (
                            active()
                            && !['canceled', 'interrupted'].includes(
                                event.error
                            )
                        ) {
                            text(
                                'ls-note',
                                'Bấm “Nghe phát âm” để nghe từ này.'
                            );
                        }
                    };

                    try {
                        synth.speak(utterance);
                    } catch {
                        text(
                            'ls-note',
                            'Bấm “Nghe phát âm” để nghe từ này.'
                        );
                    }
                };

                const src = media(word.audio);

                if (!src) {
                    speak();
                    return;
                }

                audio = new Audio(src);

                let fallback = false;

                const failed = () => {
                    if (!fallback && active()) {
                        fallback = true;
                        speak();
                    }
                };

                audio.addEventListener(
                    'error',
                    failed,
                    {once: true}
                );

                audio.play().catch(error => {
                    if (!active()) {
                        return;
                    }

                    if (error.name === 'NotAllowedError') {
                        text(
                            'ls-note',
                            'Bấm “Nghe phát âm” để bật âm thanh.'
                        );
                    } else if (error.name !== 'AbortError') {
                        failed();
                    }
                });
            }

            function show() {
                stop();

                const word = words[index];

                text('ls-word', word.english);
                text('ls-ipa', word.ipa);
                text('ls-meaning', word.vietnamese);
                text('ls-example-en', word.example);
                text('ls-example-vi', word.example_translation);

                $('ls-example').hidden =
                    !word.example
                    && !word.example_translation;

                const src = media(word.image);

                $('ls-image').hidden = !src;
                $('ls-no-image').hidden = Boolean(src);
                $('ls-image').alt = word.english;

                if (src) {
                    $('ls-image').src = src;
                } else {
                    $('ls-image').removeAttribute('src');
                }

                text(
                    'ls-count',
                    (index + 1) + ' / ' + words.length + ' từ'
                );

                $('ls-prev').disabled = index === 0;
                $('ls-next').disabled = index === words.length - 1;

                buttons.forEach((button, i) => {
                    if (i === index) {
                        button.setAttribute('aria-current', 'true');
                    } else {
                        button.removeAttribute('aria-current');
                    }
                });

                play();
            }

            $('ls-image').addEventListener('error', () => {
                $('ls-image').hidden = true;
                $('ls-no-image').hidden = false;
            });

            buttons.forEach(button => {
                button.addEventListener('click', () => {
                    index = Number(button.dataset.wordIndex);
                    show();
                });
            });

            $('ls-prev').addEventListener('click', () => {
                if (index > 0) {
                    index--;
                    show();
                }
            });

            $('ls-next').addEventListener('click', () => {
                if (index < words.length - 1) {
                    index++;
                    show();
                }
            });

            $('ls-sound').addEventListener('click', play);

            window.addEventListener('pagehide', stop);

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    stop();
                }
            });

            show();
        })();
        </script>

    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/dashboard-end.php'; ?>
