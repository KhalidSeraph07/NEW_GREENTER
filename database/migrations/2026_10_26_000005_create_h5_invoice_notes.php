<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5_counters', function (Blueprint $t) { $t->string('document_type')->primary(); $t->bigInteger('next_number'); });
        DB::table('h5_counters')->insert([['document_type' => '07', 'next_number' => 1], ['document_type' => '08', 'next_number' => 1]]);
        DB::statement("ALTER TABLE h5_counters ADD CHECK (document_type IN ('07','08') AND next_number>0)");
        Schema::create('h5_notes', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('original_id')->unique(); $t->foreign('original_id')->references('id')->on('invoices')->restrictOnDelete();
            $t->uuid('idempotency_key'); $t->char('payload_hash', 64); $t->char('original_hash', 64);
            $t->string('document_type', 2); $t->string('reason_code', 2); $t->string('series', 4); $t->bigInteger('number');
            $t->date('emission_date'); $t->jsonb('snapshot'); $t->jsonb('artifacts');
            $t->string('state')->default('pending'); $t->jsonb('result')->nullable(); $t->timestampsTz();
            $t->unique(['user_id', 'idempotency_key']); $t->unique(['document_type', 'series', 'number']);
        });
        DB::statement("ALTER TABLE h5_notes ADD CHECK (((document_type='07' AND reason_code='01' AND series='FC00') OR (document_type='08' AND reason_code='02' AND series='FD00')) AND number BETWEEN 1 AND 99999999)");
        DB::statement("ALTER TABLE h5_notes ADD CHECK (state IN ('pending','processing','retry_wait','uncertain','sim_accepted','sim_rejected','failed','expired'))");
        DB::statement("ALTER TABLE h5_notes ADD CHECK ((snapshot->>'total_cents')::bigint>0 AND (snapshot->>'subtotal_cents')::bigint>0 AND (snapshot->>'tax_cents')::bigint>=0 AND (snapshot->>'total_cents')::bigint=(snapshot->>'subtotal_cents')::bigint+(snapshot->>'tax_cents')::bigint)");
        Schema::create('h5_outbox', function (Blueprint $t) {
            $t->uuid('note_id')->primary(); $t->foreign('note_id')->references('id')->on('h5_notes')->restrictOnDelete();
            $t->string('status')->default('pending'); $t->integer('attempts')->default(0);
            $t->uuid('token')->nullable(); $t->timestampTz('reserved_until')->nullable(); $t->timestampTz('available_at')->nullable(); $t->timestampsTz();
        });
        DB::statement("ALTER TABLE h5_outbox ADD CHECK (status IN ('pending','processing','retry_wait','uncertain','completed','failed','expired') AND attempts BETWEEN 0 AND 3)");
        DB::statement("ALTER TABLE h5_outbox ADD CHECK (status<>'processing' OR (token IS NOT NULL AND reserved_until IS NOT NULL))");
        Schema::create('h5_receipts', function (Blueprint $t) {
            $t->uuid('note_id')->primary(); $t->foreign('note_id')->references('id')->on('h5_notes')->restrictOnDelete();
            $t->jsonb('response'); $t->timestampTz('created_at');
        });
        Schema::create('h5_events', function (Blueprint $t) {
            $t->id(); $t->uuid('note_id'); $t->foreign('note_id')->references('id')->on('h5_notes')->restrictOnDelete();
            $t->string('kind'); $t->uuid('token')->nullable(); $t->boolean('attention')->default(false);
            $t->jsonb('payload')->nullable(); $t->timestampTz('created_at'); $t->index(['note_id', 'id']);
        });
        DB::unprepared(<<<'SQL'
CREATE FUNCTION h5_note_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE source invoices%ROWTYPE;
BEGIN
    IF TG_OP='DELETE' THEN RAISE EXCEPTION 'H5 immutable note'; END IF;
    IF TG_OP='INSERT' THEN
        SELECT * INTO source FROM invoices WHERE id=NEW.original_id FOR UPDATE;
        IF source.user_id IS DISTINCT FROM NEW.user_id OR source.document_type<>'01' OR source.transport_status<>'simulated_accepted'
           OR NEW.state<>'pending' OR NEW.result IS NOT NULL
           OR NEW.snapshot->'original' IS DISTINCT FROM (to_jsonb(source)-ARRAY['created_at','updated_at','idempotency_key'])
           OR NEW.snapshot->>'document_type' IS DISTINCT FROM NEW.document_type OR NEW.snapshot->>'reason_code' IS DISTINCT FROM NEW.reason_code
           OR NEW.snapshot->>'series' IS DISTINCT FROM NEW.series OR (NEW.snapshot->>'number')::bigint IS DISTINCT FROM NEW.number
           OR NEW.snapshot->>'emission_date' IS DISTINCT FROM NEW.emission_date::text
           OR NEW.snapshot->>'mode' IS DISTINCT FROM 'fictitious' OR NEW.snapshot->>'original_granted_test' IS DISTINCT FROM 'true'
           OR NEW.snapshot->>'currency' IS DISTINCT FROM 'PEN' THEN RAISE EXCEPTION 'H5 invalid original or identity'; END IF;
        IF NEW.document_type='07' AND ((NEW.snapshot->>'subtotal_cents')::bigint<>source.subtotal_cents
            OR (NEW.snapshot->>'tax_cents')::bigint<>source.tax_cents OR (NEW.snapshot->>'total_cents')::bigint<>source.total_cents) THEN
            RAISE EXCEPTION 'H5 credit must reverse entire original'; END IF;
    ELSE
        IF (to_jsonb(NEW)-ARRAY['state','result','updated_at']) IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['state','result','updated_at'])
           OR NOT ((OLD.state='pending' AND NEW.state IN ('processing','expired'))
             OR (OLD.state='retry_wait' AND NEW.state IN ('processing','expired'))
             OR (OLD.state='processing' AND NEW.state IN ('retry_wait','uncertain','sim_accepted','sim_rejected','failed'))
             OR (OLD.state='uncertain' AND NEW.state IN ('sim_accepted','sim_rejected'))) THEN RAISE EXCEPTION 'H5 invalid transition or frozen content'; END IF;
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER h5_note_guard BEFORE INSERT OR UPDATE OR DELETE ON h5_notes FOR EACH ROW EXECUTE FUNCTION h5_note_guard();
CREATE FUNCTION h5_append_only() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'H5 append only evidence'; END $$;
CREATE TRIGGER h5_receipts_frozen BEFORE UPDATE OR DELETE ON h5_receipts FOR EACH ROW EXECUTE FUNCTION h5_append_only();
CREATE TRIGGER h5_events_frozen BEFORE UPDATE OR DELETE ON h5_events FOR EACH ROW EXECUTE FUNCTION h5_append_only();
SQL);
    }

    public function down(): void { throw new RuntimeException('H5-A no borra documentos ni evidencias. Requiere reversion revisada.'); }
};
