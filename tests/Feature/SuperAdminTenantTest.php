<?php

namespace Tests\Feature;

use App\Models\NotificationTemplate;
use App\Models\SaasPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SuperAdminTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_super_admin_can_onboard_isp_with_provisioning(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $plan = SaasPlan::where('code', 'business')->first();

        $this->actingAs($admin)->post(route('admin.tenants.store'), [
            'name' => 'PT Jaringan Contoh', 'code' => 'jrg-01', 'pic_name' => 'Nama PIC', 'whatsapp' => '0812 3456 7890',
            'email' => 'INFO@contoh.co.id', 'address' => 'Jl. Contoh', 'province' => 'Jawa Barat', 'city' => 'Bandung',
            'saas_plan_id' => $plan->id, 'period' => 'monthly', 'initial_status' => 'trial', 'trial_days' => 14,
            'admin_name' => 'Admin Contoh', 'admin_email' => 'admin@contoh.co.id', 'theme' => 'default', 'timezone' => 'Asia/Jakarta',
            // Upaya menyuntik harga/kuota dari form harus diabaikan.
            'price' => 1, 'client_quota' => 999999,
        ])->assertRedirect();

        $tenant = Tenant::where('code', 'JRG-01')->firstOrFail();
        $this->assertSame('trial', $tenant->status);
        $this->assertSame('info@contoh.co.id', $tenant->profile->email);
        $this->assertSame('081234567890', $tenant->profile->whatsapp);

        $sub = $tenant->currentSubscription;
        $this->assertSame('199000.00', (string) $sub->price);
        $this->assertSame(500, $sub->client_quota);

        $this->assertSame(4, $tenant->provisioningJobs()->where('status', 'done')->count());
        $this->assertSame(count(NotificationTemplate::DEFAULTS), $this->inTenant($tenant, fn () => NotificationTemplate::count()));

        $ispAdmin = User::where('email', 'admin@contoh.co.id')->first();
        $this->assertNull($ispAdmin->password);
        $this->assertFalse($ispAdmin->is_super_admin);
        $this->assertTrue($ispAdmin->hasRole('isp_admin'));
        $this->assertFalse($ispAdmin->hasPermission('tenants.create'));
        Notification::assertSentTo($ispAdmin, \App\Notifications\AdminInvitation::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.created', 'tenant_id' => $tenant->id]);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $t = $this->makeTenant();
        $this->actingAs($this->superAdmin())->post(route('admin.tenants.status', $t), ['status' => 'draft'])->assertSessionHas('error');
        $this->assertSame('trial', $t->fresh()->status);
    }

    public function test_activation_link_is_single_use(): void
    {
        $t = $this->makeTenant();
        $user = $t->users()->first();
        [, $token] = UserInvitation::issue($user);

        $this->get(route('activation.show', $token))->assertOk();
        $this->post(route('activation.store', $token), ['password' => 'Password12345', 'password_confirmation' => 'Password12345'])->assertRedirect();
        $this->assertAuthenticatedAs($user->fresh());
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('activation.show', $token))->assertNotFound();
        $this->post('/login', ['email' => $user->email, 'password' => 'Password12345'])->assertRedirect(route('app.dashboard'));
    }

    public function test_admin_pages_render(): void
    {
        $t = $this->makeTenant();
        $admin = $this->superAdmin();
        foreach ([route('admin.dashboard'), route('admin.tenants.index'), route('admin.tenants.create'), route('admin.tenants.show', $t),
            route('admin.tenants.edit', $t), route('admin.plans.index'), route('admin.plans.create'), route('admin.saas-invoices.index'), route('admin.audit.index'), route('profile.edit')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
