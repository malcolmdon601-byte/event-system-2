<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentReservation;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventFlowController extends Controller
{
    private const EVENT_STATUSES = ['enquiry', 'quotation', 'deposit', 'confirmed', 'planning', 'ready', 'completed', 'cancelled'];
    private function admin(Request $request): bool { return in_array($request->user()?->role, ['super_admin', 'manager', 'finance', 'staff'], true); }
    private function ownedEvent(Request $request, Event $event): bool
    {
        return $this->admin($request) || ($request->user()?->customer_id && (int) $event->customer_id === (int) $request->user()->customer_id);
    }
    private function log(Request $request, string $action, ?Event $event = null, ?string $description = null): void
    {
        DB::table('activity_logs')->insert(['user_id' => $request->user()?->id, 'event_id' => $event?->id, 'action' => $action, 'description' => $description, 'properties' => null, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function notifyUser(?User $user, string $type, array $data): void
    {
        if (!$user) return;
        DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'type' => $type, 'data' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }
    private function notifyCustomer(Event $event, string $type, array $data): void
    {
        $this->notifyUser($event->customer()->with('user')->first()?->user, $type, $data + ['event_id' => $event->id, 'event_name' => $event->name]);
    }
    private function eventDetails(Event $event): Event
    {
        return $event->load(['customer', 'eventType', 'services', 'staff', 'vendors', 'tasks.assignedStaff', 'invoices.payments', 'payments.customer', 'quotations.items']);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $credentials['email'])->where('is_active', true)->first();
        if (!$user || !Hash::check($credentials['password'], $user->password)) return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        return response()->json(['token' => $user->createToken('eventflow-web')->plainTextToken, 'user' => $user->load('customer')]);
    }

    public function register(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:8', 'confirmed'], 'phone' => ['nullable', 'string', 'max:40']]);
        return DB::transaction(function () use ($data) {
            $customer = Customer::create(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => 'customer', 'customer_id' => $customer->id]);
            $customer->update(['user_id' => $user->id]);
            return response()->json(['token' => $user->createToken('eventflow-web')->plainTextToken, 'user' => $user], 201);
        });
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Signed out.']);
    }

    public function publicServices() { return Service::where('is_active', true)->orderBy('name')->get(); }
    public function publicEventTypes() { return EventType::where('is_active', true)->orderBy('name')->get(); }

    public function requestQuote(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'],
            'event_type_id' => ['nullable', 'exists:event_types,id'], 'event_date' => ['required', 'date', 'after_or_equal:today'], 'guest_count' => ['nullable', 'integer', 'min:1'],
            'venue' => ['nullable', 'string', 'max:255'], 'budget' => ['nullable', 'numeric', 'min:0'], 'message' => ['nullable', 'string', 'max:5000'],
            'service_ids' => ['nullable', 'array'], 'service_ids.*' => ['integer', 'exists:services,id'],
        ]);
        $event = DB::transaction(function () use ($data) {
            $customer = Customer::withTrashed()->where('email', $data['email'])->first();
            if ($customer?->trashed()) $customer->restore();
            $customer ??= new Customer();
            $customer->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
            $customer->save();
            $typeId = $data['event_type_id'] ?? null;
            $typeName = $typeId ? EventType::find($typeId)->name : 'Event';
            $event = Event::create(['customer_id' => $customer->id, 'event_type_id' => $typeId, 'name' => $data['name'] . ' — ' . $typeName, 'event_date' => $data['event_date'], 'guest_count' => $data['guest_count'] ?? null, 'venue' => $data['venue'] ?? null, 'budget' => $data['budget'] ?? null, 'message' => $data['message'] ?? null, 'notes' => $data['message'] ?? null, 'status' => 'enquiry', 'reference' => 'ENQ-' . Str::upper(Str::random(8))]);
            foreach ($data['service_ids'] ?? [] as $id) {
                $service = Service::find($id);
                $quantity = $service->pricing_type === 'per_guest' ? (int) ($data['guest_count'] ?? 1) : 1;
                $event->services()->attach($id, ['quantity' => $quantity, 'unit_price' => $service->base_price, 'subtotal' => $quantity * (float) $service->base_price]);
            }
            return $event;
        });
        User::whereIn('role', ['super_admin', 'manager'])->where('is_active', true)->each(fn(User $user) => $this->notifyUser($user, 'event.enquiry_received', ['event_id' => $event->id, 'event_name' => $event->name, 'reference' => $event->reference]));
        return response()->json(['message' => 'Your enquiry was received. Our team will contact you shortly.', 'reference' => $event->reference], 201);
    }

    public function dashboard()
    {
        $now = now()->toDateString();
        $events = Event::query();
        $paid = Payment::where('status', 'completed')->sum('amount');
        $outstanding = Invoice::sum('balance');
        $statusCounts = Event::selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $monthExpression = DB::getDriverName() === 'sqlite' ? "strftime('%Y-%m', paid_at)" : "DATE_FORMAT(paid_at, '%Y-%m')";
        $monthly = Payment::selectRaw("{$monthExpression} as month, SUM(amount) as total")->where('paid_at', '>=', now()->subMonths(5)->startOfMonth())->groupBy('month')->orderBy('month')->get();
        return response()->json([
            'stats' => ['total_events' => (clone $events)->count(), 'upcoming_events' => (clone $events)->whereDate('event_date', '>=', $now)->whereNotIn('status', ['cancelled', 'completed'])->count(), 'pending_enquiries' => (clone $events)->where('status', 'enquiry')->count(), 'confirmed_bookings' => (clone $events)->whereIn('status', ['confirmed', 'planning', 'ready'])->count(), 'revenue' => (float) $paid, 'outstanding' => (float) $outstanding],
            'upcoming_events' => Event::with('customer')->whereDate('event_date', '>=', $now)->whereNotIn('status', ['cancelled', 'completed'])->orderBy('event_date')->limit(8)->get(),
            'events_by_status' => $statusCounts, 'recent_payments' => Payment::with(['customer', 'event'])->latest('paid_at')->limit(8)->get(), 'monthly_revenue' => $monthly,
        ]);
    }

    public function events(Request $request)
    {
        $query = Event::with('customer')->withCount('tasks')->latest('event_date');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('search')) $query->where(fn($q) => $q->where('name', 'like', '%' . $request->search . '%')->orWhereHas('customer', fn($c) => $c->where('name', 'like', '%' . $request->search . '%')));
        return $query->paginate(100);
    }

    public function showEvent(Event $event) { return $this->eventDetails($event); }

    public function createEvent(Request $request)
    {
        $data = $request->validate(['customer_id' => ['required', 'exists:customers,id'], 'name' => ['required', 'string', 'max:255'], 'event_date' => ['required', 'date'], 'guest_count' => ['nullable', 'integer', 'min:0'], 'venue' => ['nullable', 'string', 'max:255'], 'event_type_id' => ['nullable', 'exists:event_types,id'], 'budget' => ['nullable', 'numeric', 'min:0'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i'], 'notes' => ['nullable', 'string']]);
        $event = Event::create($data + ['status' => 'enquiry']);
        $this->log($request, 'event.created', $event, 'Event created');
        return response()->json($event->load('customer'), 201);
    }

    public function updateEvent(Request $request, Event $event)
    {
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255'], 'event_date' => ['sometimes', 'required', 'date'], 'guest_count' => ['sometimes', 'nullable', 'integer', 'min:0'], 'venue' => ['sometimes', 'nullable', 'string', 'max:255'], 'event_type_id' => ['sometimes', 'nullable', 'exists:event_types,id'], 'budget' => ['sometimes', 'nullable', 'numeric', 'min:0'], 'start_time' => ['sometimes', 'nullable', 'date_format:H:i'], 'end_time' => ['sometimes', 'nullable', 'date_format:H:i'], 'notes' => ['sometimes', 'nullable', 'string'], 'status' => ['sometimes', Rule::in(self::EVENT_STATUSES)]]);
        $previousStatus = $event->status;
        $event->update($data);
        $this->log($request, 'event.updated', $event, 'Event details updated');
        if (isset($data['status']) && $data['status'] !== $previousStatus) $this->notifyCustomer($event, 'event.status_updated', ['status' => $event->status]);
        return $this->eventDetails($event);
    }

    public function attachServices(Request $request, Event $event)
    {
        $data = $request->validate(['services' => ['present', 'array'], 'services.*.service_id' => ['required', 'exists:services,id'], 'services.*.quantity' => ['required', 'integer', 'min:1']]);
        $sync = [];
        foreach ($data['services'] as $item) { $service = Service::findOrFail($item['service_id']); $qty = $item['quantity']; $sync[$service->id] = ['quantity' => $qty, 'unit_price' => $service->base_price, 'subtotal' => $qty * $service->base_price]; }
        $event->services()->sync($sync);
        return $this->eventDetails($event);
    }

    public function attachStaff(Request $request, Event $event)
    {
        $data = $request->validate(['staff_ids' => ['present', 'array'], 'staff_ids.*' => ['integer', 'exists:staff,id']]);
        $event->staff()->sync($data['staff_ids']);
        return $event->load('staff');
    }

    public function attachVendors(Request $request, Event $event)
    {
        $data = $request->validate(['vendor_ids' => ['present', 'array'], 'vendor_ids.*' => ['integer', 'exists:vendors,id']]);
        $event->vendors()->sync($data['vendor_ids']);
        return $event->load('vendors');
    }

    public function customers(Request $request)
    {
        $query = Customer::withCount('events')->orderBy('name');
        if ($request->filled('search')) $query->where(fn($q) => $q->where('name', 'like', '%' . $request->search . '%')->orWhere('email', 'like', '%' . $request->search . '%')->orWhere('phone', 'like', '%' . $request->search . '%'));
        return $query->paginate(100);
    }
    public function createCustomer(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string']]);
        return response()->json(Customer::create($data), 201);
    }
    public function updateCustomer(Request $request, Customer $customer)
    {
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255'], 'email' => ['sometimes', 'nullable', 'email', 'max:255'], 'phone' => ['sometimes', 'nullable', 'string', 'max:40'], 'address' => ['sometimes', 'nullable', 'string']]);
        $customer->update($data); return $customer->loadCount('events');
    }
    public function deleteCustomer(Customer $customer) { $customer->delete(); return response()->json(['message' => 'Customer archived.']); }

    public function staff() { return Staff::where('status', 'active')->orderBy('name')->get(); }
    public function vendors() { return Vendor::where('status', 'active')->orderBy('name')->get(); }
    public function services() { return Service::where('is_active', true)->orderBy('name')->get(); }
    public function equipment() { return Equipment::with('reservations')->orderBy('name')->get(); }

    public function quotations(Request $request)
    {
        $query = Quotation::with(['event.customer', 'items'])->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        $page = $query->paginate(100);
        $page->getCollection()->each(fn($quote) => $quote->setRelation('customer', $quote->event->customer));
        return $page;
    }

    public function createQuotation(Request $request)
    {
        $data = $request->validate(['event_id' => ['required', 'exists:events,id'], 'discount' => ['nullable', 'numeric', 'min:0'], 'deposit_amount' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string'], 'items' => ['required', 'array', 'min:1'], 'items.*.description' => ['required', 'string', 'max:255'], 'items.*.quantity' => ['required', 'integer', 'min:1'], 'items.*.unit_price' => ['required', 'numeric', 'min:0']]);
        $quotation = DB::transaction(function () use ($data) {
            $subtotal = collect($data['items'])->sum(fn($item) => (int) $item['quantity'] * (float) $item['unit_price']);
            $discount = (float) ($data['discount'] ?? 0);
            abort_if($discount > $subtotal, 422, 'Discount cannot exceed the quotation subtotal.');
            $quotation = Quotation::create(['event_id' => $data['event_id'], 'quotation_number' => 'QT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)), 'subtotal' => $subtotal, 'discount' => $discount, 'tax' => 0, 'total' => $subtotal - $discount, 'deposit_amount' => min((float) ($data['deposit_amount'] ?? 0), $subtotal - $discount), 'valid_until' => now()->addDays(14)->toDateString(), 'notes' => $data['notes'] ?? null, 'status' => 'sent']);
            foreach ($data['items'] as $item) $quotation->items()->create(['description' => $item['description'], 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'subtotal' => $item['quantity'] * $item['unit_price']]);
            $quotation->event()->update(['status' => 'quotation']);
            return $quotation;
        });
        $quotation->load(['event.customer', 'items'])->setRelation('customer', $quotation->event->customer);
        $this->notifyCustomer($quotation->event, 'quotation.sent', ['quotation_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number, 'total' => $quotation->total]);
        return response()->json($quotation, 201);
    }

    public function acceptQuotation(Request $request, Quotation $quotation)
    {
        $event = $quotation->event;
        abort_unless($this->ownedEvent($request, $event), 403);
        abort_unless(in_array($quotation->status, ['sent', 'viewed'], true), 422, 'This quotation can no longer be accepted.');
        return DB::transaction(function () use ($quotation, $event) {
            $quotation->update(['status' => 'accepted']);
            $event->update(['status' => 'deposit']);
            $amount = $quotation->deposit_amount > 0 ? $quotation->deposit_amount : $quotation->total;
            $invoice = Invoice::firstOrCreate(['quotation_id' => $quotation->id], ['event_id' => $event->id, 'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)), 'total' => $amount, 'amount_paid' => 0, 'balance' => $amount, 'due_date' => now()->addDays(7)->toDateString(), 'status' => 'unpaid']);
            $this->notifyCustomer($event, 'quotation.accepted', ['quotation_id' => $quotation->id, 'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number]);
            return response()->json(['quotation' => $quotation, 'invoice' => $invoice]);
        });
    }

    public function rejectQuotation(Request $request, Quotation $quotation)
    {
        abort_unless($this->ownedEvent($request, $quotation->event), 403);
        abort_unless(in_array($quotation->status, ['sent', 'viewed'], true), 422, 'This quotation can no longer be declined.');
        $quotation->update(['status' => 'rejected']); $quotation->event()->update(['status' => 'enquiry']);
        return response()->json(['message' => 'Quotation declined.']);
    }

    public function payments()
    {
        return Payment::with(['customer', 'event', 'invoice'])->latest('paid_at')->paginate(100);
    }

    public function createPayment(Request $request)
    {
        $data = $request->validate(['invoice_id' => ['required', 'exists:invoices,id'], 'amount' => ['required', 'numeric', 'gt:0'], 'method' => ['required', Rule::in(['mobile_money', 'bank_transfer', 'card', 'cash'])]]);
        $payment = DB::transaction(function () use ($data) {
            $invoice = Invoice::whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();
            abort_if((float) $data['amount'] > (float) $invoice->balance, 422, 'Payment cannot exceed the invoice balance.');
            $event = $invoice->event;
            $payment = Payment::create(['invoice_id' => $invoice->id, 'event_id' => $event->id, 'customer_id' => $event->customer_id, 'payment_reference' => 'PAY-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)), 'amount' => $data['amount'], 'method' => $data['method'], 'status' => 'completed', 'is_simulated' => true, 'paid_at' => now()]);
            $invoice->amount_paid = (float) $invoice->amount_paid + (float) $data['amount'];
            $invoice->balance = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
            $invoice->status = $invoice->balance <= 0 ? 'paid' : 'partial';
            $invoice->save();
            if ($event->status === 'deposit' && $invoice->amount_paid >= min((float) $invoice->total, (float) ($invoice->quotation?->deposit_amount ?? $invoice->total))) $event->update(['status' => 'confirmed']);
            $this->notifyCustomer($event, 'payment.received', ['payment_reference' => $payment->payment_reference, 'amount' => $payment->amount, 'invoice_balance' => $invoice->balance]);
            return $payment;
        });
        return response()->json($payment->load(['customer', 'event', 'invoice']), 201);
    }

    public function createTask(Request $request)
    {
        $data = $request->validate(['event_id' => ['required', 'exists:events,id'], 'title' => ['required', 'string', 'max:255'], 'assigned_staff_id' => ['nullable', 'exists:staff,id'], 'due_date' => ['nullable', 'date'], 'priority' => ['required', Rule::in(['low', 'medium', 'high'])]]);
        return response()->json(Task::create($data), 201);
    }
    public function updateTask(Request $request, Task $task)
    {
        $data = $request->validate(['status' => ['sometimes', Rule::in(['todo', 'in_progress', 'completed'])], 'title' => ['sometimes', 'string', 'max:255'], 'assigned_staff_id' => ['sometimes', 'nullable', 'exists:staff,id'], 'due_date' => ['sometimes', 'nullable', 'date'], 'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])]]);
        $task->update($data); return $task->load('assignedStaff');
    }

    public function eventMessages(Request $request, Event $event)
    {
        abort_unless($this->ownedEvent($request, $event), 403);
        return $event->messages()->with('sender')->oldest()->get();
    }
    public function createMessage(Request $request, Event $event)
    {
        abort_unless($this->ownedEvent($request, $event), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        return response()->json(Message::create(['event_id' => $event->id, 'sender_id' => $request->user()->id, 'body' => $data['body']])->load('sender'), 201);
    }

    public function portalDashboard(Request $request)
    {
        $customer = $request->user()->customer;
        abort_unless($customer, 404, 'Customer profile not found.');
        $events = Event::where('customer_id', $customer->id)->with(['quotations', 'invoices'])->orderBy('event_date')->get();
        $eventIds = $events->pluck('id');
        $quotations = Quotation::whereIn('event_id', $eventIds)->with('event')->latest()->get();
        $payments = Payment::where('customer_id', $customer->id)->latest('paid_at')->get();
        $next = $events->where('event_date', '>=', now()->toDateString())->whereNotIn('status', ['cancelled', 'completed'])->first();
        return response()->json(['customer' => $customer, 'events' => $events, 'next_event' => $next, 'outstanding_balance' => (float) Invoice::whereIn('event_id', $eventIds)->sum('balance'), 'quotations' => $quotations, 'payments' => $payments]);
    }

    public function reserveEquipment(Request $request)
    {
        $data = $request->validate(['equipment_id' => ['required', 'exists:equipment,id'], 'event_id' => ['required', 'exists:events,id'], 'quantity' => ['required', 'integer', 'min:1'], 'reserved_from' => ['required', 'date'], 'reserved_until' => ['required', 'date', 'after_or_equal:reserved_from']]);
        return DB::transaction(function () use ($data) {
            $equipment = Equipment::whereKey($data['equipment_id'])->lockForUpdate()->firstOrFail();
            $conflict = EquipmentReservation::where('equipment_id', $equipment->id)->whereDate('reserved_from', '<=', $data['reserved_until'])->whereDate('reserved_until', '>=', $data['reserved_from'])->sum('quantity');
            abort_if($conflict + $data['quantity'] > $equipment->quantity, 422, 'Equipment is not available in the requested quantity for these dates.');
            return response()->json(EquipmentReservation::create($data)->load(['equipment', 'event']), 201);
        });
    }
}
