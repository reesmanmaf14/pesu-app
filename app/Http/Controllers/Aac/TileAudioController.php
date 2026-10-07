<?php

namespace App\Http\Controllers\Aac;

use App\Http\Controllers\Controller;
use App\Http\Requests\TileAudioRequest;
use App\Models\Tile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class TileAudioController extends Controller
{
    private const CONTENT_TYPES = [
        'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
    ];

    /** Plays a word's recorded Tamil voice: a built-in word's for any approved user, a custom word's for its owner. */
    public function show(Tile $tile): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('listen', $tile);

        $disk = Storage::disk(Tile::audioDisk());
        abort_unless($tile->ta_audio_path && $disk->exists($tile->ta_audio_path), 404);

        // A bucket (Laravel Cloud) has no local path: after the check above, send the browser to a
        // short-lived signed link. The bucket supports byte ranges, so Safari can play it too.
        if (config('filesystems.disks.'.Tile::audioDisk().'.driver') !== 'local') {
            return redirect()->away($disk->temporaryUrl($tile->ta_audio_path, now()->addMinutes(5)))
                ->header('Cache-Control', 'private, no-store');
        }

        // A file response (not a stream) supports byte ranges, which Safari needs to play audio.
        $type = self::CONTENT_TYPES[pathinfo($tile->ta_audio_path, PATHINFO_EXTENSION)] ?? 'application/octet-stream';

        return response()->file($disk->path($tile->ta_audio_path), [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Add or replace a word's recording. Audio only: the word's labels, category, grammar and picture
     * are never touched, so this is safe for built-in words. The new file is saved before the old one
     * is deleted, so a failed upload never loses the existing recording.
     */
    public function store(TileAudioRequest $request, Tile $tile): JsonResponse
    {
        $disk = Storage::disk(Tile::audioDisk());
        $old = $tile->ta_audio_path;
        $new = Tile::storeRecording($request->file('ta_audio'), $tile->audioDir());

        try {
            $this->setAudioPath($tile, $new);
        } catch (Throwable $e) {
            $disk->delete($new);
            throw $e;
        }

        if ($old && $old !== $new) {
            $disk->delete($old);
        }

        return response()->json(['tile' => $tile->fresh()->toAac()]);
    }

    public function destroy(Tile $tile): JsonResponse
    {
        Gate::authorize('record', $tile);

        $old = $tile->ta_audio_path;
        if ($old) {
            $this->setAudioPath($tile, null);
            Storage::disk(Tile::audioDisk())->delete($old);
        }

        return response()->json(['tile' => $tile->fresh()->toAac()]);
    }

    /** Writes only ta_audio_path (and updated_at, which changes the playback URL so browsers fetch the new file). */
    private function setAudioPath(Tile $tile, ?string $path): void
    {
        Tile::whereKey($tile->getKey())->update(['ta_audio_path' => $path]);
    }
}
