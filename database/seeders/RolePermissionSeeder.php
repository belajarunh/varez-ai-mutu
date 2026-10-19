<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'platform' => [
                'tenants.manage' => 'Manage platform tenants',
                'platform.audit_logs.view' => 'View platform audit logs',
                'platform.roles.manage' => 'Manage platform roles',
            ],
            'tenant' => [
                'panel.access' => 'Access the administration panel',
                'institutions.view' => 'View institution profile',
                'institutions.update' => 'Update institution profile',
                'institutions.archive' => 'Archive institution',
                'units.view' => 'View organizational units',
                'units.create' => 'Create organizational units',
                'units.update' => 'Update organizational units',
                'units.archive' => 'Archive organizational units',
                'units.access_all' => 'Access every organizational unit',
                'members.view' => 'View institution memberships',
                'members.manage' => 'Manage institution memberships and invitations',
                'audit_logs.view' => 'View institution audit logs',
                'roles.manage' => 'Manage institution roles and permissions',
                'projects.view' => 'View accreditation projects',
                'projects.create' => 'Create accreditation projects',
                'projects.manage' => 'Manage accreditation project indicators and team',
                'performance_data.view' => 'View performance datasets and import history',
                'performance_data.import' => 'Import performance data',
                'dashboard.view' => 'View tenant dashboard metrics',
                'billing.view' => 'View billing and subscription status',
                'billing.manage' => 'Manage billing and subscription settings',
                'ai.review' => 'Review AI-generated narratives and evidence summaries',
                'ai.approve' => 'Approve AI-generated outputs for publication or export',
                'ai.export' => 'Export approved AI content and source manifests',
                'documents.view' => 'View accessible institution documents',
                'documents.upload' => 'Upload documents and create versions',
                'documents.manage' => 'Manage document metadata, access and relations',
                'documents.download' => 'Download accessible document versions',
                'documents.verify' => 'Verify or reject document evidence',
                'documents.categories.manage' => 'Manage institution document categories',
                'quality.view' => 'View quality standards, activities, and audit status',
                'quality.standards.manage' => 'Manage quality standards and indicators',
                'quality.activities.manage' => 'Manage PPEPP activities and evidence',
                'quality.activities.approve' => 'Approve completed PPEPP activities',
                'quality.audits.manage' => 'Plan and manage internal quality audits',
                'quality.audits.perform' => 'Complete assigned internal audit checklists',
                'quality.findings.manage' => 'Manage audit findings and evidence',
                'quality.actions.manage' => 'Manage corrective action plans',
                'quality.actions.verify' => 'Verify corrective action effectiveness',
                'quality.policies.manage' => 'Configure quality workflow verification rules',
            ],
        ];

        foreach ($permissions as $scope => $definitions) {
            foreach ($definitions as $slug => $name) {
                Permission::query()->updateOrCreate(
                    ['scope' => $scope, 'slug' => $slug],
                    ['name' => $name],
                );
            }
        }

        $roleDefinitions = [
            'platform' => [
                'platform.super_admin' => [
                    'name' => 'Platform Super Admin',
                    'description' => 'Fiction-free system role for authorized platform operators.',
                    'permissions' => array_keys($permissions['platform']),
                ],
            ],
            'tenant' => [
                'tenant.owner' => [
                    'name' => 'Institution Owner',
                    'description' => 'Institution owner with full tenant-scoped access.',
                    'permissions' => array_keys($permissions['tenant']),
                ],
                'tenant.admin' => [
                    'name' => 'Institution Admin',
                    'description' => 'Institution administrator without institution archival access.',
                    'permissions' => array_values(array_diff(array_keys($permissions['tenant']), [
                        'institutions.archive',
                    ])),
                ],
                'tenant.quality_manager' => [
                    'name' => 'Quality Manager',
                    'description' => 'Quality work access across institution units.',
                    'permissions' => [
                        'panel.access',
                        'institutions.view',
                        'units.view',
                        'units.create',
                        'units.update',
                        'units.archive',
                        'units.access_all',
                        'audit_logs.view',
                        'projects.view',
                        'projects.create',
                        'projects.manage',
                        'performance_data.view',
                        'performance_data.import',
                        'dashboard.view',
                        'billing.view',
                        'billing.manage',
                        'documents.view',
                        'documents.upload',
                        'documents.manage',
                        'documents.download',
                        'documents.verify',
                        'documents.categories.manage',
                        'quality.view',
                        'quality.standards.manage',
                        'quality.activities.manage',
                        'quality.activities.approve',
                        'quality.audits.manage',
                        'quality.audits.perform',
                        'quality.findings.manage',
                        'quality.actions.manage',
                        'quality.actions.verify',
                        'quality.policies.manage',
                    ],
                ],
                'tenant.quality_auditor' => [
                    'name' => 'Quality Auditor',
                    'description' => 'Read quality records and complete assigned internal audit work.',
                    'permissions' => [
                        'quality.view',
                        'dashboard.view',
                        'billing.view',
                        'quality.audits.perform',
                        'quality.findings.manage',
                        'units.view',
                        'documents.view',
                    ],
                ],
                'tenant.member' => [
                    'name' => 'Institution Member',
                    'description' => 'Basic institution panel and profile access.',
                    'permissions' => [
                        'panel.access',
                        'institutions.view',
                        'units.view',
                        'projects.view',
                        'dashboard.view',
                        'billing.view',
                        'documents.view',
                        'documents.download',
                        'quality.view',
                    ],
                ],
            ],
        ];

        foreach ($roleDefinitions as $scope => $definitions) {
            foreach ($definitions as $slug => $definition) {
                $role = Role::query()->updateOrCreate(
                    ['scope' => $scope, 'slug' => $slug],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'is_system' => true,
                    ],
                );

                $permissionIds = Permission::query()
                    ->where('scope', $scope)
                    ->whereIn('slug', $definition['permissions'])
                    ->pluck('id');

                $role->permissions()->sync($permissionIds);
            }
        }
    }
}
