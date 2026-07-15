<?php

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;

function categoryData(array $overrides = []): array
{
    return array_merge([
        'name' => 'مدارک فردی',
        'slug' => 'personal-docs',
        'description' => 'مدارک شناسایی',
        'sort_order' => 1,
        'documentable_type' => 'employee',
    ], $overrides);
}

describe('document category CRUD', function () {
    describe('authentication', function () {
        it('blocks unauthenticated access', function () {
            $this->getJson('/api/document-categories')->assertStatus(401);
            $this->postJson('/api/document-categories', [])->assertStatus(401);
            $this->getJson('/api/document-categories/1')->assertStatus(401);
            $this->putJson('/api/document-categories/1', [])->assertStatus(401);
            $this->deleteJson('/api/document-categories/1')->assertStatus(401);
        });
    });

    describe('index', function () {
        it('lists all categories ordered by sort_order', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);
            DocumentCategory::factory()->create(['name' => 'Second', 'sort_order' => 2]);
            DocumentCategory::factory()->create(['name' => 'First', 'sort_order' => 1]);

            $this->actingAs($user)
                ->getJson('/api/document-categories')
                ->assertStatus(200)
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.name', 'First')
                ->assertJsonPath('data.1.name', 'Second');
        });

        it('filters by type', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);
            DocumentCategory::factory()->create(['name' => 'Employee Cat', 'sort_order' => 1, 'documentable_type' => Employee::class]);
            DocumentCategory::factory()->create(['name' => 'Other Cat', 'sort_order' => 2, 'documentable_type' => 'App\Domains\SomeOther\Models\Something']);

            $this->actingAs($user)
                ->getJson('/api/document-categories?type=employee')
                ->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Employee Cat');
        });

        it('returns all categories for unknown type', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);
            DocumentCategory::factory()->create();
            DocumentCategory::factory()->create();

            $this->actingAs($user)
                ->getJson('/api/document-categories?type=nonexistent')
                ->assertStatus(200)
                ->assertJsonCount(2, 'data');
        });

        it('includes documents_count', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);
            $category = DocumentCategory::factory()->create();

            $this->actingAs($user)
                ->getJson('/api/document-categories')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'name', 'slug', 'sort_order', 'documentable_type', 'documents_count'],
                    ],
                ]);
        });
    });

    describe('store', function () {
        it('creates a category with valid data', function () {
            $user = createUserWithPermissions(['document-category.manage']);

            $this->actingAs($user)
                ->postJson('/api/document-categories', categoryData())
                ->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'name' => 'مدارک فردی',
                        'slug' => 'personal-docs',
                        'description' => 'مدارک شناسایی',
                        'sort_order' => 1,
                    ],
                ]);

            $this->assertDatabaseHas('document_categories', [
                'slug' => 'personal-docs',
            ]);
        });

        it('fails with duplicate slug', function () {
            $user = createUserWithPermissions(['document-category.manage']);
            DocumentCategory::factory()->create(['slug' => 'personal-docs']);

            $this->actingAs($user)
                ->postJson('/api/document-categories', categoryData())
                ->assertStatus(422)
                ->assertJsonValidationErrors(['slug']);
        });

        it('fails with missing required fields', function () {
            $user = createUserWithPermissions(['document-category.manage']);

            $this->actingAs($user)
                ->postJson('/api/document-categories', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'slug', 'documentable_type']);
        });
    });

    describe('show', function () {
        it('returns a single category', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);
            $category = DocumentCategory::factory()->create();

            $this->actingAs($user)
                ->getJson('/api/document-categories/'.$category->id)
                ->assertStatus(200)
                ->assertJsonPath('data.id', $category->id);
        });

        it('returns 404 for non-existent category', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);

            $this->actingAs($user)
                ->getJson('/api/document-categories/99999')
                ->assertStatus(404);
        });
    });

    describe('update', function () {
        it('updates a category', function () {
            $user = createUserWithPermissions(['document-category.manage']);
            $category = DocumentCategory::factory()->create();

            $this->actingAs($user)
                ->putJson('/api/document-categories/'.$category->id, [
                    'name' => 'Updated',
                    'slug' => $category->slug,
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.name', 'Updated');
        });

        it('allows unique slug on own record', function () {
            $user = createUserWithPermissions(['document-category.manage']);
            $category = DocumentCategory::factory()->create();

            $this->actingAs($user)
                ->putJson('/api/document-categories/'.$category->id, [
                    'name' => 'Test',
                    'slug' => $category->slug,
                ])
                ->assertStatus(200);
        });
    });

    describe('destroy', function () {
        it('deletes a category', function () {
            $user = createUserWithPermissions(['document-category.manage']);
            $category = DocumentCategory::factory()->create();

            $this->actingAs($user)
                ->deleteJson('/api/document-categories/'.$category->id)
                ->assertStatus(200)
                ->assertJson(['message' => __('document.category_deleted')]);

            $this->assertDatabaseMissing('document_categories', ['id' => $category->id]);
        });
    });

    describe('authorization', function () {
        it('denies access without required permission', function () {
            $user = createUserWithPermissions([]);

            $this->actingAs($user)
                ->getJson('/api/document-categories')
                ->assertStatus(403);
        });

        it('denies store without document-category.manage permission', function () {
            $user = createUserWithPermissions(['document-category.view']);

            $this->actingAs($user)
                ->postJson('/api/document-categories', categoryData())
                ->assertStatus(403);
        });
    });
});
