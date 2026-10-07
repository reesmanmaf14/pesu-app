<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $tiles = Tile::visibleTo($user)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Built-in categories first, then the user's own in the order they were made.
        $categories = Category::visibleTo($user)
            ->orderByRaw('user_id IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $c) => $c->toAac($tiles->where('category_id', $c->id)));

        $payload = [
            'settings' => $user->aacSettings(),
            // The therapist records the Tamil voice of built-in words from Edit mode.
            'canRecordShared' => $user->can('therapist'),
            'categories' => $categories,
            'phrases' => $user->savedPhrases()->latest()->get(['id', 'text']),
            'routes' => [
                'tilesStore' => route('aac.tiles.store'),
                'tileUpdate' => route('aac.tiles.update', ['tile' => '__ID__']),
                'tileDestroy' => route('aac.tiles.destroy', ['tile' => '__ID__']),
                'tileAudioStore' => route('aac.tiles.audio.store', ['tile' => '__ID__']),
                'tileAudioDestroy' => route('aac.tiles.audio.destroy', ['tile' => '__ID__']),
                'categoriesStore' => route('aac.categories.store'),
                'categoryUpdate' => route('aac.categories.update', ['category' => '__ID__']),
                'categoryDestroy' => route('aac.categories.destroy', ['category' => '__ID__']),
                'phrasesStore' => route('aac.phrases.store'),
                'phraseDestroy' => route('aac.phrases.destroy', ['phrase' => '__ID__']),
                'settings' => route('aac.settings.update'),
                'usage' => route('aac.usage.store'),
            ],
        ];

        return view('aac.board', ['payload' => $payload]);
    }
}
