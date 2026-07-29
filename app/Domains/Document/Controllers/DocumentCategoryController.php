<?php

namespace App\Domains\Document\Controllers;

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Requests\StoreDocumentCategoryRequest;
use App\Domains\Document\Requests\UpdateDocumentCategoryRequest;
use App\Domains\Document\Resources\DocumentCategoryResource;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentCategoryController extends ApiController
{
    protected ?string $model = DocumentCategory::class;

    public function callAction($method, $parameters): mixed
    {
        if ($method === 'index' && request()->user() === null) {
            return $this->index(request());
        }

        return parent::callAction($method, $parameters);
    }

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
            ->orderBy('sort_order')
            ->get();

        return DocumentCategoryResource::collection($categories);
    }

    public function store(StoreDocumentCategoryRequest $request): DocumentCategoryResource
    {
        $category = DocumentCategory::create($request->validated());

        return new DocumentCategoryResource($category);
    }

    public function show(DocumentCategory $documentCategory): DocumentCategoryResource
    {
        return new DocumentCategoryResource($documentCategory);
    }

    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $documentCategory): DocumentCategoryResource
    {
        $documentCategory->update($request->validated());

        return new DocumentCategoryResource($documentCategory);
    }

    public function destroy(DocumentCategory $documentCategory): JsonResponse
    {
        $documentCategory->delete();

        return response()->json(['message' => __('document.category_deleted')]);
    }
}
