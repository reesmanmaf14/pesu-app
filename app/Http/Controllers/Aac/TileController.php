<?php

namespace App\Http\Controllers\Aac;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTileRequest;
use App\Http\Requests\UpdateTileRequest;
use App\Models\Category;
use App\Models\Tile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TileController extends Controller
{
    public function store(StoreTileRequest $request): JsonResponse
    {
        $data = $request->validated();
        $category = Category::findOrFail($data['category_id']);

        $tile = $request->user()->tiles()->create([
            ...$this->wordAttributes($data, $category),
            'image_path' => $request->hasFile('photo')
                ? $request->file('photo')->store('tiles', Tile::photoDisk())
                : null,
            'ta_audio_path' => $request->hasFile('ta_audio')
                ? $this->storeAudio($request->file('ta_audio'), $request->user()->id)
                : null,
            'sort_order' => 1000,
        ]);

        return response()->json(['tile' => $tile->toAac()], 201);
    }

    /** Sent as POST with _method=PUT so the photo and recording can travel as multipart form data. */
    public function update(UpdateTileRequest $request, Tile $tile): JsonResponse
    {
        $data = $request->validated();
        $category = Category::findOrFail($data['category_id']);
        $disk = Storage::disk(Tile::photoDisk());
        $audioDisk = Storage::disk(Tile::audioDisk());

        $tile->fill($this->wordAttributes($data, $category));

        if ($request->hasFile('photo') || $request->boolean('remove_photo')) {
            if ($tile->image_path) {
                $disk->delete($tile->image_path);
            }
            $tile->image_path = $request->hasFile('photo') ? $request->file('photo')->store('tiles', Tile::photoDisk()) : null;
        }

        if ($request->hasFile('ta_audio') || $request->boolean('remove_audio')) {
            if ($tile->ta_audio_path) {
                $audioDisk->delete($tile->ta_audio_path);
            }
            $tile->ta_audio_path = $request->hasFile('ta_audio')
                ? $this->storeAudio($request->file('ta_audio'), $request->user()->id)
                : null;
        }

        $tile->save();

        return response()->json(['tile' => $tile->toAac()]);
    }

    public function destroy(Request $request, Tile $tile): Response
    {
        // Built-in tiles have no owner, so this also blocks deleting them.
        Gate::authorize('delete', $tile);

        $tile->deleteFiles();
        $tile->delete();

        return response()->noContent();
    }

    /** Word fields shared by store and update; the category decides the grammar kind. */
    private function wordAttributes(array $data, Category $category): array
    {
        $kind = $category->default_kind;
        $en = $data['label_en'] ?? $data['label_ta'];
        $ta = $data['label_ta'] ?? $data['label_en'];

        return [
            'category_id' => $category->id,
            'kind' => $kind,
            'emoji' => ($data['emoji'] ?? null) ?: '🔤',
            'label_en' => $en,
            'label_ta' => $ta,
            'ta_dative' => $kind === 'place' ? ($data['ta_dative'] ?? null) : null,
            'ta_infinitive' => $kind === 'verb' ? ($data['ta_infinitive'] ?? null) : null,
            'en_to' => $kind === 'place' ? 'to '.$en : null,
        ];
    }

    /** Store a recording under the owner's folder (see Tile::storeRecording). */
    private function storeAudio(UploadedFile $file, int $userId): string
    {
        return Tile::storeRecording($file, Tile::AUDIO_DIR.'/'.$userId);
    }
}
