<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_outbox', function (Blueprint $table) {
            $table->uuid('attempt_token')->nullable();
            $table->timestampTz('reserved_until')->nullable();
            $table->timestampTz('available_at')->nullable();
        });
        DB::statement('ALTER TABLE invoice_outbox DROP CONSTRAINT invoice_outbox_check');
        DB::statement("ALTER TABLE invoice_outbox ADD CONSTRAINT h3_outbox_state CHECK (status IN ('pending','processing','retry_wait','completed','uncertain','exhausted') AND attempts BETWEEN 0 AND 3)");
        DB::statement("ALTER TABLE invoice_outbox ADD CONSTRAINT h3_outbox_lease CHECK ((status='processing' AND attempt_token IS NOT NULL AND reserved_until IS NOT NULL) OR status<>'processing')");
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_transport_status_check');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT h3_invoice_state CHECK (transport_status IN ('pending_simulation','simulation_processing','simulation_retry_wait','simulation_failed','simulation_uncertain','simulated_accepted','simulated_rejected'))");
        Schema::create('simulation_receipts', function (Blueprint $table) {
            $table->uuid('invoice_id')->primary();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->jsonb('response');
            $table->timestampTz('created_at');
        });
        Schema::create('invoice_transport_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->uuid('attempt_token')->nullable();
            $table->unsignedInteger('attempt');
            $table->string('kind');
            $table->boolean('attention')->default(false);
            $table->timestampTz('created_at');
            $table->index(['invoice_id', 'id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('H3 no elimina estados ni evidencia. Requiere reversión revisada.');
    }
};
