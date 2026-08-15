<?php

namespace App\Console\Commands;

use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Services\AuthorizationVersion;
use App\Domains\Authorization\Services\PermissionRegistrySynchronizer;
use App\Models\User;
use Closure;
use Illuminate\Console\Command;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;

use function Laravel\Prompts\search;

class AuthorizationGrantAll extends Command
{
    protected $signature = 'authorization:grant-all
                            {roles?* : One or more role names or ids to grant every registered permission to. When omitted, a role is selected interactively.}
                            {--sync : Replace each role\'s existing rules with allow-all instead of adding missing allows}
                            {--dry-run : Show what would be granted without writing}
                            {--user=* : User id, email, or username to assign the granted role(s) to. Repeatable.}
                            {--pick-user : Prompt to search and pick a single user to assign the role(s) to}';

    protected $description = 'Grant every registered permission to one or more roles and optionally assign those roles to users';

    public function handle(PermissionRegistrySynchronizer $synchronizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $replace = (bool) $this->option('sync');

        // 1. Ensure the registry exists in the DB (creates new groups/permissions),
        //    then resolve the roles and users to target.
        $result = $synchronizer->sync(dryRun: $dryRun);

        // In dry-run the synchronizer returns unsaved models for brand-new
        // permissions (no id yet), so use the registered count for display and
        // the resolved ids for the actual grant.
        $registeredCount = count($result['permission_models']);
        $permissionIds = collect($result['permission_models'])
            ->map(fn (Permission $permission) => $permission->id)
            ->filter()
            ->values()
            ->all();

        $roles = $this->resolveRoles();
        $users = $this->resolveUsers();

        if ($roles === []) {
            $this->error('No roles to grant permissions to.');

            return self::FAILURE;
        }

        if ($users === null) {
            $this->error('No users to assign the granted role(s) to.');

            return self::FAILURE;
        }

        if ($registeredCount === 0) {
            $this->error('No registered permissions found to grant.');

            return self::FAILURE;
        }

        $changed = false;

        foreach ($roles as $role) {
            $hasChanges = $this->hasChanges($role, $permissionIds, $replace);

            if ($dryRun) {
                $this->line(sprintf(
                    '  <info>%s</info> would receive <comment>%d</comment> permission(s) (%s).',
                    $role->name,
                    $registeredCount,
                    $replace ? 'replacing existing rules' : 'adding missing allows',
                ));

                continue;
            }

            if (! $hasChanges) {
                $this->line(sprintf('  <info>%s</info> already has every registered permission.', $role->name));

                continue;
            }

            $replace
                ? $role->syncPermissions($permissionIds)
                : $role->grantPermissions($permissionIds);

            $role->users->each(fn (User $user) => $user->flushPermissionCache());

            $this->line(sprintf(
                '  <info>%s</info> granted <comment>%d</comment> permission(s) (%s).',
                $role->name,
                count($permissionIds),
                $replace ? 'replaced all rules' : 'added missing allows',
            ));

            $changed = true;
        }

        foreach ($users as $user) {
            if ($dryRun) {
                $roleNames = collect($roles)->map(fn (Role $role) => $role->name)->implode(', ');

                $this->line(sprintf(
                    '  <info>%s</info> (%s) would receive role(s): <comment>%s</comment>.',
                    $user->name,
                    $user->email,
                    $roleNames,
                ));

                continue;
            }

            foreach ($roles as $role) {
                if ($user->roles()->where('roles.id', $role->id)->exists()) {
                    continue;
                }

                $user->assignRole($role->id);
            }

            $this->line(sprintf(
                '  <info>%s</info> (%s) assigned <comment>%d</comment> role(s).',
                $user->name,
                $user->email,
                count($roles),
            ));

            $changed = true;
        }

        if ($changed) {
            app(AuthorizationVersion::class)->bump();
        }

        if ($dryRun) {
            $this->info('Dry run — nothing was written to the database.');
        } else {
            $this->info($changed ? 'Done.' : 'No changes were required.');
        }

        return self::SUCCESS;
    }

    /**
     * Whether granting to the role would write anything, given the mode.
     *
     * @param  array<int, int>  $permissionIds
     */
    private function hasChanges(Role $role, array $permissionIds, bool $replace): bool
    {
        $granted = $role->permissions()->pluck('permissions.id')->all();

        if ($replace) {
            return count($granted) !== count($permissionIds)
                || array_diff($granted, $permissionIds) !== []
                || array_diff($permissionIds, $granted) !== [];
        }

        return array_diff($permissionIds, $granted) !== [];
    }

    /**
     * Resolve target roles from CLI arguments, or prompt the operator to
     * search and pick a single role when none were given.
     *
     * @return array<int, Role>
     */
    private function resolveRoles(): array
    {
        $arguments = $this->argument('roles') ?? [];

        if ($arguments !== []) {
            $roles = [];

            foreach ($arguments as $argument) {
                $role = ctype_digit((string) $argument)
                    ? Role::find((int) $argument)
                    : Role::where('name', $argument)->first();

                if ($role === null) {
                    $this->error("Role [{$argument}] was not found.");

                    continue;
                }

                $roles[] = $role;
            }

            return $roles;
        }

        $roleId = $this->searchOrFail(
            'Select a role to grant every registered permission to',
            fn (string $value) => $this->roleSearchOptions($value),
            'No role was given and the console is not interactive. Pass one or more role names or ids.',
        );

        if ($roleId === null) {
            return [];
        }

        $role = Role::find($roleId);

        if ($role === null) {
            $this->error('The selected role could not be loaded.');

            return [];
        }

        return [$role];
    }

    /**
     * Resolve target users from --user options, or prompt to pick a single
     * user when --pick-user is set. Returns an empty list when no user is
     * requested at all (role-only mode), or null when a requested user could
     * not be found.
     *
     * @return array<int, User>|null
     */
    private function resolveUsers(): ?array
    {
        $pickUser = (bool) $this->option('pick-user');
        $userArguments = $this->option('user') ?? [];

        if ($userArguments === [] && ! $pickUser) {
            return [];
        }

        if ($userArguments !== []) {
            $users = [];

            foreach ($userArguments as $argument) {
                $user = $this->findUser($argument);

                if ($user === null) {
                    $this->error("User [{$argument}] was not found.");

                    return null;
                }

                $users[] = $user;
            }

            return $users;
        }

        $userId = $this->searchOrFail(
            'Select a user to assign the granted role(s) to',
            fn (string $value) => $this->userSearchOptions($value),
            'No user was given and the console is not interactive. Pass --user or omit it for a role-only run.',
        );

        if ($userId === null) {
            return [];
        }

        $user = User::find($userId);

        if ($user === null) {
            $this->error('The selected user could not be loaded.');

            return [];
        }

        return [$user];
    }

    /**
     * @return array<int|string, string>
     */
    private function roleSearchOptions(string $search): array
    {
        $query = Role::query()->orderBy('display_name');

        if (trim($search) !== '') {
            $needle = trim($search);

            $query->where(function ($q) use ($needle) {
                $q->where('name', 'like', "%{$needle}%")
                    ->orWhere('display_name', 'like', "%{$needle}%")
                    ->orWhere('id', 'like', "%{$needle}%");
            });
        }

        return $query->get()
            ->mapWithKeys(fn (Role $role) => [$role->id => "[{$role->id}] {$role->name} — {$role->display_name}"])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function userSearchOptions(string $search): array
    {
        $query = User::query()->orderBy('name');

        if (trim($search) !== '') {
            $needle = trim($search);

            $query->where(function ($q) use ($needle) {
                $q->where('name', 'like', "%{$needle}%")
                    ->orWhere('email', 'like', "%{$needle}%")
                    ->orWhere('username', 'like', "%{$needle}%")
                    ->orWhere('id', 'like', "%{$needle}%");
            });
        }

        return $query->get()
            ->mapWithKeys(fn (User $user) => [$user->id => "[{$user->id}] {$user->name} <{$user->email}>"])
            ->all();
    }

    private function findUser(string $argument): ?User
    {
        if (ctype_digit($argument)) {
            return User::find((int) $argument);
        }

        return User::where('email', $argument)
            ->orWhere('username', $argument)
            ->first();
    }

    /**
     * Run an interactive single-select search. Returns the selected key (the
     * model id) or null when the console is not interactive.
     *
     * @param  Closure(string): array<int|string, string>  $options
     */
    private function searchOrFail(string $label, Closure $options, string $nonInteractiveMessage): int|string|null
    {
        try {
            return search(
                label: $label,
                placeholder: 'Search…',
                options: $options,
                required: 'Please select an item.',
            );
        } catch (NonInteractiveValidationException) {
            $this->error($nonInteractiveMessage);

            return null;
        }
    }
}
