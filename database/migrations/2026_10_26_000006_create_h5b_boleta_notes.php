<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5b_counters', function (Blueprint $t) { $t->string('document_type')->primary(); $t->bigInteger('next_number'); });
        DB::table('h5b_counters')->insert([['document_type' => '07', 'next_number' => 1], ['document_type' => '08', 'next_number' => 1]]);
        DB::statement("ALTER TABLE h5b_counters ADD CHECK (document_type IN ('07','08') AND next_number>0)");
        Schema::create('h5b_notes', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('original_id')->unique(); $t->foreign('original_id')->references('id')->on('h4_boletas')->restrictOnDelete();
            $t->uuid('idempotency_key'); $t->char('payload_hash', 64); $t->char('original_hash', 64);
            $t->string('document_type', 2); $t->string('reason_code', 2); $t->string('series', 4); $t->bigInteger('number');
            $t->date('emission_date'); $t->jsonb('snapshot'); $t->jsonb('artifacts');
            $t->string('state')->default('prepared'); $t->jsonb('result')->nullable(); $t->timestampsTz();
            $t->unique(['user_id', 'idempotency_key']); $t->unique(['document_type', 'series', 'number']);
        });
        DB::statement("ALTER TABLE h5b_notes ADD CHECK (((document_type='07' AND reason_code='01' AND series='BC00') OR (document_type='08' AND reason_code='02' AND series='BD00')) AND number BETWEEN 1 AND 99999999)");
        DB::statement("ALTER TABLE h5b_notes ADD CHECK (state='prepared')");
        DB::statement("ALTER TABLE h5b_notes ADD CHECK ((snapshot->>'total_cents')::bigint>0 AND (snapshot->>'subtotal_cents')::bigint>0 AND (snapshot->>'tax_cents')::bigint>=0 AND (snapshot->>'total_cents')::bigint=(snapshot->>'subtotal_cents')::bigint+(snapshot->>'tax_cents')::bigint)");
        Schema::create('h5b_summaries', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('idempotency_key'); $t->char('payload_hash', 64); $t->string('name')->unique();
            $t->date('reference_date'); $t->date('issue_date'); $t->jsonb('snapshot'); $t->jsonb('artifacts');
            $t->string('state')->default('pending_send'); $t->string('ticket')->nullable()->unique();
            $t->jsonb('result')->nullable(); $t->timestampsTz(); $t->unique(['user_id', 'idempotency_key']);
        });
        DB::statement("ALTER TABLE h5b_summaries ADD CHECK (state IN ('pending_send','sending','awaiting','querying','uncertain','sim_accepted','sim_rejected'))");
        DB::statement("ALTER TABLE h5b_summaries ADD CHECK (ticket IS NULL OR ticket ~ '^SIM-H5B-[0-9a-f-]{36}$')");
        Schema::create('h5b_members', function (Blueprint $t) {
            $t->uuid('summary_id'); $t->uuid('note_id')->unique(); $t->integer('position');
            $t->foreign('summary_id')->references('id')->on('h5b_summaries')->restrictOnDelete();
            $t->foreign('note_id')->references('id')->on('h5b_notes')->restrictOnDelete();
            $t->primary(['summary_id', 'note_id']); $t->unique(['summary_id', 'position']);
        });
        Schema::create('h5b_outbox', function (Blueprint $t) {
            $t->uuid('summary_id')->primary(); $t->foreign('summary_id')->references('id')->on('h5b_summaries')->restrictOnDelete();
            $t->string('phase')->default('send'); $t->string('status')->default('pending');
            $t->integer('send_attempts')->default(0); $t->integer('poll_attempts')->default(0); $t->integer('cycle_attempts')->default(0);
            $t->uuid('token')->nullable(); $t->timestampTz('reserved_until')->nullable(); $t->timestampTz('available_at')->nullable(); $t->timestampsTz();
        });
        DB::statement("ALTER TABLE h5b_outbox ADD CHECK (phase IN ('send','poll') AND status IN ('pending','processing','uncertain','completed') AND send_attempts BETWEEN 0 AND 1 AND poll_attempts>=0 AND cycle_attempts BETWEEN 0 AND 3)");
        Schema::create('h5b_simulation_receipts', function (Blueprint $t) {
            $t->uuid('summary_id')->primary(); $t->foreign('summary_id')->references('id')->on('h5b_summaries')->restrictOnDelete();
            $t->string('ticket')->unique(); $t->char('request_hash', 64); $t->timestampTz('created_at');
        });
        Schema::create('h5b_events', function (Blueprint $t) {
            $t->id(); $t->uuid('summary_id'); $t->foreign('summary_id')->references('id')->on('h5b_summaries')->restrictOnDelete();
            $t->string('kind'); $t->uuid('token')->nullable(); $t->boolean('attention')->default(false);
            $t->jsonb('payload')->nullable(); $t->timestampTz('created_at'); $t->index(['summary_id', 'id']);
        });
        DB::unprepared(<<<'SQL'
CREATE FUNCTION h5b_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'H5B immutable record'; END $$;
CREATE TRIGGER h5b_note_frozen BEFORE UPDATE OR DELETE ON h5b_notes FOR EACH ROW EXECUTE FUNCTION h5b_immutable();
CREATE TRIGGER h5b_members_frozen BEFORE UPDATE OR DELETE ON h5b_members FOR EACH ROW EXECUTE FUNCTION h5b_immutable();
CREATE TRIGGER h5b_receipts_frozen BEFORE UPDATE OR DELETE ON h5b_simulation_receipts FOR EACH ROW EXECUTE FUNCTION h5b_immutable();
CREATE TRIGGER h5b_events_frozen BEFORE UPDATE OR DELETE ON h5b_events FOR EACH ROW EXECUTE FUNCTION h5b_immutable();
CREATE FUNCTION h5b_note_original() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE source h4_boletas%ROWTYPE; rc h4_summaries%ROWTYPE;
BEGIN
 SELECT * INTO source FROM h4_boletas WHERE id=NEW.original_id FOR UPDATE;
 SELECT s.* INTO rc FROM h4_summaries s JOIN h4_members m ON s.id=m.summary_id WHERE m.boleta_id=source.id;
 IF source.id IS NULL OR source.user_id IS DISTINCT FROM NEW.user_id OR rc.state IS DISTINCT FROM 'sim_accepted'
    OR rc.user_id IS DISTINCT FROM NEW.user_id OR NEW.snapshot->>'original_granted_test' IS DISTINCT FROM 'true'
    OR NEW.snapshot->>'mode' IS DISTINCT FROM 'fictitious' OR NEW.snapshot->>'currency' IS DISTINCT FROM 'PEN'
    OR (NEW.snapshot->'original')-'accepted_rc' IS DISTINCT FROM (to_jsonb(source)-ARRAY['created_at','updated_at','idempotency_key'])
    OR NEW.snapshot->'original'->'accepted_rc' IS DISTINCT FROM jsonb_build_object('id',rc.id,'name',rc.name,'snapshot',rc.snapshot,'artifacts',rc.artifacts,'ticket',rc.ticket,'result',rc.result)
    OR NEW.snapshot->>'document_type' IS DISTINCT FROM NEW.document_type OR NEW.snapshot->>'reason_code' IS DISTINCT FROM NEW.reason_code
    OR NEW.snapshot->>'series' IS DISTINCT FROM NEW.series OR (NEW.snapshot->>'number')::bigint IS DISTINCT FROM NEW.number
    OR NEW.snapshot->>'emission_date' IS DISTINCT FROM NEW.emission_date::text
    OR NEW.emission_date<source.emission_date THEN RAISE EXCEPTION 'H5B invalid original or identity'; END IF;
 IF NEW.document_type='07' AND (NEW.snapshot->'subtotal_cents' IS DISTINCT FROM source.snapshot->'subtotal_cents'
    OR NEW.snapshot->'tax_cents' IS DISTINCT FROM source.snapshot->'tax_cents'
    OR NEW.snapshot->'total_cents' IS DISTINCT FROM source.snapshot->'total_cents') THEN RAISE EXCEPTION 'H5B full credit required'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5b_note_original BEFORE INSERT ON h5b_notes FOR EACH ROW EXECUTE FUNCTION h5b_note_original();
CREATE FUNCTION h5b_summary_frozen() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'H5B immutable summary'; END IF;
 IF (to_jsonb(NEW)-ARRAY['state','ticket','result','updated_at']) IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['state','ticket','result','updated_at'])
    OR (OLD.ticket IS NOT NULL AND NEW.ticket IS DISTINCT FROM OLD.ticket)
    OR NOT ((OLD.state='pending_send' AND NEW.state='sending') OR (OLD.state='sending' AND NEW.state IN ('awaiting','uncertain'))
      OR (OLD.state='awaiting' AND NEW.state='querying') OR (OLD.state='querying' AND NEW.state IN ('awaiting','uncertain','sim_accepted','sim_rejected'))
      OR (OLD.state='uncertain' AND NEW.state='awaiting' AND NEW.ticket IS NOT NULL)) THEN RAISE EXCEPTION 'H5B invalid transition or frozen content'; END IF;
 IF NEW.state NOT IN ('sim_accepted','sim_rejected') AND NEW.result IS DISTINCT FROM OLD.result THEN RAISE EXCEPTION 'H5B false result'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5b_summary_frozen BEFORE UPDATE OR DELETE ON h5b_summaries FOR EACH ROW EXECUTE FUNCTION h5b_summary_frozen();
CREATE FUNCTION h5b_members_consistent() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target uuid; expected text[]; actual text[];
BEGIN
 IF TG_TABLE_NAME='h5b_summaries' THEN target:=NEW.id; ELSE target:=NEW.summary_id; END IF;
 SELECT array_agg(m.value->>'id' ORDER BY m.ordinality) INTO expected FROM h5b_summaries s,
  jsonb_array_elements(s.snapshot->'members') WITH ORDINALITY m(value,ordinality) WHERE s.id=target;
 SELECT array_agg(note_id::text ORDER BY position) INTO actual FROM h5b_members WHERE summary_id=target;
 IF actual IS DISTINCT FROM expected OR expected IS NULL THEN RAISE EXCEPTION 'H5B frozen members differ'; END IF;
 IF EXISTS(SELECT 1 FROM h5b_members m JOIN h5b_notes n ON n.id=m.note_id JOIN h5b_summaries s ON s.id=m.summary_id
  WHERE s.id=target AND (n.user_id<>s.user_id OR n.emission_date<>s.reference_date)) THEN RAISE EXCEPTION 'H5B member owner/date mismatch'; END IF;
 RETURN NEW;
END $$;
CREATE CONSTRAINT TRIGGER h5b_members_consistent AFTER INSERT ON h5b_members DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h5b_members_consistent();
CREATE CONSTRAINT TRIGGER h5b_summary_consistent AFTER INSERT ON h5b_summaries DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h5b_members_consistent();
SQL);
    }
    public function down(): void { throw new RuntimeException('H5-B sin borrado automatico de datos/evidencias.'); }
};
