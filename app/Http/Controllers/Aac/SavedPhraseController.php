<?php

namespace App\Http\Controllers\Aac;

use App\Http\Controllers\Controller;
use App\Models\SavedPhrase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SavedPhraseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
        ]);

        $phrase = $request->user()->savedPhrases()->create($data);

        return response()->json(['phrase' => $phrase->only(['id', 'text'])], 201);
    }

    public function destroy(Request $request, SavedPhrase $phrase): Response
    {
        abort_unless((int) $phrase->user_id === (int) $request->user()->id, 403);

        $phrase->delete();

        return response()->noContent();
    }
}
