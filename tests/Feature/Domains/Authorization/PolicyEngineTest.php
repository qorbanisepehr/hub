<?php

use App\Contracts\Authorization;
use App\Domains\Authorization\Engine\AuthorizationContext;
use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Exceptions\AuthorizationScopeException;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Policies\ConditionEvaluator;
use App\Domains\Authorization\Policies\Operator;
use App\Domains\Authorization\Policies\PolicyValidator;
use App\Domains\Authorization\Policies\QueryTranslator;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use App\Models\User;

function policyPermission(string $name, string $groupSlug = 'employee'): Permission
{
    $group = PermissionGroup::updateOrCreate(['slug' => $groupSlug], ['name' => ucfirst($groupSlug)]);

    return Permission::updateOrCreate(['name' => $name], ['display_name' => $name, 'group_id' => $group->id]);
}

beforeEach(function () {
    $this->evaluator = app(ConditionEvaluator::class);
    $this->validator = app(PolicyValidator::class);
    $this->translator = app(QueryTranslator::class);
    $this->authorization = app(Authorization::class);
    $this->user = User::factory()->create();
});

describe('Operator registry', function () {
    it('equals uses loose equality', function () {
        expect(Operator::Equals->applies('12', 12))->toBeTrue();
        expect(Operator::Equals->applies(null, 12))->toBeFalse();
        expect(Operator::NotEquals->applies(null, 12))->toBeTrue();
    });

    it('in and not_in match against arrays', function () {
        expect(Operator::In->applies(3, [1, 2, 3]))->toBeTrue();
        expect(Operator::In->applies('3', [1, 2, 3]))->toBeTrue();
        expect(Operator::NotIn->applies(9, [1, 2, 3]))->toBeTrue();
    });

    it('contains works for strings and arrays', function () {
        expect(Operator::Contains->applies('hr-manager', 'manager'))->toBeTrue();
        expect(Operator::Contains->applies(['a', 'b'], 'a'))->toBeTrue();
        expect(Operator::NotContains->applies('hr-manager', 'security'))->toBeTrue();
    });

    it('compares numerically when both sides are numeric', function () {
        expect(Operator::GreaterThan->applies(10, 5))->toBeTrue();
        expect(Operator::GreaterThanOrEqual->applies(5, 5))->toBeTrue();
        expect(Operator::LessThan->applies(5, 10))->toBeTrue();
        expect(Operator::LessThanOrEqual->applies('5', 5))->toBeTrue();
    });

    it('treats null and empty string as missing for null/exists operators', function () {
        expect(Operator::IsNull->applies(null, null))->toBeTrue();
        expect(Operator::IsNull->applies('', null))->toBeTrue();
        expect(Operator::Exists->applies('value', null))->toBeTrue();
        expect(Operator::Exists->applies(null, null))->toBeFalse();
    });
});

describe('Condition evaluator', function () {
    it('evaluates a literal equals leaf against the resource', function () {
        $employee = Employee::factory()->create(['employment_status' => 'active']);

        $node = ['all' => [
            ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
        ]];

        expect($this->evaluator->evaluates($node, $this->user, $employee, null))->toBeTrue();
        expect($this->evaluator->evaluates($node, $this->user, Employee::factory()->create(['employment_status' => 'inactive']), null))->toBeFalse();
    });

    it('compares a resource attribute against an actor attribute', function () {
        $employee = Employee::factory()->create(['user_id' => $this->user->id]);

        $node = ['all' => [
            ['attribute' => 'employee.user_id', 'operator' => 'equals', 'value_source' => 'actor', 'value' => 'id'],
        ]];

        expect($this->evaluator->evaluates($node, $this->user, $employee, null))->toBeTrue();
        expect($this->evaluator->evaluates($node, $this->user, Employee::factory()->create(), null))->toBeFalse();
    });

    it('reads values from the authorization context', function () {
        $employee = Employee::factory()->create(['employment_status' => 'active']);
        $context = AuthorizationContext::make(['expected_status' => 'active']);

        $node = ['all' => [
            ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'context', 'value' => 'expected_status'],
        ]];

        expect($this->evaluator->evaluates($node, $this->user, $employee, $context))->toBeTrue();
        expect($this->evaluator->evaluates($node, $this->user, $employee, null))->toBeFalse();
    });

    it('requires every child inside an all group', function () {
        $employee = Employee::factory()->create(['employment_status' => 'active', 'employment_type' => 'official']);

        $node = ['all' => [
            ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
            ['attribute' => 'employee.employment_type', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'official'],
        ]];

        expect($this->evaluator->evaluates($node, $this->user, $employee, null))->toBeTrue();
        expect($this->evaluator->evaluates($node, $this->user, Employee::factory()->create(['employment_status' => 'active', 'employment_type' => 'contractual']), null))->toBeFalse();
    });

    it('matches when any child matches inside an any group', function () {
        $employee = Employee::factory()->create(['employment_status' => 'active']);

        $node = ['all' => [
            ['any' => [
                ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
                ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'security'],
            ]],
        ]];

        expect($this->evaluator->evaluates($node, $this->user, $employee, null))->toBeTrue();
    });

    it('negates the child inside a not group', function () {
        $employee = Employee::factory()->create(['employment_status' => 'active']);

        $node = ['not' => [
            'attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'security',
        ]];

        expect($this->evaluator->evaluates($node, $this->user, $employee, null))->toBeTrue();
    });

    it('traverses relationships for related attributes', function () {
        $category = DocumentCategory::create(['name' => 'Insurance', 'slug' => 'insurance']);
        $document = Document::factory()->create(['category_id' => $category->id]);

        $node = ['all' => [
            ['attribute' => 'document.category.slug', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'insurance'],
        ]];

        expect($this->evaluator->evaluates($node, $this->user, $document, null))->toBeTrue();
    });

    it('treats an unknown operator as not matching', function () {
        $employee = Employee::factory()->create();

        $node = ['all' => [
            ['attribute' => 'employee.id', 'operator' => 'eq', 'value_source' => 'literal', 'value' => 1],
        ]];

        expect($this->evaluator->evaluates($node, $this->user, $employee, null))->toBeFalse();
    });
});

describe('Policy validator', function () {
    it('accepts a valid tree', function () {
        $node = ['all' => [
            ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
            ['any' => [
                ['attribute' => 'employee.employment_type', 'operator' => 'in', 'value_source' => 'literal', 'value' => ['official', 'contractual']],
            ]],
        ]];

        expect($this->validator->errors($node, 'employee'))->toBeEmpty();
    });

    it('rejects an unknown attribute', function () {
        $node = ['all' => [
            ['attribute' => 'employee.department_id', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 1],
        ]];

        $errors = $this->validator->errors($node, 'employee');

        expect(collect($errors)->contains(fn (string $error) => str_contains($error, 'unknown attribute')))->toBeTrue();
    });

    it('rejects an operator unsupported for the attribute type', function () {
        $node = ['all' => [
            ['attribute' => 'employee.id', 'operator' => 'contains', 'value_source' => 'literal', 'value' => '1'],
        ]];

        $errors = $this->validator->errors($node, 'employee');

        expect(collect($errors)->contains(fn (string $error) => str_contains($error, 'is not supported')))->toBeTrue();
    });

    it('rejects an unknown value source', function () {
        $node = ['all' => [
            ['attribute' => 'employee.id', 'operator' => 'equals', 'value_source' => 'mysql', 'value' => 1],
        ]];

        $errors = $this->validator->errors($node, 'employee');

        expect(collect($errors)->contains(fn (string $error) => str_contains($error, 'unknown value source')))->toBeTrue();
    });
});

describe('Query translator', function () {
    it('translates an equals leaf to a where clause', function () {
        $query = Employee::query();
        $node = ['all' => [
            ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
        ]];

        $this->translator->apply($query, $this->user, 'employee', $node, null);

        expect($query->toSql())->toContain('"employment_status" = ?');
        expect($query->getBindings())->toContain('active');
    });

    it('translates in to whereIn', function () {
        $query = Employee::query();
        $node = ['all' => [
            ['attribute' => 'employee.id', 'operator' => 'in', 'value_source' => 'literal', 'value' => [1, 2]],
        ]];

        $this->translator->apply($query, $this->user, 'employee', $node, null);

        expect($query->toSql())->toContain('"id" in (?, ?)');
    });

    it('builds an orWhere group for any', function () {
        $query = Employee::query();
        $node = ['all' => [
            ['any' => [
                ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
                ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'security'],
            ]],
        ]];

        $this->translator->apply($query, $this->user, 'employee', $node, null);

        $sql = $query->toSql();
        expect($sql)->toContain('or "employment_status" = ?');
    });

    it('throws on a non-queryable attribute', function () {
        $query = Document::query();
        $node = ['all' => [
            ['attribute' => 'document.category.slug', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'insurance'],
        ]];

        expect(fn () => $this->translator->apply($query, $this->user, 'document', $node, null))
            ->toThrow(AuthorizationScopeException::class);
    });
});

describe('scope()', function () {
    it('narrows the query with an allow policy', function () {
        policyPermission('employee.view');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $role->accessRules()->create([
            'permission_id' => Permission::where('name', 'employee.view')->first()->id,
            'effect' => AccessRuleEffect::Allow,
            'policy' => ['all' => [
                ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
            ]],
        ]);
        $this->user->assignRole($role->id, true);

        Employee::factory()->create(['employment_status' => 'active']);
        Employee::factory()->create(['employment_status' => 'inactive']);

        $result = $this->authorization->scope($this->user, 'employee.view', Employee::query());

        expect($result->count())->toBe(1);
        expect($result->first()->employment_status)->toBe('active');
    });

    it('applies the negation of a deny policy', function () {
        policyPermission('employee.view');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $role->accessRules()->create([
            'permission_id' => Permission::where('name', 'employee.view')->first()->id,
            'effect' => AccessRuleEffect::Allow,
        ]);
        $role->accessRules()->create([
            'permission_id' => Permission::where('name', 'employee.view')->first()->id,
            'effect' => AccessRuleEffect::Deny,
            'policy' => ['all' => [
                ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'security'],
            ]],
        ]);
        $this->user->assignRole($role->id, true);

        Employee::factory()->create(['employment_status' => 'active']);
        Employee::factory()->create(['employment_status' => 'security']);

        $result = $this->authorization->scope($this->user, 'employee.view', Employee::query());

        expect($result->count())->toBe(1);
        expect($result->first()->employment_status)->toBe('active');
    });

    it('leaves the query untouched on an unconditional allow', function () {
        policyPermission('employee.view');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $role->permissions()->attach(Permission::where('name', 'employee.view')->first()->id);
        $this->user->assignRole($role->id, true);

        $query = Employee::query();

        $result = $this->authorization->scope($this->user, 'employee.view', $query);

        expect($result->getQuery()->wheres)->toBeEmpty();
    });

    it('returns nothing when no rule grants access', function () {
        policyPermission('employee.view');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $this->user->assignRole($role->id, true);

        Employee::factory()->create();

        $result = $this->authorization->scope($this->user, 'employee.view', Employee::query());

        expect($result->toSql())->toContain('0 = 1');
    });

    it('returns nothing on an unconditional deny', function () {
        policyPermission('employee.view');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $role->accessRules()->create([
            'permission_id' => Permission::where('name', 'employee.view')->first()->id,
            'effect' => AccessRuleEffect::Deny,
        ]);
        $this->user->assignRole($role->id, true);

        $result = $this->authorization->scope($this->user, 'employee.view', Employee::query());

        expect($result->toSql())->toContain('0 = 1');
    });

    it('throws when an allow policy is not queryable', function () {
        policyPermission('document.view', 'document');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $role->accessRules()->create([
            'permission_id' => Permission::where('name', 'document.view')->first()->id,
            'effect' => AccessRuleEffect::Allow,
            'policy' => ['all' => [
                ['attribute' => 'document.category.slug', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'insurance'],
            ]],
        ]);
        $this->user->assignRole($role->id, true);

        expect(fn () => $this->authorization->scope($this->user, 'document.view', Document::query()))
            ->toThrow(AuthorizationScopeException::class);
    });
});
