<?php

namespace Tests\Feature;

use Database\Seeders\EventFlowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventFlowApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventFlowDemoSeeder::class);
    }

    public function test_demo_user_can_login_and_access_dashboard(): void
    {
        $login = $this->postJson('/api/auth/login', ['email' => 'admin@eventflow.test', 'password' => 'password']);
        $login->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
        $this->withToken($login->json('token'))->getJson('/api/dashboard')->assertOk()->assertJsonStructure(['stats', 'upcoming_events', 'events_by_status', 'recent_payments', 'monthly_revenue']);
    }

    public function test_public_quote_request_creates_an_enquiry(): void
    {
        $this->getJson('/api/public/event-types')->assertOk()->assertJsonFragment(['name' => 'Wedding', 'base_price' => '15000000.00']);
        $this->getJson('/api/public/services')->assertOk()->assertJsonFragment(['name' => 'Catering', 'base_price' => '85000.00', 'pricing_type' => 'per_guest']);
        $catering = \App\Models\Service::where('name', 'Catering')->firstOrFail();
        $response = $this->postJson('/api/public/request-quote', [
            'name' => 'New Client', 'email' => 'new-client@example.test', 'event_date' => now()->addMonths(3)->toDateString(),
            'guest_count' => 60, 'venue' => 'Kampala', 'service_ids' => [$catering->id],
        ]);
        $response->assertCreated()->assertJsonStructure(['message', 'reference']);
        $event = \App\Models\Event::where('reference', $response->json('reference'))->firstOrFail();
        $this->assertSame(60, (int) $event->services()->firstOrFail()->pivot->quantity);
        $this->assertSame(5100000.0, (float) $event->services()->firstOrFail()->pivot->subtotal);
        $this->assertSame('enquiry', $event->status);
    }

    public function test_admin_can_create_quotation_and_customer_can_accept_it(): void
    {
        $admin = $this->postJson('/api/auth/login', ['email' => 'admin@eventflow.test', 'password' => 'password'])->json('token');
        $event = \App\Models\Event::where('reference', 'DEMO-SARAH-WEDDING')->firstOrFail();
        $quote = $this->withToken($admin)->postJson('/api/quotations', [
            'event_id' => $event->id, 'discount' => 0, 'deposit_amount' => 500,
            'items' => [['description' => 'Planning package', 'quantity' => 1, 'unit_price' => 1000]],
        ]);
        $quote->assertCreated()->assertJsonPath('total', 1000);

        $customerLogin = $this->postJson('/api/auth/login', ['email' => 'customer@eventflow.test', 'password' => 'password']);
        $customerLogin->assertOk()->assertJsonPath('user.role', 'customer')->assertJsonPath('user.customer_id', $event->customer_id);
        $customer = $customerLogin->json('token');
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $acceptance = $this->withToken($customer)->postJson('/api/quotations/' . $quote->json('id') . '/accept');
        $acceptance->assertOk()->assertJsonPath('invoice.total', 500);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'deposit']);
    }
}
