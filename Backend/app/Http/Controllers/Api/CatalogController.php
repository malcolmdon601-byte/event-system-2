<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\EventType;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function createService(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'pricing_type' => ['required', Rule::in(['fixed', 'per_guest', 'per_hour'])], 'base_price' => ['required', 'numeric', 'min:0']]);
        return response()->json(Service::create($data), 201);
    }
    public function updateService(Request $request, Service $service)
    {
        $service->update($request->validate(['name' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string'], 'pricing_type' => ['sometimes', Rule::in(['fixed', 'per_guest', 'per_hour'])], 'base_price' => ['sometimes', 'numeric', 'min:0'], 'is_active' => ['sometimes', 'boolean']]));
        return $service;
    }
    public function createEventType(Request $request)
    {
        return response()->json(EventType::create($request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'base_price' => ['required', 'numeric', 'min:0']])), 201);
    }
    public function updateEventType(Request $request, EventType $eventType)
    {
        $eventType->update($request->validate(['name' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string'], 'base_price' => ['sometimes', 'numeric', 'min:0'], 'is_active' => ['sometimes', 'boolean']]));
        return $eventType;
    }

    public function createStaff(Request $request)
    {
        return response()->json(Staff::create($request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'role_title' => ['required', 'string', 'max:255'], 'user_id' => ['nullable', 'exists:users,id']])), 201);
    }
    public function updateStaff(Request $request, Staff $staff)
    {
        $staff->update($request->validate(['name' => ['sometimes', 'required', 'string', 'max:255'], 'email' => ['sometimes', 'nullable', 'email'], 'phone' => ['sometimes', 'nullable', 'string'], 'role_title' => ['sometimes', 'string'], 'status' => ['sometimes', Rule::in(['active', 'inactive'])], 'user_id' => ['sometimes', 'nullable', 'exists:users,id']]));
        return $staff;
    }
    public function deleteStaff(Staff $staff) { $staff->update(['status' => 'inactive']); return response()->json(['message' => 'Staff member deactivated.']); }

    public function createVendor(Request $request)
    {
        return response()->json(Vendor::create($request->validate(['name' => ['required', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string'], 'address' => ['nullable', 'string']])), 201);
    }
    public function updateVendor(Request $request, Vendor $vendor)
    {
        $vendor->update($request->validate(['name' => ['sometimes', 'required', 'string', 'max:255'], 'category' => ['sometimes', 'nullable', 'string'], 'email' => ['sometimes', 'nullable', 'email'], 'phone' => ['sometimes', 'nullable', 'string'], 'address' => ['sometimes', 'nullable', 'string'], 'status' => ['sometimes', Rule::in(['active', 'inactive'])]]));
        return $vendor;
    }
    public function deleteVendor(Vendor $vendor) { $vendor->update(['status' => 'inactive']); return response()->json(['message' => 'Vendor deactivated.']); }

    public function createEquipment(Request $request)
    {
        return response()->json(Equipment::create($request->validate(['name' => ['required', 'string', 'max:255'], 'category' => ['nullable', 'string'], 'quantity' => ['required', 'integer', 'min:1']])), 201);
    }
    public function updateEquipment(Request $request, Equipment $equipment)
    {
        $equipment->update($request->validate(['name' => ['sometimes', 'required', 'string'], 'category' => ['sometimes', 'nullable', 'string'], 'quantity' => ['sometimes', 'integer', 'min:1'], 'status' => ['sometimes', Rule::in(['active', 'inactive'])]]));
        return $equipment;
    }
    public function deleteEquipment(Equipment $equipment)
    {
        abort_if($equipment->reservations()->exists(), 409, 'Equipment with reservation history cannot be deleted.');
        $equipment->delete();
        return response()->json(['message' => 'Equipment deleted.']);
    }

    public function invoices() { return Invoice::with(['event.customer', 'quotation', 'payments'])->latest()->paginate(100); }
    public function showInvoice(Invoice $invoice) { return $invoice->load(['event.customer', 'quotation.items', 'payments']); }

    public function notifications(Request $request)
    {
        return response()->json($request->user()->notifications()->latest()->paginate(50));
    }
    public function markNotificationRead(Request $request, string $notification)
    {
        $row = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $row->markAsRead();
        return response()->json(['message' => 'Notification marked as read.']);
    }
    public function markAllNotificationsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Notifications marked as read.']);
    }

    public function documents(Request $request)
    {
        $query = Document::with(['event', 'uploader'])->latest();
        if ($request->filled('event_id')) $query->where('event_id', $request->integer('event_id'));
        return $query->paginate(50);
    }
    public function uploadDocument(Request $request)
    {
        $data = $request->validate(['event_id' => ['nullable', 'exists:events,id'], 'document' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,doc,docx,xls,xlsx'], 'name' => ['nullable', 'string', 'max:255']]);
        $file = $request->file('document');
        $path = $file->store('event-documents', 'public');
        $document = Document::create(['event_id' => $data['event_id'] ?? null, 'uploaded_by' => $request->user()->id, 'name' => $data['name'] ?? $file->getClientOriginalName(), 'path' => $path, 'mime_type' => $file->getMimeType()]);
        return response()->json($document->load('uploader'), 201);
    }
    public function deleteDocument(Document $document)
    {
        Storage::disk('public')->delete($document->path);
        $document->delete();
        return response()->json(['message' => 'Document deleted.']);
    }

    public function activityLog(Request $request)
    {
        $query = ActivityLog::with(['user', 'event'])->latest();
        if ($request->filled('event_id')) $query->where('event_id', $request->integer('event_id'));
        return $query->paginate(100);
    }
}
