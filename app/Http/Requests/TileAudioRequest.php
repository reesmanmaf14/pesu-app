<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Saving a word's Tamil recording. Only the recording is read; any other field sent along is ignored. */
class TileAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record', $this->route('tile')) ?? false;
    }

    public function rules(): array
    {
        return [
            'ta_audio' => ['required', ...array_values(array_diff(StoreTileRequest::audioRules(), ['nullable']))],
        ];
    }

    public function messages(): array
    {
        return [
            'ta_audio.required' => 'Record the word first.',
            'ta_audio.max' => 'That recording is too long. Keep it under 10 seconds.',
            'ta_audio.mimetypes' => 'That recording could not be saved. Please record it again.',
            'ta_audio.file' => 'That recording could not be saved. Please record it again.',
        ];
    }
}
