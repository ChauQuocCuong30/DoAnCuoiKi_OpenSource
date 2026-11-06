/* Original short game cues; synthesized locally, with no audio download. */
(() => {
    'use strict';
    let context = null, generation = 0;
    const voices = new Set(), played = new Set();
    function unlock() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return Promise.resolve(false);
            context ??= new AudioContext();
            return Promise.resolve(context.state === 'suspended' ? context.resume() : undefined)
                .then(() => context.state === 'running').catch(() => false);
        } catch { return Promise.resolve(false); }
    }
    function stop() {
        generation++;
        for (const node of voices) { try { node.stop(); } catch {} }
        voices.clear();
    }
    async function play(correct, {key = ''} = {}) {
        if (typeof correct !== 'boolean' || document.hidden || (key && played.has(key))) return false;
        stop();
        const ticket = generation;
        if (!await unlock() || ticket !== generation || document.hidden || (key && played.has(key))) return false;
        if (key) {
            played.add(key);
            if (played.size > 128) played.delete(played.values().next().value);
        }
        // Ascending clear chime / lower descending double tone, both at a gentle volume.
        const notes = correct ? [[659.25, 0, .13], [880, .09, .14], [1046.5, .18, .20]]
            : [[246.94, 0, .17], [196, .12, .22]];
        const start = context.currentTime + .008;
        for (const [frequency, offset, duration] of notes) {
            const oscillator = context.createOscillator(), gain = context.createGain(), at = start + offset;
            oscillator.type = correct ? 'sine' : 'triangle';
            oscillator.frequency.setValueAtTime(frequency, at);
            gain.gain.setValueAtTime(0, at);
            gain.gain.linearRampToValueAtTime(correct ? .075 : .045, at + .012);
            gain.gain.exponentialRampToValueAtTime(.0001, at + duration);
            oscillator.connect(gain); gain.connect(context.destination);
            oscillator.onended = () => { voices.delete(oscillator); oscillator.disconnect(); gain.disconnect(); };
            voices.add(oscillator); oscillator.start(at); oscillator.stop(at + duration + .015);
        }
        return true;
    }
    // Resume while a user gesture is active, before the asynchronous grading request.
    document.addEventListener('pointerdown', unlock, {passive:true});
    document.addEventListener('keydown', unlock);
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
    window.addEventListener('pagehide', stop);
    window.DLFeedbackSound = {unlock, play, stop};
})();
