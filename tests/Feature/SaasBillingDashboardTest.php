<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\InstitutionSubscription;
use App\Models\InstitutionUser;
use App\Models\Role;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\Billing\DashboardMetricsService;
use App\Services\Billing\PaymentGatewayService;
use App\Services\Billing\UsageQuotaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaaSBillingDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_usage_quota_blocks_excess_ai_usage(): void
    {
        $institution = $this->createInstitution('saas-a');
        $owner = User::factory()->create();
        $role = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->attachMembership($institution, $owner, $role);

        $plan = SaasPlan::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'billing_interval' => 'monthly',
            'price' => 150000,
            'currency' => 'IDR',
            'status' => 'active',
            'ai_quota_limit' => 1000,
            'storage_quota_mb' => 1000,
            'users_quota_limit' => 20,
            'projects_quota_limit' => 10,
            'unit_quota_limit' => 5,
        ]);

        $service = app(UsageQuotaService::class);
        $service->changePlan($institution, $plan, $owner);
        $service->recordUsage($institution, $owner, 'ai_tokens', 700, 'tokens', 'ai_generation', 1, '2026-10');

        $check = $service->checkQuota($institution, 'ai_tokens', 400, '2026-10');

        $this->assertFalse($check['allowed']);
        $this->assertSame(300.0, round((float) $check['remaining'], 1));
        $this->assertDatabaseHas('usage_ledgers', [
            'institution_id' => $institution->getKey(),
            'usage_type' => 'ai_tokens',
            'quantity' => 700,
        ]);
    }

    public function test_subscription_plan_change_and_dashboard_permission_are_scoped(): void
    {
        $institution = $this->createInstitution('saas-b');
        $owner = User::factory()->create();
        $role = Role::query()->where('scope', 'tenant')->where('slug', 'tenant.owner')->firstOrFail();
        $this->attachMembership($institution, $owner, $role);

        $basic = SaasPlan::query()->create([
            'code' => 'basic',
            'name' => 'Basic',
            'billing_interval' => 'monthly',
            'price' => 200000,
            'currency' => 'IDR',
            'status' => 'active',
            'ai_quota_limit' => 2000,
            'storage_quota_mb' => 2000,
            'users_quota_limit' => 25,
            'projects_quota_limit' => 12,
            'unit_quota_limit' => 7,
        ]);

        $service = app(UsageQuotaService::class);
        $subscription = $service->changePlan($institution, $basic, $owner);

        $this->assertInstanceOf(InstitutionSubscription::class, $subscription);
        $this->assertSame('active', $subscription->status);

        $dashboard = app(DashboardMetricsService::class);
        $dashboardData = $dashboard->forInstitution($institution, $owner);
        $this->assertSame($institution->getKey(), $dashboardData['institution_id']);
        $this->assertArrayHasKey('health', $dashboardData);
    }

    public function test_duplicate_webhooks_are_ignored_and_manual_activation_is_supported(): void
    {
        $institution = $this->createInstitution('saas-c');
        $invoice = \App\Models\BillingInvoice::query()->create([
            'institution_id' => $institution->getKey(),
            'invoice_number' => 'INV-1001',
            'status' => 'draft',
            'amount' => 150000,
            'currency' => 'IDR',
        ]);

        $service = app(PaymentGatewayService::class);
        $manual = $service->createManualSubscription($institution, $invoice);
        $this->assertSame('manual_activation_pending', $manual['status']);

        config()->set('services.billing.gateway.enabled', true);
        config()->set('services.billing.webhook_secret', 'demo-secret');

        $payload = json_encode([
            'institution_id' => $institution->getKey(),
            'invoice_id' => $invoice->getKey(),
            'gateway' => 'manual',
            'external_reference' => 'REF-1001',
            'status' => 'processed',
            'amount' => 150000,
            'currency' => 'IDR',
            'provider_status' => 'paid',
        ]);
        $signature = hash_hmac('sha256', $payload, 'demo-secret');

        $first = $service->handleWebhook($payload, $signature, 'dup-key-1');
        $second = $service->handleWebhook($payload, $signature, 'dup-key-1');

        $this->assertSame('processed', $first['status']);
        $this->assertSame('duplicate_webhook', $second['status']);
    }

    private function attachMembership(Institution $institution, User $user, Role $role): InstitutionUser
    {
        return InstitutionUser::query()->create([
            'institution_id' => $institution->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'status' => InstitutionUser::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
    }

    private function createInstitution(string $slug): Institution
    {
        $institution = Institution::query()->create([
            'name' => "Institution {$slug}",
            'slug' => $slug,
            'institution_type' => 'other',
            'timezone' => 'Asia/Jakarta',
        ]);
        $institution->forceFill(['status' => Institution::STATUS_ACTIVE])->save();

        return $institution;
    }
}
