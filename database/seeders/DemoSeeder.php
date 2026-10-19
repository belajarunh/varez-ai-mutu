<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\InstitutionUser;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Fictional demo data may only be seeded in local or testing environments.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'pengguna-demo@example.test'],
            [
                'name' => 'Pengguna Demo Fiktif',
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );

        $institution = Institution::query()->firstOrNew(['slug' => 'institusi-contoh-fiktif']);
        $institution->fill([
            'name' => 'Institusi Contoh Fiktif',
            'institution_type' => 'other',
        ]);
        $institution->forceFill(['status' => Institution::STATUS_ACTIVE])->save();

        $ownerRole = Role::query()
            ->where('scope', 'tenant')
            ->where('slug', 'tenant.owner')
            ->firstOrFail();

        InstitutionUser::query()->firstOrCreate(
            ['institution_id' => $institution->getKey(), 'user_id' => $user->getKey()],
            [
                'role_id' => $ownerRole->getKey(),
                'role_scope' => 'tenant',
                'status' => InstitutionUser::STATUS_ACTIVE,
                'joined_at' => now(),
            ],
        );

        OrganizationalUnit::query()->firstOrCreate(
            ['institution_id' => $institution->getKey(), 'code' => 'UNIT-DEMO'],
            [
                'unit_type' => 'other',
                'name' => 'Unit Contoh Fiktif',
                'status' => OrganizationalUnit::STATUS_ACTIVE,
            ],
        );
    }
}
