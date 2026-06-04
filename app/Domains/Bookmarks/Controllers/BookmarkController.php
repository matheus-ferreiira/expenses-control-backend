<?php

namespace App\Domains\Bookmarks\Controllers;

use App\Domains\Bookmarks\Models\Bookmark;
use App\Domains\Bookmarks\Models\BookmarkCategory;
use App\Domains\Bookmarks\Requests\ReorderRequest;
use App\Domains\Bookmarks\Requests\StoreBookmarkRequest;
use App\Domains\Bookmarks\Requests\UpdateBookmarkRequest;
use App\Domains\Bookmarks\Resources\BookmarkResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request, BookmarkCategory $category): JsonResponse
    {
        $this->authorize('view', $category);

        $query = Bookmark::where('bookmark_category_id', $category->id)->ordered();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->boolean('favorites')) {
            $query->favorites();
        }

        return $this->success(BookmarkResource::collection($query->get()));
    }

    public function store(StoreBookmarkRequest $request, BookmarkCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $bookmark = Bookmark::create([
            'bookmark_category_id' => $category->id,
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return $this->created(new BookmarkResource($bookmark), 'Bookmark criado');
    }

    public function update(UpdateBookmarkRequest $request, Bookmark $bookmark): JsonResponse
    {
        $this->authorize('update', $bookmark);

        if ($request->has('bookmark_category_id')) {
            $newCategory = BookmarkCategory::findOrFail($request->bookmark_category_id);
            if ($newCategory->user_id !== $request->user()->id) {
                return $this->error('Categoria não encontrada', 403);
            }
        }

        $bookmark->update($request->validated());

        return $this->success(new BookmarkResource($bookmark), 'Bookmark atualizado');
    }

    public function destroy(Request $request, Bookmark $bookmark): JsonResponse
    {
        $this->authorize('delete', $bookmark);

        $bookmark->delete();

        return $this->noContent();
    }

    public function toggleFavorite(Request $request, Bookmark $bookmark): JsonResponse
    {
        $this->authorize('update', $bookmark);

        $bookmark->update(['is_favorite' => !$bookmark->is_favorite]);

        return $this->success(new BookmarkResource($bookmark), 'Favorito atualizado');
    }

    public function reorder(ReorderRequest $request, BookmarkCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        foreach ($request->items as $item) {
            Bookmark::where('id', $item['id'])
                ->where('bookmark_category_id', $category->id)
                ->where('user_id', $request->user()->id)
                ->update(['position' => $item['position']]);
        }

        return $this->success([], 'Ordem atualizada');
    }
}
