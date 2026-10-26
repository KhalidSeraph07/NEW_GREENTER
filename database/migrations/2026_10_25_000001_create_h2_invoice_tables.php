<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestampsTz();
        });
        Schema::create('invoice_series', function (Blueprint $table) {
            $table->string('series', 4)->primary();
            $table->bigInteger('next_number');
        });
        DB::table('invoice_series')->insert(['series' => 'F000', 'next_number' => 1]);
        DB::statement('ALTER TABLE invoice_series ADD CHECK (next_number > 0)');
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('issuer_ruc', 11);
            $table->string('document_type', 2);
            $table->string('series', 4);
            $table->bigInteger('number');
            $table->jsonb('snapshot');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('tax_cents');
            $table->bigInteger('total_cents');
            $table->jsonb('artifacts');
            $table->string('transport_status')->default('pending_simulation');
            $table->jsonb('simulated_response')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['issuer_ruc', 'document_type', 'series', 'number']);
        });
        DB::statement("ALTER TABLE invoices ADD CHECK (issuer_ruc='00000000000' AND document_type='01' AND series='F000' AND number>0)");
        DB::statement('ALTER TABLE invoices ADD CHECK (subtotal_cents>0 AND tax_cents>=0 AND total_cents=subtotal_cents+tax_cents)');
        DB::statement("ALTER TABLE invoices ADD CHECK (transport_status IN ('pending_simulation','simulated_accepted','simulated_rejected','simulation_uncertain'))");
        Schema::create('invoice_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('invoice_id')->unique();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE invoice_outbox ADD CHECK (status IN ('pending','completed','uncertain') AND attempts BETWEEN 0 AND 1)");
    }

    public function down(): void
    {
        throw new RuntimeException('H2 no borra documentos/evidencias automáticamente. Requiere plan de reversión revisado.');
    }
};
