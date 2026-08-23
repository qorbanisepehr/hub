<?php

use App\Domains\Employee\Models\Employee;
use App\Domains\FormOptions\Models\FormOption;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Rules\FormOptionValue;
use Illuminate\Support\Facades\Validator;

function makeOption(array $attributes = []): FormOption
{
    return FormOption::factory()->create(array_merge([
        'group' => 'test_group',
        'value' => 'test_value',
        'label' => 'گزینه تست',
    ], $attributes));
}

describe('form options public endpoints', function () {
    beforeEach(function () {
        makeOption(['group' => 'gender', 'value' => 'male', 'label' => 'مرد', 'sort_order' => 1]);
        makeOption(['group' => 'gender', 'value' => 'female', 'label' => 'زن', 'sort_order' => 2]);
    });

    it('returns active options keyed by group for every group', function () {
        $this->getJson('/api/form-options')
            ->assertOk()
            ->assertJsonPath('data.gender.0.value', 'male')
            ->assertJsonPath('data.gender.0.label', 'مرد')
            ->assertJsonPath('data.gender.1.value', 'female');
    });

    it('returns a single group of options', function () {
        $this->getJson('/api/form-options/gender')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.value', 'male')
            ->assertJsonPath('data.1.value', 'female');
    });

    it('exposes parent_value and group_label for grouped options', function () {
        makeOption(['group' => 'religion_sect', 'value' => 'shia', 'label' => 'شیعه', 'parent_value' => 'islam', 'group_label' => 'اسلام']);

        $this->getJson('/api/form-options/religion_sect')
            ->assertOk()
            ->assertJsonPath('data.0.parent_value', 'islam')
            ->assertJsonPath('data.0.group_label', 'اسلام');
    });

    it('orders options by sort_order', function () {
        makeOption(['group' => 'priority', 'value' => 'b', 'sort_order' => 2]);
        makeOption(['group' => 'priority', 'value' => 'a', 'sort_order' => 1]);

        $this->getJson('/api/form-options/priority')
            ->assertOk()
            ->assertJsonPath('data.0.value', 'a')
            ->assertJsonPath('data.1.value', 'b');
    });

    it('excludes inactive options from public responses', function () {
        makeOption(['group' => 'gender', 'value' => 'other', 'label' => 'سایر', 'is_active' => false]);

        $this->getJson('/api/form-options/gender')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('returns an empty group for an unknown group', function () {
        $this->getJson('/api/form-options/nope')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('resolves stored values back to labels including inactive options', function () {
        makeOption(['group' => 'gender', 'value' => 'other', 'label' => 'سایر', 'is_active' => false]);

        $response = $this->getJson('/api/form-options/gender/resolve?values=male,other,alien')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $labels = collect($response->json('data'))->pluck('label', 'value');

        expect($labels['male'])->toBe('مرد')
            ->and($labels['other'])->toBe('سایر');
    });

    it('caps the number of resolvable values at 100', function () {
        for ($i = 1; $i <= 120; $i++) {
            makeOption(['group' => 'bulk', 'value' => "v{$i}", 'label' => "گزینه {$i}"]);
        }

        $values = implode(',', array_map(fn ($i) => "v{$i}", range(1, 120)));

        $this->getJson("/api/form-options/bulk/resolve?values={$values}")
            ->assertOk()
            ->assertJsonCount(100, 'data');
    });

    it('clamps the limit parameter between 1 and 100', function () {
        makeOption(['group' => 'priority', 'value' => 'a', 'sort_order' => 1]);
        makeOption(['group' => 'priority', 'value' => 'b', 'sort_order' => 2]);
        makeOption(['group' => 'priority', 'value' => 'c', 'sort_order' => 3]);

        $this->getJson('/api/form-options/priority?limit=0')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/form-options/priority?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/form-options/priority?limit=999')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('treats LIKE wildcards in search terms as literals', function () {
        makeOption(['group' => 'code', 'value' => 'pct', 'label' => '100% تخفیف']);
        makeOption(['group' => 'code', 'value' => 'under', 'label' => 'a_b']);
        makeOption(['group' => 'code', 'value' => 'plain', 'label' => 'plain']);

        $this->getJson('/api/form-options/code?search=100%25')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', 'pct');

        $this->getJson('/api/form-options/code?search=a_b')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', 'under');

        $this->getJson('/api/form-options/code?search=plain')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('does not let a wildcard search term match unrelated options', function () {
        makeOption(['group' => 'code', 'value' => 'alpha', 'label' => 'الفبا']);
        makeOption(['group' => 'code', 'value' => 'beta', 'label' => 'بتا']);

        $this->getJson('/api/form-options/code?search=%2525')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('form options admin endpoints', function () {
    it('rejects unauthenticated requests', function () {
        $this->getJson('/api/admin/form-options')->assertStatus(401);
        $this->postJson('/api/admin/form-options', ['group' => 'x', 'value' => 'y', 'label' => 'z'])->assertStatus(401);
    });

    it('forbids users without the manage permission', function () {
        $user = createUserWithPermissions();

        $this->actingAs($user)
            ->getJson('/api/admin/form-options')
            ->assertStatus(403);
    });

    it('lists all options grouped by group for managers', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption();

        $this->actingAs($user)
            ->getJson('/api/admin/form-options')
            ->assertOk()
            ->assertJsonPath('data.0.value', $option->value);
    });

    it('filters the admin list by group', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        makeOption(['group' => 'first', 'value' => 'a']);
        makeOption(['group' => 'second', 'value' => 'b']);

        $this->actingAs($user)
            ->getJson('/api/admin/form-options?group=first')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', 'a');
    });

    it('paginates the admin list', function () {
        $user = createUserWithPermissions(['form-options.manage']);

        for ($i = 1; $i <= 25; $i++) {
            makeOption(['group' => 'bulk', 'value' => "v{$i}", 'sort_order' => $i]);
        }

        $this->actingAs($user)
            ->getJson('/api/admin/form-options?group=bulk&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.value', 'v1');

        $this->actingAs($user)
            ->getJson('/api/admin/form-options?group=bulk&per_page=10&page=3')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.value', 'v21');
    });

    it('clamps per_page between 1 and 100', function () {
        $user = createUserWithPermissions(['form-options.manage']);

        for ($i = 1; $i <= 25; $i++) {
            makeOption(['group' => 'bulk', 'value' => "v{$i}", 'sort_order' => $i]);
        }

        $this->actingAs($user)
            ->getJson('/api/admin/form-options?group=bulk&per_page=0')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->getJson('/api/admin/form-options?group=bulk&per_page=999')
            ->assertOk()
            ->assertJsonCount(25, 'data');
    });

    it('lists admin groups including location groups with counts', function () {
        $user = createUserWithPermissions(['form-options.manage']);

        makeOption(['group' => 'gender', 'value' => 'male', 'label' => 'مرد']);
        makeOption(['group' => 'gender', 'value' => 'female', 'label' => 'زن']);
        makeOption(['group' => 'gender', 'value' => 'other', 'is_active' => false]);
        makeOption([
            'group' => 'province',
            'value' => '100',
            'label' => 'مرکزی',
            'group_label' => 'استان',
        ]);
        makeOption([
            'group' => 'city',
            'value' => '100-1000001001101',
            'label' => 'اراک',
            'parent_value' => '100',
            'group_label' => 'شهر',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/form-options/groups')
            ->assertOk();

        $groups = collect($response->json('data'))->pluck('count', 'group');

        expect($groups['gender'])->toBe(3)
            ->and($groups['province'])->toBe(1)
            ->and($groups['city'])->toBe(1);

        $provinceRow = collect($response->json('data'))
            ->firstWhere('group', 'province');

        expect($provinceRow['label'])->toBe('استان');
    });

    it('rejects unauthenticated requests to the groups endpoint', function () {
        $this->getJson('/api/admin/form-options/groups')->assertStatus(401);
    });

    it('creates an option', function () {
        $user = createUserWithPermissions(['form-options.manage']);

        $this->actingAs($user)
            ->postJson('/api/admin/form-options', [
                'group' => 'gender',
                'value' => 'male',
                'label' => 'مرد',
                'sort_order' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.group', 'gender')
            ->assertJsonPath('data.value', 'male')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('form_options', ['group' => 'gender', 'value' => 'male', 'label' => 'مرد']);
    });

    it('rejects a duplicate value within the same group', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        makeOption(['group' => 'gender', 'value' => 'male']);

        $this->actingAs($user)
            ->postJson('/api/admin/form-options', [
                'group' => 'gender',
                'value' => 'male',
                'label' => 'مرد',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    });

    it('allows the same value in a different group', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        makeOption(['group' => 'gender', 'value' => 'male']);

        $this->actingAs($user)
            ->postJson('/api/admin/form-options', [
                'group' => 'employment_type',
                'value' => 'male',
                'label' => '???',
            ])
            ->assertOk();
    });

    it('rejects a city whose parent_value is an unknown province', function () {
        $user = createUserWithPermissions(['form-options.manage']);

        $this->actingAs($user)
            ->postJson('/api/admin/form-options', [
                'group' => 'city',
                'value' => '100-1000001001101',
                'label' => '???',
                'parent_value' => '999',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_value']);
    });

    it('rejects a city whose parent_value references an inactive province', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        makeOption(['group' => 'province', 'value' => '100', 'label' => '??????', 'is_active' => false]);

        $this->actingAs($user)
            ->postJson('/api/admin/form-options', [
                'group' => 'city',
                'value' => '100-1000001001101',
                'label' => '???',
                'parent_value' => '100',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_value']);
    });

    it('accepts a city linked to an active province', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        makeOption(['group' => 'province', 'value' => '100', 'label' => '??????']);

        $this->actingAs($user)
            ->postJson('/api/admin/form-options', [
                'group' => 'city',
                'value' => '100-1000001001101',
                'label' => '???',
                'parent_value' => '100',
            ])
            ->assertOk()
            ->assertJsonPath('data.parent_value', '100');
    });

    it('does not enforce parent groups outside the location hierarchy', function () {
        $user = createUserWithPermissions(['form-options.manage']);

        $this->actingAs($user)
            ->postJson('/api/admin/form-options', [
                'group' => 'religion_sect',
                'value' => 'shia',
                'label' => '???',
                'parent_value' => 'islam',
            ])
            ->assertOk();
    });

    it('updates an option', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption(['label' => 'قدیمی']);

        $this->actingAs($user)
            ->putJson("/api/admin/form-options/{$option->id}", [
                'label' => 'جدید',
                'sort_order' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'جدید')
            ->assertJsonPath('data.sort_order', 5);

        $this->assertDatabaseHas('form_options', ['id' => $option->id, 'label' => 'جدید']);
    });

    it('rejects an update that collides with another value in the group', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        makeOption(['group' => 'gender', 'value' => 'female']);
        $male = makeOption(['group' => 'gender', 'value' => 'male']);

        $this->actingAs($user)
            ->putJson("/api/admin/form-options/{$male->id}", ['value' => 'female'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    });

    it('allows renaming the value of an unreferenced option', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption();

        $this->actingAs($user)
            ->putJson("/api/admin/form-options/{$option->id}", ['value' => 'renamed_value'])
            ->assertOk()
            ->assertJsonPath('data.value', 'renamed_value');
    });

    it('refuses to rename the value of a referenced option', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption(['group' => 'religion', 'value' => 'islam']);

        Employee::factory()->create([
            'section_personal' => ['religion' => $option->value],
        ]);

        $this->actingAs($user)
            ->putJson("/api/admin/form-options/{$option->id}", ['value' => 'renamed_value'])
            ->assertStatus(409);

        $this->assertDatabaseHas('form_options', ['id' => $option->id, 'value' => 'islam']);
    });

    it('still updates presentation fields of a referenced option', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption(['group' => 'religion', 'value' => 'islam']);

        Employee::factory()->create([
            'section_personal' => ['religion' => $option->value],
        ]);

        $this->actingAs($user)
            ->putJson("/api/admin/form-options/{$option->id}", [
                'label' => '????',
                'sort_order' => 7,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.label', '????')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('form_options', ['id' => $option->id, 'value' => 'islam', 'sort_order' => 7]);
    });

    it('deletes an option', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption();

        $this->actingAs($user)
            ->deleteJson("/api/admin/form-options/{$option->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('form_options', ['id' => $option->id]);
    });

    it('refuses to delete an option referenced by employee sections', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption();

        Employee::factory()->create([
            'section_personal' => ['religion' => $option->value],
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/admin/form-options/{$option->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('form_options', ['id' => $option->id]);
    });

    it('refuses to delete an option referenced by a soft-deleted employee', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption();

        Employee::factory()->create([
            'section_skills' => ['skill' => [$option->value]],
            'deleted_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/admin/form-options/{$option->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('form_options', ['id' => $option->id]);
    });

    it('toggles an option active state', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption();

        $this->actingAs($user)
            ->postJson("/api/admin/form-options/{$option->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($user)
            ->postJson("/api/admin/form-options/{$option->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    });

    it('makes a toggled-off option disappear from public responses', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        $option = makeOption(['group' => 'gender', 'value' => 'male']);

        $this->getJson('/api/form-options/gender')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->postJson("/api/admin/form-options/{$option->id}/toggle")
            ->assertOk();

        $this->getJson('/api/form-options/gender')->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('FormOptionValue rule', function () {
    beforeEach(function () {
        makeOption(['group' => 'gender', 'value' => 'male', 'label' => 'مرد']);
        makeOption(['group' => 'gender', 'value' => 'female', 'label' => 'زن']);
        makeOption(['group' => 'gender', 'value' => 'other', 'label' => 'سایر', 'is_active' => false]);
    });

    it('accepts an active value', function () {
        $validator = Validator::make(['gender' => 'male'], ['gender' => new FormOptionValue('gender')]);

        expect($validator->passes())->toBeTrue();
    });

    it('rejects an unknown value', function () {
        $validator = Validator::make(['gender' => 'alien'], ['gender' => new FormOptionValue('gender')]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('gender'))->toBe('مقدار انتخابی نامعتبر است.');
    });

    it('rejects an inactive value', function () {
        $validator = Validator::make(['gender' => 'other'], ['gender' => new FormOptionValue('gender')]);

        expect($validator->fails())->toBeTrue();
    });

    it('accepts an array of active values', function () {
        makeOption(['group' => 'preferred_workplace', 'value' => 'tehran', 'label' => 'دفتر تهران']);
        makeOption(['group' => 'preferred_workplace', 'value' => 'kerman', 'label' => 'دفتر کرمان']);

        $validator = Validator::make(
            ['places' => ['tehran', 'kerman']],
            ['places' => ['array', new FormOptionValue('preferred_workplace')]],
        );

        expect($validator->passes())->toBeTrue();
    });

    it('rejects an array containing an unknown value', function () {
        makeOption(['group' => 'preferred_workplace', 'value' => 'tehran', 'label' => 'دفتر تهران']);

        $validator = Validator::make(
            ['places' => ['tehran', 'mars']],
            ['places' => ['array', new FormOptionValue('preferred_workplace')]],
        );

        expect($validator->fails())->toBeTrue();
    });
});

describe('FormOptionService caching', function () {
    it('flushes the cached options for a group after an update', function () {
        $service = app(FormOptionService::class);
        $option = makeOption(['group' => 'gender', 'value' => 'male']);

        expect($service->getOptions('gender'))->toHaveCount(1);

        $option->update(['is_active' => false]);

        expect($service->getOptions('gender'))->toHaveCount(1);

        $service->flush('gender');

        expect($service->getOptions('gender'))->toHaveCount(0);
    });

    it('isValid only matches active options', function () {
        $service = app(FormOptionService::class);
        makeOption(['group' => 'gender', 'value' => 'male']);
        makeOption(['group' => 'gender', 'value' => 'other', 'is_active' => false]);

        expect($service->isValid('gender', 'male'))->toBeTrue()
            ->and($service->isValid('gender', 'other'))->toBeFalse()
            ->and($service->isValid('gender', 'alien'))->toBeFalse();
    });

    it('serves stored records holding historically deactivated options without revalidation', function () {
        makeOption(['group' => 'gender', 'value' => 'male', 'label' => 'مرد']);

        $employee = Employee::factory()->create([
            'section_personal' => ['gender' => 'male'],
        ]);

        FormOption::query()
            ->where('group', 'gender')
            ->where('value', 'male')
            ->update(['is_active' => false]);

        $user = createUserWithPermissions(['employee.view']);

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.section_personal.gender', 'male');
    });
});
