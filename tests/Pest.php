<?php

use App\Domains\FormOptions\Models\FormOption;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Rbac\Models\Permission;
use App\Domains\Rbac\Models\PermissionGroup;
use App\Domains\Rbac\Models\Role;
use App\Enums\GrantPurpose;
use App\Enums\OtpContext;
use App\Models\User;
use App\Services\OtpService;
use Database\Seeders\FormOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Seed the option groups referenced by questionnaire/cv validation rules.
 */
function seedFormOptions(?array $groups = null): void
{
    app(FormOptionSeeder::class)->run($groups);
}

/**
 * Seed a minimal province + city pair so birth_place (a combined «استان-شهر»
 * label value validated by FormOptionValue('city', 'province')) passes both
 * structural and completion validation.
 */
function seedLocationOptions(): void
{
    FormOption::firstOrCreate(
        ['group' => 'province', 'value' => 'tehran'],
        ['label' => 'تهران', 'sort_order' => 0, 'is_active' => true],
    );

    FormOption::firstOrCreate(
        ['group' => 'city', 'value' => 'tehran'],
        ['label' => 'تهران', 'parent_value' => 'tehran', 'sort_order' => 0, 'is_active' => true],
    );
}

/**
 * Create an extra questionnaire for OTP-related helpers when needed.
 */
function createUserWithPermissions(array $permissionNames = []): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'name' => 'test-role-'.uniqid(),
        'display_name' => 'Test Role',
        'is_active' => true,
    ]);

    $group = PermissionGroup::firstOrCreate(
        ['slug' => 'test'],
        ['name' => 'Test Group', 'sort_order' => 999],
    );

    foreach ($permissionNames as $name) {
        $permission = Permission::firstOrCreate(
            ['name' => $name],
            ['display_name' => $name, 'group_id' => $group->id],
        );
        $role->permissions()->attach($permission);
    }

    $user->assignRole($role->id, true);

    return $user;
}

/**
 * Issue (and cache) an access grant token for the given entity by uuid.
 */
function grantToken(string $uuid, GrantPurpose $purpose = GrantPurpose::Edit): string
{
    static $tokens = [];

    $tokens[$uuid][$purpose->value] ??= app(OtpService::class)->issueGrant(
        Questionnaire::where('uuid', $uuid)->firstOrFail(),
        'mobile',
        OtpContext::AccessProtected,
        $purpose,
    );

    return $tokens[$uuid][$purpose->value];
}
