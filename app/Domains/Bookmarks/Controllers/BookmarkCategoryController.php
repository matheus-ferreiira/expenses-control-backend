<?php

namespace App\Domains\Bookmarks\Controllers;

use App\Domains\Bookmarks\Models\BookmarkCategory;
use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Domains\Bookmarks\Requests\ReorderRequest;
use App\Domains\Bookmarks\Requests\StoreBookmarkCategoryRequest;
use App\Domains\Bookmarks\Requests\UpdateBookmarkCategoryRequest;
use App\Domains\Bookmarks\Resources\BookmarkCategoryResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkCategoryController extends Controller
{
    public function index(Request $request, BookmarkCollection $collection): JsonResponse
    {
        $this->authorize('view', $collection);

        $categories = $collection->categories()
            ->withCount('bookmarks')
            ->ordered()
            ->get();

        return $this->success(BookmarkCategoryResource::collection($categories));
    }

    public function store(StoreBookmarkCategoryRequest $request, BookmarkCollection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        $category = BookmarkCategory::create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        $category->loadCount('bookmarks');

        return $this->created(new BookmarkCategoryResource($category), 'Categoria criada');
    }

    public function update(UpdateBookmarkCategoryRequest $request, BookmarkCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());
        $category->loadCount('bookmarks');

        return $this->success(new BookmarkCategoryResource($category), 'Categoria atualizada');
    }

    public function destroy(Request $request, BookmarkCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return $this->noContent();
    }

    public function reorder(ReorderRequest $request, BookmarkCollection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        foreach ($request->items as $item) {
            BookmarkCategory::where('id', $item['id'])
                ->where('bookmark_collection_id', $collection->id)
                ->where('user_id', $request->user()->id)
                ->update(['position' => $item['position']]);
        }

        return $this->success([], 'Ordem atualizada');
    }
}
