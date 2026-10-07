/**
 * Thin wrapper over the browser's Web Speech API, plus playback of recorded words.
 * Tamil voices depend on the device: Android (Google Speech Services) and Windows usually
 * offer one; some devices have none. In Tamil, recordings fill the gaps (see speakTamil):
 * built-in words use the device's Tamil voice first and the therapist's recording when there
 * is none; a parent's own recorded word plays its recording first.
 *
 * Only one thing is ever heard at a time: every new speak/speakParts call stops whatever
 * recording or synthesised speech is still going.
 */
export class Speaker {
    constructor() {
        this.supported = 'speechSynthesis' in window;
        this.voices = [];
        // One reused element: once a tap has let it play, mobile browsers keep allowing it.
        this.audio = typeof Audio === 'undefined' ? null : new Audio();
        this.run = 0; // bumped by cancel() so a sequence that is still playing stops
        this.ending = null;
        this.voiceWaiters = [];
        this.waitedForVoices = false;
    }

    init(onVoicesChanged) {
        if (!this.supported) return;
        const load = () => {
            this.voices = window.speechSynthesis.getVoices();
            if (this.voices.length) this.voiceWaiters.splice(0).forEach((done) => done());
            onVoicesChanged?.();
        };
        load();
        window.speechSynthesis.onvoiceschanged = load;
    }

    /**
     * Whether a voice for `lang` is installed. Browsers often fill the voice list a moment after the
     * page loads, so the first call waits up to `ms` for it. Only an installed voice counts: without one,
     * some devices would read Tamil text with an English voice, or stay silent.
     */
    async hasVoice(lang, voiceURI = null, ms = 1000) {
        if (!this.supported) return false;
        if (!this.voices.length) this.voices = window.speechSynthesis.getVoices();
        if (!this.voices.length && !this.waitedForVoices) {
            this.waitedForVoices = true;
            await new Promise((resolve) => {
                const timer = setTimeout(resolve, ms);
                this.voiceWaiters.push(() => {
                    clearTimeout(timer);
                    resolve();
                });
            });
        }
        return !!this.pick(lang, voiceURI);
    }

    voicesFor(lang) {
        return this.voices.filter((v) => (v.lang || '').toLowerCase().startsWith(lang));
    }

    pick(lang, voiceURI) {
        const list = this.voicesFor(lang);
        return (
            list.find((v) => v.voiceURI === voiceURI) ||
            (lang === 'en'
                ? list.find((v) => /en[-_]IN/i.test(v.lang)) || list.find((v) => /en[-_]GB/i.test(v.lang))
                : null) ||
            list[0] ||
            null
        );
    }

    speak(text, lang, opts = {}) {
        if (!text) return;
        this.speakParts([{ text }], lang, opts);
    }

    /**
     * Say parts one after another. Tamil follows speakTamil(). Otherwise (English) a part with
     * `audio` plays that recording, falling back to its `text` through speech synthesis if the
     * file can't be played; a part with only `text` is synthesised.
     * onUnspoken(texts) is called (Tamil only) with what could not be said at all.
     */
    async speakParts(parts, lang, { rate = 1, voiceURI = null, onStart, onEnd, onUnspoken } = {}) {
        this.cancel();
        const todo = parts.filter((p) => p.audio || p.text);
        if (!todo.length) return;

        const run = this.run;
        this.ending = onEnd ?? null;
        onStart?.();

        if (lang === 'ta') {
            await this.speakTamil(todo, run, rate, voiceURI, onUnspoken);
        } else {
            for (const p of todo) {
                if (run !== this.run) return;
                const played = p.audio ? await this.play(p.audio) : false;
                if (!played && p.text && run === this.run) await this.say(p.text, lang, rate, voiceURI);
            }
        }

        if (run === this.run) this.finish();
    }

    /**
     * Tamil order, per part:
     *  - a parent's own recorded word (`audio`, not `shared`): its recording, then the Tamil voice;
     *  - anything else (built-in words, sentence starters, typed text): the Tamil voice, then the
     *    therapist's recording if the word has one.
     * Neighbouring voice-first parts are said together, as one sentence, like before.
     * Whatever can't be said either way goes to onUnspoken, so the user sees a message, not silence.
     */
    async speakTamil(parts, run, rate, voiceURI, onUnspoken) {
        const live = () => run === this.run;
        let voice = await this.hasVoice('ta', voiceURI);
        const unspoken = [];

        for (let i = 0; i < parts.length && live(); ) {
            const p = parts[i];

            if (p.audio && !p.shared) {
                i++;
                let ok = await this.play(p.audio);
                if (!ok && live() && voice && p.text) ok = await this.say(p.text, 'ta', rate, voiceURI);
                if (!ok && live() && p.text) unspoken.push(p.text);
                continue;
            }

            let j = i;
            while (j < parts.length && !(parts[j].audio && !parts[j].shared)) j++;
            const group = parts.slice(i, j);
            i = j;

            const text = group.map((g) => g.text).filter(Boolean).join(' ');
            if (voice && text) {
                if (await this.say(text, 'ta', rate, voiceURI)) continue;
                voice = false; // the voice failed (e.g. an online voice while offline): use recordings from here on
            }
            for (const g of group) {
                if (!live()) break;
                const ok = g.audio ? await this.play(g.audio) : false;
                if (!ok && live() && g.text) unspoken.push(g.text);
            }
        }

        if (live() && unspoken.length) onUnspoken?.(unspoken);
    }

    cancel() {
        this.run++;
        if (this.supported) window.speechSynthesis.cancel();
        if (this.audio && !this.audio.paused) this.audio.pause();
        this.finish();
    }

    finish() {
        const end = this.ending;
        this.ending = null;
        end?.();
    }

    /** Resolves true when the recording played (or was stopped), false if it couldn't play. */
    play(url) {
        const a = this.audio;
        if (!a) return Promise.resolve(false);

        return new Promise((resolve) => {
            const done = (ok) => {
                a.onended = a.onerror = a.onpause = null;
                resolve(ok);
            };
            a.onended = () => done(true);
            a.onerror = () => done(false);
            a.onpause = () => {
                if (!a.ended) done(true); // stopped by cancel()
            };
            a.src = url;
            a.play().catch(() => done(false));
        });
    }

    /** Resolves true when spoken (or stopped by cancel()), false if the browser reports it failed. */
    say(text, lang, rate, voiceURI) {
        if (!this.supported) return Promise.resolve(false);

        return new Promise((resolve) => {
            const u = new SpeechSynthesisUtterance(text);
            const voice = this.pick(lang, voiceURI);
            u.lang = voice ? voice.lang : lang === 'ta' ? 'ta-IN' : 'en-IN';
            if (voice) u.voice = voice;
            u.rate = rate;
            u.onend = () => resolve(true);
            // "interrupted"/"canceled" mean cancel() stopped it on purpose, not that the voice failed.
            u.onerror = (e) => resolve(e.error === 'interrupted' || e.error === 'canceled');
            window.speechSynthesis.speak(u);
        });
    }
}
