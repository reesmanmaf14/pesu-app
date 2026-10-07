<?php

namespace App\Http\Controllers\Aac;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lang' => ['sometimes', 'in:en,ta'],
            'show_both' => ['sometimes', 'boolean'],
            'speak_each' => ['sometimes', 'boolean'],
            'rate' => ['sometimes', 'numeric', 'between:0.5,1.5'],
            'cols' => ['sometimes', 'integer', 'in:3,4,5,6,8'],
            'voice_en' => ['sometimes', 'nullable', 'string', 'max:255'],
            'voice_ta' => ['sometimes', 'nullable', 'string', 'max:255'],
            'log_usage' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->aac_settings = array_merge($user->aacSettings(), $data);
        $user->save();

        return response()->json(['settings' => $user->aacSettings()]);
    }
}
