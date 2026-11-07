<style>
.review-shell {
    max-width: 1160px;
    margin: 0 auto;
    font-size: 16px;
}

.review-shell [hidden] {
    display: none !important;
}

.review-shell p {
    line-height: 1.7;
}

.review-shell :is(button, select) {
    font: inherit;
}

.review-shell :is(button, a, select):focus-visible {
    outline: 3px solid var(--dq-blue);
    outline-offset: 4px;
}

.review-shell button {
    cursor: pointer;
}

.review-shell button:disabled {
    opacity: .55;
    cursor: wait;
}

.review-shell .review-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 28px;
    padding: 32px;
    border: 2px solid var(--theme-line);
    border-radius: 24px;
    background: var(--theme-soft);
}

.review-shell .eyebrow,
.review-shell .guide-kicker {
    color: var(--theme-good);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
    margin: 0 0 12px;
}

.review-shell h1 {
    font-size: clamp(28px, 3vw, 40px);
    line-height: 1.2;
}

.review-shell .hero-description {
    max-width: 620px;
    margin: 16px 0;
    color: var(--theme-muted);
}

.review-shell .muted {
    color: var(--theme-muted);
    font-size: 14px;
}

.review-shell .hero-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
}

.review-shell .review-hero-icon {
    display: grid;
    place-items: center;
    flex: 0 0 144px;
    height: 144px;
    background: var(--theme-selected);
    color: var(--theme-good);
    border-radius: 36px;
}

.review-shell .review-hero-icon .ld-icon {
    width: 76px;
    height: 76px;
}

.review-shell .review-benefits {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin: 22px 0 28px;
}

.review-shell :is(.benefit-card, .guide-card, .panel) {
    border: 2px solid var(--theme-line);
    border-radius: 20px;
    background: var(--theme-surface);
    color: var(--theme-ink);
}

.review-shell .benefit-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 18px;
}

.review-shell :is(.benefit-number, .level-badge) {
    display: grid;
    place-items: center;
    flex: 0 0 42px;
    min-height: 40px;
    padding: 4px;
    border-radius: 12px;
    background: var(--theme-good-bg);
    color: var(--theme-good);
    font-size: 13px;
    font-weight: 800;
}

.review-shell .benefit-card strong {
    font-size: 15px;
}

.review-shell .benefit-card p {
    color: var(--theme-muted);
    font-size: 13px;
    margin-top: 4px;
}

.review-shell .review-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 280px;
    align-items: start;
    gap: 24px;
}

.review-shell .review-content {
    min-width: 0;
}

.review-shell .review-tabs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 18px;
    scrollbar-width: thin;
}

.review-shell .review-tabs button {
    flex-shrink: 0;
    min-height: 46px;
    padding: 10px 16px;
    border: 2px solid transparent;
    border-radius: 13px;
    background: var(--theme-soft);
    color: var(--theme-muted);
    font-size: 14px;
    font-weight: 700;
}

.review-shell .review-tabs button.active {
    background: var(--theme-selected);
    border-color: var(--dq-green);
    color: var(--theme-good);
}

.review-shell .review-tabs b {
    display: inline-block;
    min-width: 24px;
    margin-left: 6px;
    padding: 1px 6px;
    border-radius: 6px;
    background: var(--theme-good-bg);
    color: var(--theme-good);
}

.review-shell .toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.review-shell .toolbar label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}

.review-shell select {
    max-width: 100%;
    min-height: 44px;
    padding: 8px 12px;
    border: 2px solid var(--theme-line);
    border-radius: 12px;
    background: var(--theme-surface);
    color: var(--theme-ink);
}

.review-shell .button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 11px 18px;
    border: 2px solid var(--dq-green);
    border-radius: 13px;
    background: var(--dq-green);
    color: var(--dq-on-green);
    box-shadow: 0 3px 0 var(--dq-green-edge);
    font-size: 14px;
    font-weight: 750;
    text-align: center;
    text-decoration: none;
}

.review-shell .button.secondary {
    border-color: var(--theme-line);
    background: var(--theme-surface);
    color: var(--theme-ink);
    box-shadow: 0 3px 0 var(--theme-line);
}

.review-shell .button:hover {
    filter: brightness(.96);
}

.review-shell .button:active {
    transform: translateY(2px);
    box-shadow: none;
}

.review-shell .panel {
    padding: 24px;
}

.review-shell .empty {
    padding: 52px 24px;
    text-align: center;
    color: var(--theme-muted);
    line-height: 1.8;
}

.review-shell .empty strong {
    display: block;
    margin-bottom: 10px;
    font-size: 21px;
    color: var(--theme-ink);
}

.review-shell .list {
    display: grid;
    gap: 14px;
}

.review-shell .row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 18px;
    border: 1px solid var(--theme-line);
    border-radius: 15px;
}

.review-shell .word-text {
    flex: 1 1 190px;
    min-width: 0;
    overflow-wrap: anywhere;
}

.review-shell .word-text h3 {
    font-size: 23px;
    margin-bottom: 8px;
}

.review-shell .word-text p {
    font-size: 14px;
    color: var(--theme-muted);
}

.review-shell .due-tag {
    padding: 5px 9px;
    border-radius: 8px;
    background: var(--theme-good-bg);
    color: var(--theme-good);
    font-size: 12px;
    font-weight: 700;
}

.review-shell .message {
    padding: 16px 20px;
    margin: 0 0 20px;
    border: 1px solid var(--theme-line);
    border-radius: 14px;
    background: var(--theme-good-bg);
    color: var(--theme-good);
    line-height: 1.7;
}

.review-shell .message.error {
    background: var(--theme-bad-bg);
    color: var(--theme-bad);
    border-color: var(--theme-bad);
}

.review-shell .card-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
    color: var(--theme-muted);
    font-size: 13px;
}

.review-shell .flashcard {
    padding: 28px 18px;
    border: 1px solid var(--theme-line);
    border-radius: 18px;
    text-align: center;
}

.review-shell .flashcard h3 {
    margin: 18px 0;
    font-size: clamp(27px, 3vw, 38px);
    overflow-wrap: anywhere;
}

.review-shell .flashcard img {
    max-width: 100%;
    max-height: 210px;
    object-fit: contain;
    margin: 0 auto 18px;
}

.review-shell .flashcard .back {
    margin-top: 24px;
    padding-top: 22px;
    border-top: 1px solid var(--theme-line);
    color: var(--theme-good);
    font-size: 25px;
    overflow-wrap: anywhere;
}

.review-shell .flashcard p {
    margin-top: 16px;
    color: var(--theme-muted);
    line-height: 1.9;
    overflow-wrap: anywhere;
}

.review-shell .audio-button {
    padding: 11px 16px;
    border: 1px solid var(--theme-line);
    border-radius: 12px;
    background: var(--theme-soft);
    color: var(--dq-blue);
}

.review-shell .card-actions {
    margin-top: 22px;
    text-align: center;
}

.review-shell .ratings {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 22px;
}

.review-shell .rating {
    padding: 14px 8px;
    border: 2px solid var(--theme-line);
    border-radius: 13px;
    background: var(--theme-surface);
    color: var(--theme-ink);
    font-size: 13px;
}

.review-shell .rating strong {
    display: block;
    margin-bottom: 6px;
    color: var(--theme-good);
    font-size: 24px;
}

.review-shell .rating.low strong {
    color: var(--theme-bad);
}

.review-shell .rating:hover {
    border-color: var(--theme-good);
    background: var(--theme-good-bg);
}

.review-shell .rating.low:hover {
    border-color: var(--theme-bad);
    background: var(--theme-bad-bg);
}

.review-shell .small-note {
    margin-top: 16px;
    color: var(--theme-muted);
    font-size: 13px;
    line-height: 1.7;
}

.review-shell .more {
    margin-top: 22px;
    text-align: center;
}

.review-shell :is(.history-item, .notification) {
    padding: 18px 0;
    border-bottom: 1px solid var(--theme-line);
    overflow-wrap: anywhere;
}

.review-shell :is(.history-item, .notification):last-child {
    border-bottom: 0;
}

.review-shell .history-item h3 {
    font-size: 18px;
    margin-bottom: 8px;
}

.review-shell :is(.history-item p, .notification p, .notification small) {
    color: var(--theme-muted);
    line-height: 1.8;
    font-size: 14px;
}

.review-shell .notification.unread {
    padding-left: 16px;
    border-left: 3px solid var(--dq-green);
}

.review-shell .notification small {
    display: block;
    margin-top: 6px;
}

.review-shell .notification .button {
    margin-top: 12px;
}

.review-shell .review-guide {
    display: grid;
    gap: 18px;
}

.review-shell .guide-card {
    padding: 22px;
}

.review-shell .guide-card h2 {
    margin-bottom: 18px;
    font-size: 19px;
}

.review-shell .guide-steps {
    padding-left: 20px;
    margin: 0;
    color: var(--theme-muted);
    font-size: 14px;
    line-height: 1.8;
}

.review-shell .guide-steps li + li {
    margin-top: 12px;
}

.review-shell .guide-steps li::marker {
    color: var(--theme-good);
    font-weight: 800;
}

.review-shell .memory-level {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 18px;
}

.review-shell .memory-level strong {
    font-size: 14px;
}

.review-shell .memory-level p,
.review-shell .guide-note {
    margin-top: 4px;
    color: var(--theme-muted);
    font-size: 13px;
    line-height: 1.8;
}

.review-shell .level-low {
    background: var(--theme-bad-bg);
    color: var(--theme-bad);
}

.review-shell .guide-note {
    padding-top: 16px;
    border-top: 1px dashed var(--theme-line);
}

.review-shell footer {
    margin-top: 28px;
    text-align: center;
    color: var(--theme-muted);
    font-size: 13px;
}

@media (max-width: 1200px) {
    .review-shell .review-workspace {
        grid-template-columns: minmax(0, 1fr);
    }

    .review-shell .review-guide {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 680px) {
    .review-shell .review-hero {
        padding: 24px 20px;
    }

    .review-shell .review-hero-icon {
        display: none;
    }

    .review-shell .review-benefits,
    .review-shell .review-guide {
        grid-template-columns: minmax(0, 1fr);
    }

    .review-shell .panel {
        padding: 18px;
    }

    .review-shell .toolbar {
        flex-wrap: wrap;
    }

    .review-shell .review-tabs button {
        padding: 10px 12px;
    }

    .review-shell .ratings {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .review-shell .row .button {
        width: 100%;
    }
}
</style>