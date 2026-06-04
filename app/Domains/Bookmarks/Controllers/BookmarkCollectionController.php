<?php

namespace App\Domains\Bookmarks\Controllers;

use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Domains\Bookmarks\Requests\ReorderRequest;
use App\Domains\Bookmarks\Requests\StoreBookmarkCollectionRequest;
use App\Domains\Bookmarks\Requests\UpdateBookmarkCollectionRequest;
use App\Domains\Bookmarks\Resources\BookmarkCollectionResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkCollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $collections = BookmarkCollection::forUser($request->user()->id)
            ->withCount('bookmarks')
            ->with(['categories' => fn ($q) => $q->ordered()->withCount('bookmarks')])
            ->ordered()
            ->get();

        return $this->success(BookmarkCollectionResource::collection($collections));
    }

    public function store(StoreBookmarkCollectionRequest $request): JsonResponse
    {
        $collection = BookmarkCollection::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        $collection->loadCount('bookmarks');

        return $this->created(new BookmarkCollectionResource($collection), 'Coleção criada');
    }

    public function update(UpdateBookmarkCollectionRequest $request, BookmarkCollection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        $collection->update($request->validated());
        $collection->loadCount('bookmarks');

        return $this->success(new BookmarkCollectionResource($collection), 'Coleção atualizada');
    }

    public function destroy(Request $request, BookmarkCollection $collection): JsonResponse
    {
        $this->authorize('delete', $collection);

        $collection->delete();

        return $this->noContent();
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        foreach ($request->items as $item) {
            BookmarkCollection::where('id', $item['id'])
                ->where('user_id', $request->user()->id)
                ->update(['position' => $item['position']]);
        }

        return $this->success([], 'Ordem atualizada');
    }
}
