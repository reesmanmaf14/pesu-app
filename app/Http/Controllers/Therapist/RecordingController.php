<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tile;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Built-in words and whether each has the therapist's Tamil recording. Parents' own words never appear here. */
class RecordingController extends Controller
{
    public function index(Request $request): View
    {
        $missingOnly = $request->boolean('missing');

        $tiles = Tile::whereNull('user_id')
            ->when($missingOnly, fn ($q) => $q->whereNull('ta_audio_path'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $categories = Category::whereNull('user_id')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $c) => $c->toAac($tiles->where('category_id', $c->id)))
            ->filter(fn (array $c) => count($c['tiles']) > 0)
            ->values();

        return view('therapist.recordings', [
            'payload' => [
                'categories' => $categories,
                'routes' => [
                    'tileAudioStore' => route('aac.tiles.audio.store', ['tile' => '__ID__']),
                    'tileAudioDestroy' => route('aac.tiles.audio.destroy', ['tile' => '__ID__']),
                ],
            ],
            'missingOnly' => $missingOnly,
            'total' => Tile::whereNull('user_id')->count(),
            'recorded' => Tile::whereNull('user_id')->whereNotNull('ta_audio_path')->count(),
        ]);
    }
}
