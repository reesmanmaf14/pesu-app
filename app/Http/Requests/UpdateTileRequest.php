<?php

namespace App\Http\Requests;

class UpdateTileRequest extends StoreTileRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tile')) ?? false;
    }

    public function rules(): array
    {
        return parent::rules() + [
            'remove_photo' => ['sometimes', 'boolean'],
            'remove_audio' => ['sometimes', 'boolean'],
        ];
    }
}
