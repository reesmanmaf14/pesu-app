<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTileRequest extends FormRequest
{
    /**
     * Types a recorded voice may arrive as. MediaRecorder output is often detected as video/*
     * (WebM/MP4 containers), so those are accepted too; the server picks the stored extension.
     */
    public const AUDIO_TYPES = [
        'audio/webm' => 'webm', 'video/webm' => 'webm',
        'audio/ogg' => 'ogg', 'video/ogg' => 'ogg', 'application/ogg' => 'ogg',
        'audio/mp4' => 'm4a', 'video/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'm4a',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/wave' => 'wav',
    ];

    public const AUDIO_MAX_KB = 1024;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Only built-in categories or the user's own; someone else's category is "invalid".
            'category_id' => [
                'required', 'integer',
                Rule::exists('categories', 'id')->where(
                    fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', $this->user()->id)
                ),
            ],
            'label_en' => ['nullable', 'string', 'max:120', 'required_without:label_ta'],
            'label_ta' => ['nullable', 'string', 'max:120', 'required_without:label_en'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'ta_dative' => ['nullable', 'string', 'max:120'],
            'ta_infinitive' => ['nullable', 'string', 'max:120'],
            'ta_audio' => self::audioRules(),
        ];
    }

    public static function audioRules(): array
    {
        return ['nullable', 'file', 'max:'.self::AUDIO_MAX_KB, 'mimetypes:'.implode(',', array_keys(self::AUDIO_TYPES))];
    }

    public function messages(): array
    {
        return [
            'label_en.required_without' => 'Type the word in English, Tamil, or both.',
            'label_ta.required_without' => 'Type the word in English, Tamil, or both.',
            'category_id.required' => 'Choose a category.',
            'category_id.exists' => 'Choose one of your categories.',
            'photo.max' => 'That photo is too large. Choose one under 2 MB.',
            'ta_audio.max' => 'That recording is too long. Keep it under 10 seconds.',
            'ta_audio.mimetypes' => 'That recording could not be saved. Please record it again.',
            'ta_audio.file' => 'That recording could not be saved. Please record it again.',
        ];
    }
}
