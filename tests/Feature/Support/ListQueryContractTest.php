<?php

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Authorization\Models\Role;
use App\Domains\Cv\Models\Cv;
use App\Domains\Employee\Models\Employee;
use App\Domains\FormOptions\Models\FormOption;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = createUserWithPermissions([
        'employee.list',
        'user.view',
        'role.view',
        'cv.view',
    ]);
});

describe('ListQuery contract', function () {
    describe('employees', function () {
        it('searches across the whitelisted columns with the filter param', function () {
            $hit = Employee::factory()->create(['first_name' => 'آرش']);
            Employee::factory()->create(['first_name' => 'سارا']);

            actingAs($this->admin)
                ->getJson('/api/employees?filter=آرش')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $hit->id);
        });

        it('escapes LIKE wildcards in the search term', function () {
            // A literal `%` or `_` in the term must not act as a wildcard and
            // match unrelated rows.
            Employee::factory()->create(['personnel_code' => '10001']);
            Employee::factory()->create(['personnel_code' => '10002']);

            actingAs($this->admin)
                ->getJson('/api/employees?filter=100%_')
                ->assertOk()
                ->assertJsonCount(0, 'data');
        });

        it('filters by status', function () {
            $active = Employee::factory()->create(['employment_status' => 'active']);
            Employee::factory()->create(['employment_status' => 'inactive']);

            actingAs($this->admin)
                ->getJson('/api/employees?status=active')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $active->id);
        });

        it('excludes by the negated status param', function () {
            Employee::factory()->create(['employment_status' => 'active']);
            $other = Employee::factory()->create(['employment_status' => 'inactive']);

            actingAs($this->admin)
                ->getJson('/api/employees?status_not=active')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $other->id);
        });

        it('sorts ascending and descending', function () {
            Employee::factory()->create(['personnel_code' => '00001']);
            $last = Employee::factory()->create(['personnel_code' => '00002']);

            actingAs($this->admin)
                ->getJson('/api/employees?sort=personnel_code&order=asc')
                ->assertOk()
                ->assertJsonPath('data.0.personnel_code', '00001');

            actingAs($this->admin)
                ->getJson('/api/employees?sort=personnel_code&order=desc')
                ->assertOk()
                ->assertJsonPath('data.0.id', $last->id);
        });

        it('maps the combined full_name sort id to the name column', function () {
            $alpha = Employee::factory()->create(['first_name' => 'آلفا']);
            Employee::factory()->create(['first_name' => 'یونس']);

            actingAs($this->admin)
                ->getJson('/api/employees?sort=full_name&order=asc')
                ->assertOk()
                ->assertJsonPath('data.0.id', $alpha->id);
        });

        it('falls back to the default sort for unknown sort columns', function () {
            Employee::factory()->create();

            // Must not 500 and must fall back to the personnel_code default.
            actingAs($this->admin)
                ->getJson('/api/employees?sort=not_a_column&order=asc')
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });

        it('clamps per_page', function () {
            Employee::factory()->create();

            actingAs($this->admin)
                ->getJson('/api/employees?per_page=9999')
                ->assertOk()
                ->assertJsonPath('meta.per_page', 50);
        });
    });

    describe('users', function () {
        it('searches name and email', function () {
            $user = User::factory()->create(['name' => 'UniqueName']);
            User::factory()->create(['name' => 'Other']);

            actingAs($this->admin)
                ->getJson('/api/users?filter=UniqueName')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $user->id);
        });

        it('filters by role and is_active', function () {
            $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
            $inRole = User::factory()->create(['is_active' => true]);
            $inRole->roles()->attach($role->id);
            $inactive = User::factory()->create(['is_active' => false]);
            $inactive->roles()->attach($role->id);

            actingAs($this->admin)
                ->getJson('/api/users?role='.$role->id)
                ->assertOk()
                ->assertJsonCount(2, 'data');

            actingAs($this->admin)
                ->getJson('/api/users?role='.$role->id.'&is_active=1')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $inRole->id);
        });

        it('sorts by name asc by default', function () {
            User::factory()->create(['name' => 'Beta']);
            User::factory()->create(['name' => 'Alpha']);

            actingAs($this->admin)
                ->getJson('/api/users')
                ->assertOk()
                ->assertJsonPath('data.0.name', 'Alpha');
        });

        it('exposes a lightweight role options endpoint for dropdowns', function () {
            // createUserWithPermissions attaches its own test role, so the
            // created role must be found among the options by id.
            $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);

            $response = actingAs($this->admin)
                ->getJson('/api/roles/options')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'label']]])
                ->json('data');

            expect(collect($response)->pluck('id'))->toContain($role->id);
        });

        it('exposes a lightweight user options endpoint', function () {
            User::factory()->create(['name' => 'Options User']);

            actingAs($this->admin)
                ->getJson('/api/users/options')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'label']]]);
        });
    });

    describe('roles', function () {
        it('searches name and display_name', function () {
            Role::create(['name' => 'manager', 'display_name' => 'مدیر', 'is_active' => true]);
            Role::create(['name' => 'hr', 'display_name' => 'منابع', 'is_active' => true]);

            actingAs($this->admin)
                ->getJson('/api/roles?filter=مدیر')
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });

        it('filters by is_active', function () {
            // The beforeEach helper attaches its own active test role.
            Role::create(['name' => 'off', 'display_name' => 'Off', 'is_active' => false]);

            actingAs($this->admin)
                ->getJson('/api/roles?is_active=1')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', $this->admin->roles->first()->name);
        });

        it('sorts by display_name and falls back for unknown sorts', function () {
            Role::create(['name' => 'z', 'display_name' => 'Zeta', 'is_active' => true]);
            $first = Role::create(['name' => 'a', 'display_name' => 'Alpha', 'is_active' => true]);

            actingAs($this->admin)
                ->getJson('/api/roles')
                ->assertOk()
                ->assertJsonPath('data.0.id', $first->id);

            actingAs($this->admin)
                ->getJson('/api/roles?sort=injection;drop&order=asc')
                ->assertOk();
        });
    });

    describe('admin form options', function () {
        it('searches label and value with the unified filter param', function () {
            $manager = createUserWithPermissions(['form-options.manage']);
            FormOption::create([
                'group' => 'gender',
                'value' => 'needle_value',
                'label' => 'سوزن',
                'sort_order' => 0,
                'is_active' => true,
            ]);
            FormOption::create([
                'group' => 'gender',
                'value' => 'other',
                'label' => 'دیگر',
                'sort_order' => 1,
                'is_active' => true,
            ]);

            actingAs($manager)
                ->getJson('/api/admin/form-options?filter=needle_value')
                ->assertOk()
                ->assertJsonCount(1, 'data');

            actingAs($manager)
                ->getJson('/api/admin/form-options?filter='.urlencode('سوزن'))
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });

        it('keeps the fixed sort order', function () {
            $manager = createUserWithPermissions(['form-options.manage']);
            FormOption::create([
                'group' => 'gender',
                'value' => 'b',
                'label' => 'B',
                'sort_order' => 2,
                'is_active' => true,
            ]);
            $first = FormOption::create([
                'group' => 'gender',
                'value' => 'a',
                'label' => 'A',
                'sort_order' => 1,
                'is_active' => true,
            ]);

            actingAs($manager)
                ->getJson('/api/admin/form-options?group=gender')
                ->assertOk()
                ->assertJsonPath('data.0.id', $first->id);
        });
    });

    describe('cv bank', function () {
        it('searches the four whitelisted columns and filters by status', function () {
            Cv::create([
                'first_name' => 'RarestName',
                'last_name' => 'Person',
                'email' => 'rare@example.com',
                'mobile' => '09120000001',
                'status' => 'submitted',
            ]);

            actingAs($this->admin)
                ->getJson('/api/cv/bank?filter=RarestName')
                ->assertOk()
                ->assertJsonCount(1, 'data');

            actingAs($this->admin)
                ->getJson('/api/cv/bank?status=approved')
                ->assertOk()
                ->assertJsonCount(0, 'data');
        });

        it('maps the combined full_name sort id to the name column', function () {
            $alpha = Cv::create([
                'first_name' => 'آلفا',
                'last_name' => 'Person',
                'email' => 'alpha@example.com',
                'mobile' => '09120000003',
                'status' => 'submitted',
            ]);
            Cv::create([
                'first_name' => 'یونس',
                'last_name' => 'Person',
                'email' => 'y@example.com',
                'mobile' => '09120000004',
                'status' => 'submitted',
            ]);

            actingAs($this->admin)
                ->getJson('/api/cv/bank?sort=full_name&order=asc')
                ->assertOk()
                ->assertJsonPath('data.0.id', $alpha->id);
        });

        it('rejects unknown sort columns without erroring', function () {
            Cv::create([
                'first_name' => 'Any',
                'last_name' => 'Person',
                'email' => 'any@example.com',
                'mobile' => '09120000002',
                'status' => 'submitted',
            ]);

            actingAs($this->admin)
                ->getJson('/api/cv/bank?sort=bogus_column')
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });
    });

    describe('audit logs', function () {
        it('accepts the unified filter param and the legacy search alias', function () {
            $viewer = createUserWithPermissions(['audit.view']);
            AuditLog::factory()->create(['description' => 'unified-term-here']);

            actingAs($viewer)
                ->getJson('/api/audit-logs?filter=unified-term')
                ->assertOk()
                ->assertJsonCount(1, 'data');

            actingAs($viewer)
                ->getJson('/api/audit-logs?search=unified-term')
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });

        it('accepts sort + order and the legacy dash prefix', function () {
            $viewer = createUserWithPermissions(['audit.view']);
            AuditLog::factory()->count(2)->create();

            actingAs($viewer)
                ->getJson('/api/audit-logs?sort=event&order=asc')
                ->assertOk()
                ->assertJsonCount(2, 'data');

            actingAs($viewer)
                ->getJson('/api/audit-logs?sort=-created_at')
                ->assertOk()
                ->assertJsonCount(2, 'data');
        });

        it('filters by the negated category param', function () {
            $viewer = createUserWithPermissions(['audit.view']);
            AuditLog::factory()->create(['category' => 'employee']);
            $other = AuditLog::factory()->create(['category' => 'auth']);

            actingAs($viewer)
                ->getJson('/api/audit-logs?category_not=employee')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $other->id);
        });

        it('filters by the date range', function () {
            $viewer = createUserWithPermissions(['audit.view']);
            $inRange = AuditLog::factory()->create([
                'created_at' => now()->subDays(2),
            ]);
            AuditLog::factory()->create(['created_at' => now()->subDays(30)]);

            actingAs($viewer)
                ->getJson('/api/audit-logs?date_from='.now()->subDays(5)->toDateString().'&date_to='.now()->toDateString())
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $inRange->id);
        });
    });
});
