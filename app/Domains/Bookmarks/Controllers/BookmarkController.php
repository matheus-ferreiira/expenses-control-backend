<?php

namespace App\Domains\Bookmarks\Controllers;

use App\Domains\Bookmarks\Models\Bookmark;
use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Domains\Bookmarks\Requests\ReorderRequest;
use App\Domains\Bookmarks\Requests\StoreBookmarkRequest;
use App\Domains\Bookmarks\Requests\UpdateBookmarkRequest;
use App\Domains\Bookmarks\Resources\BookmarkResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request, BookmarkCollection $collection): JsonResponse
    {
        $this->authorize('view', $collection);

        $query = Bookmark::where('bookmark_collection_id', $collection->id)->ordered();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->boolean('favorites')) {
            $query->favorites();
        }

        return $this->success(BookmarkResource::collection($query->get()));
    }

    public function store(StoreBookmarkRequest $request, BookmarkCollection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        $bookmark = Bookmark::create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return $this->created(new BookmarkResource($bookmark), 'Bookmark criado');
    }

    public function update(UpdateBookmarkRequest $request, Bookmark $bookmark): JsonResponse
    {
        $this->authorize('update', $bookmark);

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

    public function reorder(ReorderRequest $request, BookmarkCollection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        foreach ($request->items as $item) {
            Bookmark::where('id', $item['id'])
                ->where('bookmark_collection_id', $collection->id)
                ->where('user_id', $request->user()->id)
                ->update(['position' => $item['position']]);
        }

        return $this->success([], 'Ordem atualizada');
    }
}
