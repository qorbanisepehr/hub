<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Services\AiProviderService;
use App\Domains\Ai\Value\AiDefaultProvider;
use App\Domains\Ai\Value\AiModel;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\FormOptions\Models\FormOption;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
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
 * Create a personnel document category for tests.
 */
function personnelDocumentCategory(string $slug, string $name = 'Personnel'): DocumentCategory
{
    return DocumentCategory::create([
        'name' => $name,
        'slug' => $slug,
        'type' => DocumentCategory::TYPE_PERSONNEL,
    ]);
}

/**
 * Seed the option groups referenced by questionnaire/cv validation rules.
 */
function seedFormOptions(?array $groups = null): void
{
    app(FormOptionSeeder::class)->run($groups);
}

/**
 * Seed a minimal province + city pair so birth_place (a city option value
 * validated by FormOptionValue('city', 'province')) passes both structural
 * and completion validation.
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
 * Seed an AI provider through the store and make it the default.
 *
 * Seeded through the store rather than a factory: there is no provider factory
 * any more and there should not be one. A factory that writes a document has to
 * reach past the store to do it, and then it is seeding state the application
 * itself cannot write — so a green test would be proving something the product
 * cannot do.
 *
 * This lives here rather than in one test file because Pest loads every test
 * file into a single process, so a helper defined in one file is silently
 * unavailable when another file is run on its own.
 */
function seedSummaryProvider(
    string $name = 'local',
    bool $isActive = true,
    bool $makeDefault = true,
    AiDriver $driver = AiDriver::Ollama,
): AiProvider {
    $provider = AiProvider::make($name, $driver)->asActive($isActive);

    $store = app(SystemSettingsRepositoryInterface::class);
    $store->put($provider);

    // Only claim the pointer when asked. There is exactly one default row, so a
    // second provider seeded for a comparison would silently steal it and leave
    // the tests that depend on the first resolving against the wrong gateway.
    if ($makeDefault) {
        $store->put(AiDefaultProvider::make($name));
    }

    return $provider;
}

/**
 * Add a model to a stored provider, re-reading it first.
 *
 * The re-read is load-bearing, not tidiness. A provider and its model list are
 * one aggregate, so `addModel()` checks the duplicate-name rule against the
 * document it is handed: a caller holding the value from *before* an earlier
 * write is one model behind, and the duplicate it should have refused gets
 * stored silently.
 */
function seedSummaryModel(string $providerName, string $modelName, bool $isActive = true, array $capabilities = []): AiModel
{
    $service = app(AiProviderService::class);

    $service->addModel(
        $service->provider($providerName),
        new AiModel($modelName, null, $capabilities, $isActive),
    );

    return $service->provider($providerName)->model($modelName);
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
