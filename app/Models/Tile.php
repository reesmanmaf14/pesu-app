<?php

namespace App\Models;

use App\Http\Requests\StoreTileRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Tile extends Model
{
    public const AUDIO_DIR = 'aac/audio';

    /** The therapist's recordings of built-in words: not tied to any account, so deleting one never removes them. */
    public const SHARED_AUDIO_DIR = 'aac/audio/shared';

    protected $fillable = [
        'user_id', 'category_id', 'is_core', 'kind', 'emoji', 'image_path', 'ta_audio_path',
        'label_en', 'label_ta', 'ta_dative', 'ta_infinitive', 'en_to', 'en_infinitive',
        'en_article', 'frame_key', 'template_en', 'template_ta', 'accepts', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'en_article' => 'boolean',
            'accepts' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Built-in tiles plus the ones this user added. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    public function isCustom(): bool
    {
        return $this->user_id !== null;
    }

    /** A built-in word every approved user sees; only the therapist records its Tamil voice. */
    public function isShared(): bool
    {
        return ! $this->isCustom();
    }

    /** Folder for this word's recording: the shared folder for built-in words, else the owner's folder. */
    public function audioDir(): string
    {
        return $this->isShared() ? self::SHARED_AUDIO_DIR : self::AUDIO_DIR.'/'.$this->user_id;
    }

    /**
     * Store a recording in $dir with a random name. The extension comes from the
     * server-detected type, never from the browser's filename.
     */
    public static function storeRecording(UploadedFile $file, string $dir): string
    {
        $ext = StoreTileRequest::AUDIO_TYPES[$file->getMimeType()] ?? 'webm';

        return $file->storeAs($dir, Str::random(40).'.'.$ext, self::audioDisk());
    }

    /** Disk for recordings (private: only served through TileAudioController). See config/pesu.php. */
    public static function audioDisk(): string
    {
        return config('pesu.audio_disk');
    }

    /** Disk for word photos (public URLs). See config/pesu.php. */
    public static function photoDisk(): string
    {
        return config('pesu.photo_disk');
    }

    /** Remove the photo and recording files that belong to this tile. */
    public function deleteFiles(): void
    {
        if ($this->image_path) {
            Storage::disk(self::photoDisk())->delete($this->image_path);
        }
        if ($this->ta_audio_path) {
            Storage::disk(self::audioDisk())->delete($this->ta_audio_path);
        }
    }

    /** Compact shape consumed by resources/js/aac (keys match grammar.js). */
    public function toAac(): array
    {
        return [
            'id' => $this->id,
            'k' => $this->kind,
            'e' => $this->emoji,
            'img' => $this->image_path ? Storage::disk(self::photoDisk())->url($this->image_path) : null,
            // Version query so a re-recorded word isn't served from the browser cache.
            'audio' => $this->ta_audio_path
                ? route('aac.tiles.audio', ['tile' => $this->id, 'v' => $this->updated_at?->timestamp])
                : null,
            'cat' => $this->category_id,
            'en' => $this->label_en,
            'ta' => $this->label_ta,
            'dat' => $this->ta_dative,
            'inf' => $this->ta_infinitive,
            'to' => $this->en_to,
            'enInf' => $this->en_infinitive,
            'the' => $this->en_article,
            'frame' => $this->frame_key ? [
                'key' => $this->frame_key,
                'en' => $this->template_en,
                'ta' => $this->template_ta,
                'accepts' => $this->accepts ?? [],
            ] : null,
            'custom' => $this->isCustom(),
        ];
    }
}
