<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GrantPlatformSuperAdmin extends Command
{
    protected $signature = 'platform:grant-super-admin {email} {--force : Skip the interactive confirmation}';

    protected $description = 'Grant the platform super-admin role to an existing, verified user.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $role = Role::query()->where('scope', 'platform')->where('slug', 'platform.super_admin')->first();

        if ($user === null || $user->email_verified_at === null || $user->status !== 'active' || $role === null) {
            $this->error('A verified, active user and seeded platform role are required.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Grant platform super-admin access to {$user->email}?")) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($user, $role): void {
            $user->platformRoles()->syncWithoutDetaching([
                $role->getKey() => ['role_scope' => 'platform'],
            ]);

            AuditLog::query()->create([
                'actor_user_id' => null,
                'action' => 'platform.super_admin.granted',
                'subject_type' => User::class,
                'subject_id' => (string) $user->getKey(),
                'after_json' => ['role_id' => $role->getKey()],
                'correlation_id' => (string) Str::uuid(),
            ]);
        });

        $this->info('Platform super-admin role granted and recorded in the audit log.');

        return self::SUCCESS;
    }
}
