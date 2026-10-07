<?php

namespace App\Http\Controllers\Aac;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UsageController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'sentence' => ['required', 'string', 'max:1000'],
            'lang' => ['required', 'in:en,ta'],
        ]);

        $user = $request->user();

        if ($user->aacSettings()['log_usage']) {
            $user->usageLogs()->create($data);
        }

        return response()->noContent();
    }
}
