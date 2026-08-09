<?php

use App\Domains\FormOptions\Models\FormOption;
use Database\Seeders\LocationSeeder;

describe('location hierarchy seeding', function () {
    beforeEach(function () {
        $this->seed(LocationSeeder::class);
    });

    it('seeds province and city with the expected counts', function () {
        $counts = [
            'province' => 31,
            'city' => 1156,
        ];

        foreach ($counts as $group => $expected) {
            expect(FormOption::ofGroup($group)->count())->toBe($expected);
        }
    });

    it('links every city to an existing province parent', function () {
        $childParents = FormOption::ofGroup('city')->pluck('parent_value');
        $parentValues = FormOption::ofGroup('province')->pluck('value');

        expect($childParents->contains(fn ($value) => $value === null))->toBeFalse()
            ->and($childParents->diff($parentValues))->toBeEmpty();
    });

    it('stores geo metadata on provinces and counties on cities', function () {
        $province = FormOption::ofGroup('province')->where('value', '100')->first();
        $city = FormOption::ofGroup('city')->where('value', '100-1000001001101')->first();

        expect($province->meta)->toHaveKey('tel_prefix')
            ->and($province->meta)->toHaveKey('lat')
            ->and($province->meta)->toHaveKey('lon')
            ->and($city->meta)->toHaveKey('county_id');
    });

    it('is idempotent across repeated runs', function () {
        $this->seed(LocationSeeder::class);

        expect(FormOption::count())->toBe(1187);
    });
});

describe('location groups on the public endpoints', function () {
    it('serves a parent-filtered location group', function () {
        FormOption::create(['group' => 'province', 'value' => '100', 'label' => 'مرکزی', 'sort_order' => 0]);
        FormOption::create(['group' => 'city', 'value' => '100-1000007001112', 'label' => 'آستانه', 'parent_value' => '100', 'sort_order' => 0]);
        FormOption::create(['group' => 'city', 'value' => '100-1000001001101', 'label' => 'اراک', 'parent_value' => '100', 'sort_order' => 1]);
        FormOption::create(['group' => 'city', 'value' => '101-1000009002103', 'label' => 'آستارا', 'parent_value' => '101', 'sort_order' => 0]);

        $this->getJson('/api/form-options/city?parent_value=100')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.value', '100-1000007001112')
            ->assertJsonPath('data.1.value', '100-1000001001101');
    });

    it('filters a group by label via the search param', function () {
        FormOption::create(['group' => 'city', 'value' => '123-1000001002577', 'label' => 'تهران', 'sort_order' => 0]);
        FormOption::create(['group' => 'city', 'value' => '100-1000001001101', 'label' => 'اراک', 'sort_order' => 1]);
        FormOption::create(['group' => 'city', 'value' => '107-1000002001234', 'label' => 'شیراز', 'sort_order' => 2]);

        $this->getJson('/api/form-options/city?search='.urlencode('تهران'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', '123-1000001002577');
    });

    it('caps search results with the limit param', function () {
        foreach (['تهران ۱', 'تهران ۲', 'تهران ۳'] as $i => $label) {
            FormOption::create(['group' => 'city', 'value' => "123-10000010000{$i}", 'label' => $label, 'sort_order' => $i]);
        }

        $this->getJson('/api/form-options/city?search=تهران&limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('combines the search and parent_value filters', function () {
        FormOption::create(['group' => 'city', 'value' => '100-1000001001101', 'label' => 'اراک', 'parent_value' => '100', 'sort_order' => 0]);
        FormOption::create(['group' => 'city', 'value' => '100-1000007001112', 'label' => 'آستانه', 'parent_value' => '100', 'sort_order' => 1]);
        FormOption::create(['group' => 'city', 'value' => '101-1000009002103', 'label' => 'اراک', 'parent_value' => '101', 'sort_order' => 0]);

        $this->getJson('/api/form-options/city?parent_value=100&search='.urlencode('آستانه'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', '100-1000007001112');
    });

    it('excludes location groups from the aggregate index', function () {
        FormOption::create(['group' => 'gender', 'value' => 'male', 'label' => 'مرد', 'sort_order' => 0]);
        FormOption::create(['group' => 'province', 'value' => '100', 'label' => 'مرکزی', 'sort_order' => 0]);

        $this->getJson('/api/form-options')
            ->assertOk()
            ->assertJsonPath('data.gender.0.value', 'male')
            ->assertJsonMissingPath('data.province');
    });
});

describe('location groups on the admin endpoints', function () {
    it('excludes location groups from the unfiltered admin list', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        FormOption::create(['group' => 'gender', 'value' => 'male', 'label' => 'مرد', 'sort_order' => 0]);
        FormOption::create(['group' => 'province', 'value' => '100', 'label' => 'مرکزی', 'sort_order' => 0]);

        $this->actingAs($user)
            ->getJson('/api/admin/form-options')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('includes a location group when explicitly filtered', function () {
        $user = createUserWithPermissions(['form-options.manage']);
        FormOption::create(['group' => 'city', 'value' => '100-1000001001101', 'label' => 'اراک', 'parent_value' => '100', 'sort_order' => 0]);

        $this->actingAs($user)
            ->getJson('/api/admin/form-options?group=city')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', '100-1000001001101');
    });
});
