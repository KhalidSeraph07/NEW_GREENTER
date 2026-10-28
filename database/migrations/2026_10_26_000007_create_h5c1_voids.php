<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5c1_counters',function(Blueprint $t){$t->string('route')->primary();$t->bigInteger('next_number');});
        DB::table('h5c1_counters')->insert(['route'=>'RA','next_number'=>1]);
        DB::statement("ALTER TABLE h5c1_counters ADD CHECK(route='RA' AND next_number>0)");
        Schema::create('h5c1_grants',function(Blueprint $t){
            $t->bigIncrements('sequence'); $t->uuid('id')->unique();$t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('kind');$t->uuid('document_id');$t->uuid('idempotency_key');$t->char('payload_hash',64);
            $t->string('state');$t->text('provenance');$t->jsonb('proof');$t->timestampTz('created_at');
            $t->unique(['user_id','idempotency_key']);$t->index(['kind','document_id','sequence']);
        });
        DB::statement("ALTER TABLE h5c1_grants ADD CHECK(kind IN ('invoice','invoice_note','boleta','boleta_note') AND state IN ('granted','not_granted'))");
        Schema::create('h5c1_batches', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('idempotency_key'); $t->char('payload_hash', 64); $t->string('name')->unique();
            $t->string('route',2); $t->date('reference_date'); $t->date('issue_date'); $t->jsonb('snapshot'); $t->jsonb('artifacts');
            $t->string('state')->default('pending_send'); $t->string('ticket')->nullable()->unique();
            $t->jsonb('result')->nullable(); $t->timestampsTz(); $t->unique(['user_id', 'idempotency_key']);
        });
        DB::statement("ALTER TABLE h5c1_batches ADD CHECK (state IN ('pending_send','sending','awaiting','querying','uncertain','sim_accepted','sim_rejected'))");
        DB::statement("ALTER TABLE h5c1_batches ADD CHECK (ticket IS NULL OR ticket ~ '^SIM-H5C1-[0-9a-f-]{36}$')");
        DB::statement("ALTER TABLE h5c1_batches ADD CHECK(route IN ('RA','RC'))");
        Schema::create('h5c1_members',function(Blueprint $t){
            $t->uuid('batch_id');$t->string('kind');$t->uuid('document_id');$t->uuid('grant_id');$t->integer('position');
            $t->foreign('batch_id')->references('id')->on('h5c1_batches')->restrictOnDelete();
            $t->foreign('grant_id')->references('id')->on('h5c1_grants')->restrictOnDelete();
            $t->primary(['kind','document_id']);$t->unique(['batch_id','position']);
        });
        Schema::create('h5c1_outbox', function (Blueprint $t) {
            $t->uuid('batch_id')->primary(); $t->foreign('batch_id')->references('id')->on('h5c1_batches')->restrictOnDelete();
            $t->string('phase')->default('send'); $t->string('status')->default('pending');
            $t->integer('send_attempts')->default(0); $t->integer('poll_attempts')->default(0); $t->integer('cycle_attempts')->default(0);
            $t->uuid('token')->nullable(); $t->timestampTz('reserved_until')->nullable(); $t->timestampTz('available_at')->nullable(); $t->timestampsTz();
        });
        DB::statement("ALTER TABLE h5c1_outbox ADD CHECK (phase IN ('send','poll') AND status IN ('pending','processing','uncertain','completed') AND send_attempts BETWEEN 0 AND 1 AND poll_attempts>=0 AND cycle_attempts BETWEEN 0 AND 3)");
        Schema::create('h5c1_simulation_receipts', function (Blueprint $t) {
            $t->uuid('batch_id')->primary(); $t->foreign('batch_id')->references('id')->on('h5c1_batches')->restrictOnDelete();
            $t->string('ticket')->unique(); $t->char('request_hash', 64); $t->timestampTz('created_at');
        });
        Schema::create('h5c1_events', function (Blueprint $t) {
            $t->id(); $t->uuid('batch_id'); $t->foreign('batch_id')->references('id')->on('h5c1_batches')->restrictOnDelete();
            $t->string('kind'); $t->uuid('token')->nullable(); $t->boolean('attention')->default(false);
            $t->jsonb('payload')->nullable(); $t->timestampTz('created_at'); $t->index(['batch_id', 'id']);
        });
        DB::unprepared(<<<'SQL'
CREATE FUNCTION h5c1_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'H5C1 append only'; END $$;
CREATE TRIGGER h5c1_grants_frozen BEFORE UPDATE OR DELETE ON h5c1_grants FOR EACH ROW EXECUTE FUNCTION h5c1_immutable();
CREATE TRIGGER h5c1_members_frozen BEFORE UPDATE OR DELETE ON h5c1_members FOR EACH ROW EXECUTE FUNCTION h5c1_immutable();
CREATE TRIGGER h5c1_receipts_frozen BEFORE UPDATE OR DELETE ON h5c1_simulation_receipts FOR EACH ROW EXECUTE FUNCTION h5c1_immutable();
CREATE TRIGGER h5c1_events_frozen BEFORE UPDATE OR DELETE ON h5c1_events FOR EACH ROW EXECUTE FUNCTION h5c1_immutable();
-- Lock original before child, matching the existing note creators; no existing rows are updated.
CREATE FUNCTION h5c1_lock_source(k text, doc uuid) RETURNS bigint LANGUAGE plpgsql AS $$
DECLARE owner_id bigint; parent uuid;
BEGIN
 CASE k
 WHEN 'invoice' THEN SELECT user_id INTO owner_id FROM invoices WHERE id=doc FOR UPDATE;
 WHEN 'boleta' THEN SELECT user_id INTO owner_id FROM h4_boletas WHERE id=doc FOR UPDATE;
 WHEN 'invoice_note' THEN
  SELECT original_id INTO parent FROM h5_notes WHERE id=doc;
  PERFORM 1 FROM invoices WHERE id=parent FOR UPDATE;
  SELECT user_id INTO owner_id FROM h5_notes WHERE id=doc FOR UPDATE;
 WHEN 'boleta_note' THEN
  SELECT original_id INTO parent FROM h5b_notes WHERE id=doc;
  PERFORM 1 FROM h4_boletas WHERE id=parent FOR UPDATE;
  SELECT user_id INTO owner_id FROM h5b_notes WHERE id=doc FOR UPDATE;
 ELSE RAISE EXCEPTION 'H5C1 invalid source kind'; END CASE;
 IF owner_id IS NULL THEN RAISE EXCEPTION 'H5C1 absent source'; END IF;
 RETURN owner_id;
END $$;
CREATE FUNCTION h5c1_grant_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE prior h5c1_grants%ROWTYPE;
BEGIN
 IF h5c1_lock_source(NEW.kind,NEW.document_id)<>NEW.user_id THEN RAISE EXCEPTION 'H5C1 foreign grant'; END IF;
 IF EXISTS(SELECT 1 FROM h5c1_members WHERE kind=NEW.kind AND document_id=NEW.document_id) THEN RAISE EXCEPTION 'H5C1 reserved source'; END IF;
 SELECT * INTO prior FROM h5c1_grants WHERE kind=NEW.kind AND document_id=NEW.document_id ORDER BY sequence DESC LIMIT 1;
 IF NEW.state='not_granted' AND (prior.state='granted'
  OR (NEW.kind='invoice' AND EXISTS(SELECT 1 FROM h5_notes WHERE original_id=NEW.document_id))
  OR (NEW.kind='boleta' AND EXISTS(SELECT 1 FROM h5b_notes WHERE original_id=NEW.document_id))) THEN RAISE EXCEPTION 'H5C1 incompatible grant'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5c1_grant_guard BEFORE INSERT ON h5c1_grants FOR EACH ROW EXECUTE FUNCTION h5c1_grant_guard();
CREATE FUNCTION h5c1_note_gate() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE k text; grant_state text;
BEGIN
 k:=CASE WHEN TG_TABLE_NAME='h5_notes' THEN 'invoice' ELSE 'boleta' END;
 PERFORM h5c1_lock_source(k,NEW.original_id);
 SELECT state INTO grant_state FROM h5c1_grants WHERE kind=k AND document_id=NEW.original_id ORDER BY sequence DESC LIMIT 1;
 IF grant_state='not_granted' OR EXISTS(SELECT 1 FROM h5c1_members WHERE kind=k AND document_id=NEW.original_id) THEN RAISE EXCEPTION 'H5C1 note conflicts with non-grant or void reservation'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5c1_note_gate BEFORE INSERT ON h5_notes FOR EACH ROW EXECUTE FUNCTION h5c1_note_gate();
CREATE TRIGGER h5c1_note_gate BEFORE INSERT ON h5b_notes FOR EACH ROW EXECUTE FUNCTION h5c1_note_gate();
CREATE FUNCTION h5c1_members_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE g h5c1_grants%ROWTYPE; b h5c1_batches%ROWTYPE;
BEGIN
 SELECT * INTO b FROM h5c1_batches WHERE id=NEW.batch_id;
 IF h5c1_lock_source(NEW.kind,NEW.document_id)<>b.user_id THEN RAISE EXCEPTION 'H5C1 foreign member'; END IF;
 SELECT * INTO g FROM h5c1_grants WHERE kind=NEW.kind AND document_id=NEW.document_id ORDER BY sequence DESC LIMIT 1;
 IF g.id IS DISTINCT FROM NEW.grant_id OR g.state IS DISTINCT FROM 'not_granted' OR g.user_id<>b.user_id THEN RAISE EXCEPTION 'H5C1 non-grant not accredited'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5c1_members_guard BEFORE INSERT ON h5c1_members FOR EACH ROW EXECUTE FUNCTION h5c1_members_guard();
CREATE FUNCTION h5c1_consistent() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target uuid; expected text[]; actual text[];
BEGIN
 IF TG_TABLE_NAME='h5c1_batches' THEN target:=NEW.id; ELSE target:=NEW.batch_id; END IF;
 SELECT array_agg((m.value->>'kind')||':'|| (m.value->>'id') ORDER BY m.ordinality) INTO expected FROM h5c1_batches b,
  jsonb_array_elements(b.snapshot->'members') WITH ORDINALITY m(value,ordinality) WHERE b.id=target;
 SELECT array_agg(kind||':'||document_id::text ORDER BY position) INTO actual FROM h5c1_members WHERE batch_id=target;
 IF actual IS DISTINCT FROM expected OR expected IS NULL THEN RAISE EXCEPTION 'H5C1 frozen members differ'; END IF;
 RETURN NEW;
END $$;
CREATE CONSTRAINT TRIGGER h5c1_members_consistent AFTER INSERT ON h5c1_members DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h5c1_consistent();
CREATE CONSTRAINT TRIGGER h5c1_batch_consistent AFTER INSERT ON h5c1_batches DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h5c1_consistent();
CREATE FUNCTION h5c1_summary_frozen() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'H5C1 immutable summary'; END IF;
 IF (to_jsonb(NEW)-ARRAY['state','ticket','result','updated_at']) IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['state','ticket','result','updated_at'])
    OR (OLD.ticket IS NOT NULL AND NEW.ticket IS DISTINCT FROM OLD.ticket)
    OR NOT ((OLD.state='pending_send' AND NEW.state='sending') OR (OLD.state='sending' AND NEW.state IN ('awaiting','uncertain'))
      OR (OLD.state='awaiting' AND NEW.state='querying') OR (OLD.state='querying' AND NEW.state IN ('awaiting','uncertain','sim_accepted','sim_rejected'))
      OR (OLD.state='uncertain' AND NEW.state='awaiting' AND NEW.ticket IS NOT NULL)) THEN RAISE EXCEPTION 'H5C1 invalid transition or frozen content'; END IF;
 IF NEW.state NOT IN ('sim_accepted','sim_rejected') AND NEW.result IS DISTINCT FROM OLD.result THEN RAISE EXCEPTION 'H5C1 false result'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5c1_summary_frozen BEFORE UPDATE OR DELETE ON h5c1_batches FOR EACH ROW EXECUTE FUNCTION h5c1_summary_frozen();
SQL);
    }
    public function down(): void { throw new RuntimeException('H5-C1 preserva datos y evidencias; sin borrado automatico.'); }
};
