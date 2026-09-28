<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EventFlowDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $roles = [
                ['Admin', 'admin@eventflow.test', 'super_admin'], ['Manager', 'manager@eventflow.test', 'manager'],
                ['Finance Officer', 'finance@eventflow.test', 'finance'], ['Event Staff', 'staff@eventflow.test', 'staff'],
            ];
            foreach ($roles as [$name, $email, $role]) User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('password'), 'role' => $role, 'is_active' => true]);

            $typeData = [
                ['Wedding', 'Full wedding planning and coordination.', 15000000],
                ['Corporate Event', 'Conferences, launches, and company events.', 12000000],
                ['Birthday Party', 'Memorable birthday celebrations for all ages.', 3500000],
                ['Anniversary', 'Thoughtfully planned anniversary celebrations.', 5000000],
            ];
            $types = [];
            foreach ($typeData as [$name, $description, $price]) {
                $types[$name] = EventType::updateOrCreate(['name' => $name], ['description' => $description, 'base_price' => $price, 'is_active' => true]);
            }
            $serviceData = [
                ['Catering', 'per_guest', 85000], ['Venue Styling', 'fixed', 2500000], ['Photography', 'fixed', 1800000],
                ['Sound & Lighting', 'fixed', 1200000], ['Event Coordination', 'fixed', 900000], ['Transport', 'fixed', 500000],
            ];
            $services = [];
            foreach ($serviceData as [$name, $pricing, $price]) $services[$name] = Service::updateOrCreate(['name' => $name], ['description' => 'Professional ' . strtolower($name) . ' services.', 'pricing_type' => $pricing, 'base_price' => $price, 'is_active' => true]);

            $customerData = [
                ['Sarah Namusoke', 'customer@eventflow.test', '+256700000001', 'customer@eventflow.test'],
                ['NovaTech Ltd', 'novatech@example.test', '+256700000002', null],
                ['James Kato', 'james@example.test', '+256700000003', null],
                ['Amara Events', 'amara@example.test', '+256700000004', null],
            ];
            $customers = [];
            foreach ($customerData as [$name, $email, $phone, $portalEmail]) {
                $customer = Customer::withTrashed()->firstOrCreate(['email' => $email], ['name' => $name, 'phone' => $phone]);
                if ($customer->trashed()) $customer->restore();
                if ($portalEmail) {
                    $user = User::updateOrCreate(['email' => $portalEmail], ['name' => $name, 'password' => Hash::make('password'), 'role' => 'customer', 'is_active' => true]);
                    $user->update(['customer_id' => $customer->id]);
                    $customer->update(['user_id' => $user->id]);
                }
                $customers[$email] = $customer;
            }

            $events = [];
            $eventData = [
                ['sarah-wedding', $customers['customer@eventflow.test'], 'Sarah’s Wedding Celebration', $types['Wedding'], now()->addMonths(4), 'confirmed', 180, 'Lake Victoria Serena Resort', 32000000],
                ['novatech-summit', $customers['novatech@example.test'], 'NovaTech Annual Summit', $types['Corporate Event'], now()->addMonths(2), 'quotation', 240, 'Kampala Convention Centre', 48000000],
                ['james-anniversary', $customers['james@example.test'], 'James & Grace Anniversary', $types['Anniversary'], now()->addMonths(6), 'enquiry', 80, 'Munyonyo Gardens', 12000000],
                ['amara-birthday', $customers['amara@example.test'], 'Amara’s Birthday Celebration', $types['Birthday Party'], now()->subMonths(2), 'completed', 65, 'Protea Hotel Kampala', 8500000],
            ];
            foreach ($eventData as [$key, $customer, $name, $type, $date, $status, $guests, $venue, $budget]) {
                $events[$key] = Event::firstOrCreate(['reference' => 'DEMO-' . strtoupper($key)], ['customer_id' => $customer->id, 'event_type_id' => $type->id, 'name' => $name, 'event_date' => $date->toDateString(), 'status' => $status, 'guest_count' => $guests, 'venue' => $venue, 'budget' => $budget, 'notes' => 'Demo event record for evaluation.']);
            }

            $staffRows = [
                ['Peter Okello', 'Lead Coordinator'], ['Ruth Nakanwagi', 'Event Producer'], ['Daniel Ssemanda', 'Logistics Lead'],
            ];
            $staff = [];
            foreach ($staffRows as [$name, $title]) $staff[] = Staff::firstOrCreate(['name' => $name], ['role_title' => $title, 'status' => 'active']);
            $vendor = Vendor::firstOrCreate(['name' => 'Golden Fork Catering'], ['category' => 'Catering', 'phone' => '+256700000010', 'status' => 'active']);
            $sarah = $events['sarah-wedding'];
            $sarah->services()->syncWithoutDetaching([
                $services['Catering']->id => ['quantity' => 180, 'unit_price' => 85000, 'subtotal' => 15300000],
                $services['Photography']->id => ['quantity' => 1, 'unit_price' => 1800000, 'subtotal' => 1800000],
                $services['Venue Styling']->id => ['quantity' => 1, 'unit_price' => 2500000, 'subtotal' => 2500000],
            ]);
            $sarah->staff()->syncWithoutDetaching([$staff[0]->id, $staff[1]->id]);
            $sarah->vendors()->syncWithoutDetaching([$vendor->id]);
            Task::firstOrCreate(['event_id' => $sarah->id, 'title' => 'Confirm final guest list'], ['assigned_staff_id' => $staff[1]->id, 'due_date' => now()->addMonths(3), 'priority' => 'high', 'status' => 'in_progress']);
            Task::firstOrCreate(['event_id' => $sarah->id, 'title' => 'Review venue floor plan'], ['assigned_staff_id' => $staff[0]->id, 'due_date' => now()->addMonths(2), 'priority' => 'medium', 'status' => 'todo']);

            $quotation = Quotation::firstOrCreate(['quotation_number' => 'QT-DEMO-NOVATECH'], ['event_id' => $events['novatech-summit']->id, 'subtotal' => 28000000, 'discount' => 1000000, 'tax' => 0, 'total' => 27000000, 'deposit_amount' => 8100000, 'valid_until' => now()->addDays(14), 'notes' => 'Demo quotation for NovaTech.', 'status' => 'sent']);
            $quotation->items()->firstOrCreate(['description' => 'Conference production package'], ['quantity' => 1, 'unit_price' => 20000000, 'subtotal' => 20000000]);
            $quotation->items()->firstOrCreate(['description' => 'Catering and hospitality'], ['quantity' => 1, 'unit_price' => 8000000, 'subtotal' => 8000000]);

            $birthday = $events['amara-birthday'];
            $invoice = Invoice::firstOrCreate(['invoice_number' => 'INV-DEMO-AMARA'], ['event_id' => $birthday->id, 'total' => 8500000, 'amount_paid' => 8500000, 'balance' => 0, 'due_date' => now()->subMonths(2), 'status' => 'paid']);
            Payment::firstOrCreate(['payment_reference' => 'PAY-DEMO-AMARA'], ['invoice_id' => $invoice->id, 'event_id' => $birthday->id, 'customer_id' => $birthday->customer_id, 'amount' => 8500000, 'method' => 'bank_transfer', 'status' => 'completed', 'is_simulated' => true, 'paid_at' => now()->subMonths(2)]);
        });
    }
}
