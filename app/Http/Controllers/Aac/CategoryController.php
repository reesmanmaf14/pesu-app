<?php

namespace App\Http\Controllers\Aac;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function store(SaveCategoryRequest $request): JsonResponse
    {
        $category = $request->user()->categories()->create([
            ...$this->attributes($request->validated()),
            // Built-in slugs are words like "food"; user slugs only need to be unique.
            'slug' => 'my-'.Str::lower(Str::random(12)),
            'sort_order' => 1000,
        ]);

        return response()->json(['category' => $category->toAac()], 201);
    }

    public function update(SaveCategoryRequest $request, Category $category): JsonResponse
    {
        $attributes = $this->attributes($request->validated());

        DB::transaction(function () use ($category, $attributes) {
            $kindChanged = $category->default_kind !== $attributes['default_kind'];
            $category->update($attributes);

            // The kind decides colour and grammar, so the words already here follow it.
            if ($kindChanged) {
                $category->tiles()->each(function ($tile) use ($attributes) {
                    $kind = $attributes['default_kind'];
                    $tile->update([
                        'kind' => $kind,
                        'en_to' => $kind === 'place' ? 'to '.$tile->label_en : null,
                    ]);
                });
            }
        });

        $category->load(['tiles' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        return response()->json(['category' => $category->toAac($category->tiles)]);
    }

    /** Words are never deleted with their category: the category must be emptied first. */
    public function destroy(Category $category): JsonResponse|Response
    {
        Gate::authorize('delete', $category);

        $count = $category->tiles()->count();
        if ($count > 0) {
            return response()->json([
                'message' => trans_choice(
                    'This category still has :count word. Move or delete it first.|This category still has :count words. Move or delete them first.',
                    $count
                ),
            ], 422);
        }

        $category->delete();

        return response()->noContent();
    }

    private function attributes(array $data): array
    {
        $en = $data['name_en'] ?? $data['name_ta'];
        $ta = $data['name_ta'] ?? $data['name_en'];

        return [
            'name_en' => $en,
            'name_ta' => $ta,
            'emoji' => ($data['emoji'] ?? null) ?: '📁',
            'default_kind' => $data['kind'],
        ];
    }
}
