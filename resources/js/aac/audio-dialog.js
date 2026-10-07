/**
 * The therapist's audio-only dialog (resources/views/aac/partials/audio-dialog.blade.php): record, play,
 * save, replace or delete the shared Tamil recording of a built-in word. It only ever sends the
 * recording to /aac/tiles/{id}/audio, so the word's labels, category and grammar can't change.
 */
import { api } from './api';
import { VoiceRecorder, MAX_SECONDS, recordingSupport, friendlyError, audioExtension } from './recorder';

const $ = (id) => document.getElementById(id);

const STATUS = {
    idle: 'No recording yet. Tap 🎙 Record and say the word.',
    saved: '✓ Tamil voice saved. Every approved family can hear it.',
    requesting: 'Requesting microphone permission…',
    recording: 'Recording. Say the word now, then tap Stop.',
    ready: 'New recording ready. Tap ▶ Play to check it, then 💾 Save recording.',
    saving: 'Saving…',
    removing: 'Removing…',
};

const clock = (s) => {
    const n = Math.floor(s);
    return `${String(Math.floor(n / 60)).padStart(2, '0')}:${String(n % 60).padStart(2, '0')}`;
};

export class AudioDialog {
    /**
     * routes: { tileAudioStore, tileAudioDestroy } with __ID__ placeholders.
     * onChange(tile, old): the server's updated copy of the word after a save or delete.
     * confirm(text, opts): resolves true to go ahead.
     */
    constructor({ speaker, routes, onChange, confirm }) {
        this.speaker = speaker;
        this.routes = routes;
        this.onChange = onChange;
        this.confirm = confirm;
        this.tile = null;
        this.blob = null;
        this.url = null;
        this.dlg = $('audDlg');

        this.recorder = new VoiceRecorder({
            maxSeconds: MAX_SECONDS,
            onTick: (s) => ($('audTime').textContent = clock(s)),
            onStop: (r) => this.gotRecording(r),
            onError: (message) => {
                this.render(this.resting());
                $('audErr').textContent = message;
            },
        });

        $('audRec').addEventListener('click', () => this.record());
        $('audStop').addEventListener('click', () => this.recorder.stop());
        $('audPlay').addEventListener('click', () => this.speaker.speakParts([{ audio: this.url || this.tile.audio }], 'ta'));
        $('audSave').addEventListener('click', () => this.save());
        $('audDel').addEventListener('click', () => this.remove());
        $('audDone').addEventListener('click', () => this.requestClose());
        $('audClose').addEventListener('click', () => this.requestClose());
        this.dlg.addEventListener('cancel', (e) => {
            e.preventDefault();
            this.requestClose();
        });
        this.dlg.addEventListener('close', () => {
            this.recorder.cancel();
            this.speaker.cancel();
            this.discard();
        });
    }

    open(tile) {
        this.tile = tile;
        this.discard();
        $('audWord').textContent = [tile.e, tile.en, tile.ta && tile.ta !== tile.en ? `· ${tile.ta}` : ''].filter(Boolean).join(' ');
        $('audErr').textContent = '';
        this.render(this.resting());
        this.dlg.showModal();
        $('audRec').focus();
    }

    resting() {
        return this.blob ? 'ready' : this.tile?.audio ? 'saved' : 'idle';
    }

    render(state, note = '') {
        const support = recordingSupport();
        const busy = ['recording', 'saving', 'removing'].includes(state);
        const hasSound = state === 'ready' || state === 'saved';

        $('audRec').hidden = busy;
        $('audRec').disabled = support !== 'ok' || state === 'requesting';
        $('audRec').textContent = hasSound ? '🎙 Record again' : '🎙 Record';
        $('audStop').hidden = state !== 'recording';
        $('audPlay').hidden = !hasSound;
        $('audSave').hidden = state !== 'ready';
        $('audDel').hidden = !hasSound;
        $('audDone').disabled = busy;
        $('audTimer').hidden = state !== 'recording';
        $('audStatus').textContent = note || STATUS[state];

        if (support !== 'ok') {
            $('audErr').textContent =
                support === 'insecure'
                    ? 'Voice recording needs a secure connection (https, or localhost on this computer).'
                    : 'Voice recording is not supported by this browser.';
        }
    }

    async record() {
        $('audErr').textContent = '';
        this.speaker.cancel();
        this.render('requesting');
        try {
            await this.recorder.start();
            this.render('recording');
            $('audStop').focus();
        } catch (err) {
            this.render(this.resting());
            $('audErr').textContent = friendlyError(err);
        }
    }

    gotRecording({ blob, hitLimit }) {
        if (!blob.size) {
            this.render(this.resting());
            $('audErr').textContent = 'Nothing was recorded. Please try again.';
            return;
        }
        this.discard();
        this.blob = blob;
        this.url = URL.createObjectURL(blob);
        this.render('ready', hitLimit ? `Recording stopped at the ${MAX_SECONDS}-second limit. Tap ▶ Play to check it, then 💾 Save recording.` : '');
        $('audPlay').focus();
    }

    /** Forget a new recording that hasn't been saved. */
    discard() {
        if (this.url) URL.revokeObjectURL(this.url);
        this.blob = null;
        this.url = null;
    }

    async save() {
        if (!this.blob) return;
        const fd = new FormData();
        fd.append('ta_audio', this.blob, `voice.${audioExtension(this.blob.type)}`);
        $('audErr').textContent = '';
        this.render('saving');
        try {
            const { tile } = await api.form(this.routes.tileAudioStore.replace('__ID__', this.tile.id), fd);
            this.updated(tile);
            this.discard();
            this.render('saved');
        } catch (err) {
            this.render('ready');
            $('audErr').textContent = err.errors ? Object.values(err.errors)[0][0] : err.message;
        }
    }

    async remove() {
        this.speaker.cancel();
        if (this.blob) {
            // Only the new, unsaved recording is thrown away; the saved one stays.
            this.discard();
            this.render(this.resting(), 'New recording deleted.');
            return;
        }
        if (!this.tile.audio || !(await this.confirm('Delete the saved Tamil recording of this word for everyone?'))) return;

        $('audErr').textContent = '';
        this.render('removing');
        try {
            const { tile } = await api.del(this.routes.tileAudioDestroy.replace('__ID__', this.tile.id));
            this.updated(tile);
            this.render('idle', 'Recording deleted.');
        } catch (err) {
            this.render(this.resting());
            $('audErr').textContent = err.message;
        }
    }

    updated(tile) {
        const old = this.tile;
        this.tile = tile;
        this.onChange?.(tile, old);
    }

    async requestClose() {
        if (this.recorder.recording || this.blob) {
            if (!(await this.confirm('Discard the new recording? It has not been saved.', { ok: 'Discard', cancel: 'Keep it' }))) return;
        }
        this.dlg.close();
    }
}
