<?php

use App\Domains\Document\Models\DocumentCategory;

function categoryData(array $overrides = []): array
{
    return array_merge([
        'name' => 'مدارک فردی',
        'slug' => 'personal-docs',
        'description' => 'مدارک شناسایی',
        'sort_order' => 1,
    ], $overrides);
}

describe('document category CRUD', function () {
    describe('authentication', function () {
        it('allows public access to index but blocks unauthenticated access to management endpoints', function () {
            $this->getJson('/api/document-categories')->assertOk();
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

        it('includes only parent categories by default', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);
            $parent = DocumentCategory::factory()->create(['name' => 'Parent']);
            $child = DocumentCategory::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);

            $this->actingAs($user)
                ->getJson('/api/document-categories')
                ->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Parent');
        });

        it('includes all categories when all param is true', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);
            $parent = DocumentCategory::factory()->create(['name' => 'Parent']);
            DocumentCategory::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);

            $this->actingAs($user)
                ->getJson('/api/document-categories?all=1')
                ->assertStatus(200)
                ->assertJsonCount(2, 'data');
        });

        it('returns all categories with all param', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);
            DocumentCategory::factory()->create(['name' => 'Custom A']);
            DocumentCategory::factory()->create(['name' => 'Custom B']);

            $response = $this->actingAs($user)
                ->getJson('/api/document-categories?all=1')
                ->assertStatus(200);

            $names = collect($response->json('data'))->pluck('name')->toArray();
            $this->assertContains('Custom A', $names);
            $this->assertContains('Custom B', $names);
        });

        it('includes expected fields', function () {
            $user = createUserWithPermissions(['document-category.view', 'document-category.manage']);
            DocumentCategory::factory()->create();

            $this->actingAs($user)
                ->getJson('/api/document-categories')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'name', 'slug', 'sort_order'],
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
                ->assertJsonValidationErrors(['name', 'slug']);
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
