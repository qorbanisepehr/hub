<?php

namespace App\Domains\Document\Controllers;

use App\Contracts\Authorization;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Requests\StoreDocumentCategoryRequest;
use App\Domains\Document\Requests\UpdateDocumentCategoryRequest;
use App\Domains\Document\Resources\DocumentCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class DocumentCategoryController extends Controller
{
    public function __construct(
        private Authorization $authorization,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = DocumentCategory::query()
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            })
            ->when(! $request->boolean('all'), function ($query) {
                $query->whereNull('parent_id');
            })
            ->with(['children' => function ($q) {
                $q->orderBy('sort_order');
                $q->with(['children' => function ($q2) {
                    $q2->orderBy('sort_order');
                }]);
            }])
            ->orderBy('sort_order');

        // Public read endpoint: apply row-level scoping only when an
        // authenticated actor holds the permission, so anonymous clients and
        // users without the permission keep full access.
        if ($request->user()?->hasAnyPermission(['document-category.view']) === true) {
            $this->authorization->scope($request->user(), 'document-category.view', $categories);
        }

        return DocumentCategoryResource::collection($categories->get());
    }

    public function store(StoreDocumentCategoryRequest $request): DocumentCategoryResource
    {
        $category = DocumentCategory::create($request->validated());

        return new DocumentCategoryResource($category);
    }

    public function show(Request $request, DocumentCategory $documentCategory): DocumentCategoryResource
    {
        $this->authorization->authorize($request->user(), 'document-category.view', $documentCategory);

        return new DocumentCategoryResource($documentCategory);
    }

    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $documentCategory): DocumentCategoryResource
    {
        $this->authorization->authorize($request->user(), 'document-category.manage', $documentCategory);

        $documentCategory->update($request->validated());

        return new DocumentCategoryResource($documentCategory);
    }

    public function destroy(Request $request, DocumentCategory $documentCategory): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'document-category.manage', $documentCategory);

        $documentCategory->delete();

        return response()->json(['message' => __('document.category_deleted')]);
    }
}
