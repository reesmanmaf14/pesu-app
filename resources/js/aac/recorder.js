/**
 * Records a short Tamil pronunciation with the browser's MediaRecorder API.
 * Browsers record different formats, so the first supported type below is used and the
 * server works out the real type when the file is uploaded.
 */

/** Longest recording allowed for one word, in seconds. Recording stops by itself here. */
export const MAX_SECONDS = 10;

const TYPES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg'];

export function pickMimeType() {
    if (typeof MediaRecorder === 'undefined' || !MediaRecorder.isTypeSupported) return '';
    return TYPES.find((t) => MediaRecorder.isTypeSupported(t)) || '';
}

/** 'ok', 'insecure' (microphones need https or localhost) or 'unsupported'. */
export function recordingSupport() {
    if (!window.isSecureContext) return 'insecure';
    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') return 'unsupported';
    return 'ok';
}

/** File extension for an uploaded recording, from its MIME type. */
export function audioExtension(type) {
    if (/mp4|aac|m4a/.test(type)) return 'm4a';
    if (/ogg/.test(type)) return 'ogg';
    return 'webm';
}

/** A message that is safe to show; raw exceptions are never shown to the user. */
export function friendlyError(err) {
    switch (err?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Microphone permission was denied. Please allow microphone access in your browser settings.';
        case 'NotFoundError':
        case 'OverconstrainedError':
            return 'No microphone was detected.';
        case 'NotReadableError':
        case 'AbortError':
            return 'The microphone could not be started. Close other apps that use it and try again.';
        case 'NotSupportedError':
            return 'Voice recording is not supported by this browser.';
        default:
            return 'Something went wrong while recording. Please try again.';
    }
}

export class VoiceRecorder {
    /**
     * onTick(seconds) while recording; onStop({ blob, seconds, hitLimit }) when a recording
     * is ready; onError(message) if recording fails part-way.
     */
    constructor({ maxSeconds = MAX_SECONDS, onTick, onStop, onError } = {}) {
        this.maxSeconds = maxSeconds;
        this.onTick = onTick;
        this.onStop = onStop;
        this.onError = onError;
        this.rec = null;
        this.stream = null;
        this.timer = null;
    }

    get recording() {
        return this.rec?.state === 'recording';
    }

    /** Asks for the microphone (first time) and starts recording. Rejects with the browser's error. */
    async start() {
        if (recordingSupport() !== 'ok') {
            const e = new Error('unsupported');
            e.name = 'NotSupportedError';
            throw e;
        }
        this.cancel();

        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const type = pickMimeType();
        let rec;
        try {
            rec = new MediaRecorder(stream, type ? { mimeType: type } : undefined);
        } catch (e) {
            stream.getTracks().forEach((t) => t.stop());
            throw e;
        }

        const chunks = [];
        const started = Date.now();
        const elapsed = () => Math.min((Date.now() - started) / 1000, this.maxSeconds);
        let hitLimit = false;

        rec.ondataavailable = (e) => {
            if (e.data && e.data.size) chunks.push(e.data);
        };
        rec.onstop = () => {
            this.release();
            if (rec.cancelled) return;
            const blob = new Blob(chunks, { type: rec.mimeType || type || 'audio/webm' });
            this.onStop?.({ blob, seconds: elapsed(), hitLimit });
        };
        rec.onerror = () => {
            rec.cancelled = true;
            this.release();
            this.onError?.(friendlyError(null));
        };

        this.stream = stream;
        this.rec = rec;
        this.stopNow = (limit) => {
            hitLimit = limit;
            if (rec.state !== 'inactive') rec.stop();
        };
        // Small timeslices so audio is kept even if the browser stops recording abruptly.
        rec.start(250);
        this.onTick?.(0);

        this.timer = setInterval(() => {
            const s = elapsed();
            this.onTick?.(s);
            // The limit stops the recording but keeps it.
            if (s >= this.maxSeconds) this.stopNow(true);
        }, 250);
    }

    stop() {
        this.stopNow?.(false);
    }

    /** Stops without keeping anything (e.g. the dialog was closed). */
    cancel() {
        if (this.rec && this.rec.state !== 'inactive') {
            this.rec.cancelled = true;
            this.rec.stop();
        }
        this.release();
    }

    release() {
        clearInterval(this.timer);
        this.timer = null;
        this.stream?.getTracks().forEach((t) => t.stop());
        this.stream = null;
        this.stopNow = null;
    }
}
