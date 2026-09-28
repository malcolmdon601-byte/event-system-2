<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->index();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        Schema::create('event_types', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->text('description')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->text('description')->nullable(); $table->string('pricing_type')->default('fixed'); $table->decimal('base_price', 14, 2)->default(0); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->foreignId('event_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); $table->date('event_date')->index(); $table->unsignedInteger('guest_count')->nullable(); $table->string('venue')->nullable(); $table->decimal('budget', 14, 2)->nullable();
            $table->time('start_time')->nullable(); $table->time('end_time')->nullable(); $table->text('notes')->nullable(); $table->text('message')->nullable(); $table->string('status')->default('enquiry')->index(); $table->string('reference')->nullable()->unique(); $table->timestamps();
        });
        Schema::create('event_service', function (Blueprint $table) {
            $table->id(); $table->foreignId('event_id')->constrained()->cascadeOnDelete(); $table->foreignId('service_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('quantity')->default(1); $table->decimal('unit_price', 14, 2)->default(0); $table->decimal('subtotal', 14, 2)->default(0); $table->timestamps(); $table->unique(['event_id', 'service_id']);
        });
        Schema::create('staff', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('name'); $table->string('email')->nullable(); $table->string('phone')->nullable(); $table->string('role_title')->default('Event Staff'); $table->string('status')->default('active'); $table->timestamps();
        });
        Schema::create('vendors', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('category')->nullable(); $table->string('email')->nullable(); $table->string('phone')->nullable(); $table->text('address')->nullable(); $table->string('status')->default('active'); $table->timestamps();
        });
        Schema::create('event_staff', function (Blueprint $table) {
            $table->id(); $table->foreignId('event_id')->constrained()->cascadeOnDelete(); $table->foreignId('staff_id')->constrained()->cascadeOnDelete(); $table->timestamps(); $table->unique(['event_id', 'staff_id']);
        });
        Schema::create('event_vendor', function (Blueprint $table) {
            $table->id(); $table->foreignId('event_id')->constrained()->cascadeOnDelete(); $table->foreignId('vendor_id')->constrained()->cascadeOnDelete(); $table->timestamps(); $table->unique(['event_id', 'vendor_id']);
        });
        Schema::create('tasks', function (Blueprint $table) {
            $table->id(); $table->foreignId('event_id')->constrained()->cascadeOnDelete(); $table->foreignId('assigned_staff_id')->nullable()->constrained('staff')->nullOnDelete(); $table->string('title'); $table->date('due_date')->nullable(); $table->string('priority')->default('medium'); $table->string('status')->default('todo'); $table->timestamps();
        });
        Schema::create('quotations', function (Blueprint $table) {
            $table->id(); $table->foreignId('event_id')->constrained()->cascadeOnDelete(); $table->string('quotation_number')->unique(); $table->decimal('subtotal', 14, 2)->default(0); $table->decimal('discount', 14, 2)->default(0); $table->decimal('tax', 14, 2)->default(0); $table->decimal('total', 14, 2)->default(0); $table->decimal('deposit_amount', 14, 2)->default(0); $table->date('valid_until')->nullable(); $table->text('notes')->nullable(); $table->string('status')->default('sent'); $table->timestamps();
        });
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('quotation_id')->constrained()->cascadeOnDelete(); $table->string('description'); $table->unsignedInteger('quantity')->default(1); $table->decimal('unit_price', 14, 2)->default(0); $table->decimal('subtotal', 14, 2)->default(0); $table->timestamps();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id(); $table->foreignId('event_id')->constrained()->cascadeOnDelete(); $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete(); $table->string('invoice_number')->unique(); $table->decimal('total', 14, 2); $table->decimal('amount_paid', 14, 2)->default(0); $table->decimal('balance', 14, 2); $table->date('due_date')->nullable(); $table->string('status')->default('unpaid'); $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('invoice_id')->constrained()->cascadeOnDelete(); $table->foreignId('event_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->constrained()->cascadeOnDelete(); $table->string('payment_reference')->unique(); $table->decimal('amount', 14, 2); $table->string('method')->default('mobile_money'); $table->string('status')->default('completed'); $table->boolean('is_simulated')->default(true); $table->timestamp('paid_at')->nullable(); $table->timestamps();
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id(); $table->foreignId('event_id')->constrained()->cascadeOnDelete(); $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete(); $table->text('body'); $table->timestamps();
        });
        Schema::create('equipment', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('category')->nullable(); $table->unsignedInteger('quantity')->default(1); $table->string('status')->default('active'); $table->timestamps();
        });
        Schema::create('equipment_reservations', function (Blueprint $table) {
            $table->id(); $table->foreignId('equipment_id')->constrained()->cascadeOnDelete(); $table->foreignId('event_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('quantity')->default(1); $table->date('reserved_from'); $table->date('reserved_until'); $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->morphs('notifiable'); $table->string('type'); $table->text('data'); $table->timestamp('read_at')->nullable(); $table->timestamps();
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id(); $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete(); $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete(); $table->string('name'); $table->string('path'); $table->string('mime_type')->nullable(); $table->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete(); $table->string('action'); $table->text('description')->nullable(); $table->json('properties')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->dropConstrainedForeignId('customer_id'); });
        Schema::dropIfExists('activity_logs'); Schema::dropIfExists('documents'); Schema::dropIfExists('notifications'); Schema::dropIfExists('equipment_reservations'); Schema::dropIfExists('equipment');
        Schema::dropIfExists('messages'); Schema::dropIfExists('payments'); Schema::dropIfExists('invoices'); Schema::dropIfExists('quotation_items'); Schema::dropIfExists('quotations');
        Schema::dropIfExists('tasks'); Schema::dropIfExists('event_vendor'); Schema::dropIfExists('event_staff'); Schema::dropIfExists('vendors'); Schema::dropIfExists('staff'); Schema::dropIfExists('event_service');
        Schema::dropIfExists('events'); Schema::dropIfExists('services'); Schema::dropIfExists('event_types'); Schema::dropIfExists('customers');
        Schema::table('users', function (Blueprint $table) { $table->dropColumn(['role', 'is_active']); });
    }
};
