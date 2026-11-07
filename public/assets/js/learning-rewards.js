/* Presentation only: scores, chapter passes and streak totals come from the server. */
(() => {
    'use strict';
    const seen = new Set();
    const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
    const integer = value => Math.max(0, Math.trunc(Number(value) || 0));

    function celebrate(key) {
        if (!key || seen.has(key) || document.hidden) return;
        seen.add(key);
        window.DLFeedbackSound?.play(true, {key: 'reward:' + key});
        if (reduced()) return;

        const layer = document.createElement('div');
        layer.className = 'lr-fireworks';
        layer.setAttribute('aria-hidden', 'true');
        const colors = ['#ffcf4a', '#54c7f2', '#9bd52c', '#ff8db6', '#ff9f43'];
        [[22, 25], [78, 25], [38, 42], [65, 42]].forEach(([x, y], burst) => {
            for (let ray = 0; ray < 14; ray++) {
                const dot = document.createElement('i');
                const angle = ray * Math.PI * 2 / 14;
                const distance = 55 + Math.random() * 65;
                dot.style.cssText = `left:${x}%;top:${y}%;--dx:${Math.cos(angle) * distance}px;--dy:${Math.sin(angle) * distance}px;--delay:${burst * .18}s;background:${colors[(ray + burst) % colors.length]}`;
                layer.append(dot);
            }
        });
        document.body.append(layer);
        setTimeout(() => layer.remove(), 1900);
    }

    function outcome(root, passed, {key = '', fresh = false} = {}) {
        if (!root) return;
        root.classList.add('lr-outcome');
        root.classList.toggle('is-pass', passed === true);
        root.classList.toggle('is-fail', passed !== true);
        if (!root.querySelector('.lr-fail-symbol')) {
            const symbol = document.createElement('div');
            symbol.className = 'lr-fail-symbol';
            symbol.textContent = '×';
            symbol.setAttribute('aria-hidden', 'true');
            root.prepend(symbol);
        }
        if (passed === true && fresh) celebrate(key);
    }

    function streak(root, {current = 0, previous = null, key = '', guest = false} = {}) {
        if (!root) return;
        const value = integer(current);
        const marker = key + ':' + value + ':' + guest;
        if (root.dataset.rewardStreak === marker) return;
        root.dataset.rewardStreak = marker;
        root.className = 'lr-streak';
        root.replaceChildren();
        if (guest) {
            root.classList.add('is-guest');
            root.textContent = 'Đăng nhập để lưu chuỗi ngày học của bạn.';
            return;
        }
        const before = previous === null ? value : integer(previous);
        const increasing = value > before;
        root.classList.toggle('is-empty', value === 0);
        root.classList.toggle('is-increasing', increasing && !reduced());
        root.setAttribute('role', 'status');
        root.setAttribute('aria-label', value + ' ngày học liên tiếp');

        const flame = document.createElement('span');
        flame.className = 'lr-flame';
        flame.setAttribute('aria-hidden', 'true');
        flame.innerHTML = '<svg viewBox="0 0 64 80"><path class="lr-flame-outer" d="M35 2c3 18 20 22 24 42 5 22-9 34-27 34S1 65 5 47c2-11 9-17 14-25-1 12 2 16 7 18C20 22 32 16 35 2Z"/><path class="lr-flame-inner" d="M34 39c1 10 12 14 12 24 0 8-6 13-14 13S18 71 19 63c1-9 10-14 15-24Z"/></svg>';
        const copy = document.createElement('div');
        const numbers = document.createElement('span');
        numbers.className = 'lr-number';
        if (increasing && !reduced()) {
            const old = document.createElement('span');
            old.className = 'lr-number-old';
            old.textContent = before;
            old.setAttribute('aria-hidden', 'true');
            numbers.append(old);
            setTimeout(() => old.remove(), 800);
        }
        const count = document.createElement('strong');
        count.className = 'lr-number-new';
        count.textContent = value;
        numbers.append(count);
        const label = document.createElement('span');
        label.className = 'lr-streak-label';
        label.textContent = 'ngày học liên tiếp';
        copy.append(numbers, label);
        root.append(flame, copy);
        if (increasing) {
            const note = document.createElement('small');
            note.textContent = 'Thêm một ngày giữ nhịp học!';
            root.append(note);
        }
    }
    window.DLRewards = {celebrate, outcome, streak};
})();
