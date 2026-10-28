<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5c2_h4_corrections',function(Blueprint $t){
            $t->uuid('original_id')->primary();$t->foreign('original_id')->references('id')->on('h4_summaries')->restrictOnDelete();
            $t->uuid('successor_id')->unique();$t->foreign('successor_id')->references('id')->on('h4_summaries')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();$t->uuid('idempotency_key');$t->char('request_hash',64);
            $t->string('reason',250);$t->date('prepared_on');$t->date('operation_date')->nullable();$t->jsonb('proof');$t->timestampTz('created_at');
            $t->unique(['user_id','idempotency_key']);
        });
        Schema::create('h5c2_h5b_corrections',function(Blueprint $t){
            $t->uuid('original_id')->primary();$t->foreign('original_id')->references('id')->on('h5b_summaries')->restrictOnDelete();
            $t->uuid('successor_id')->unique();$t->foreign('successor_id')->references('id')->on('h5b_summaries')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();$t->uuid('idempotency_key');$t->char('request_hash',64);
            $t->string('reason',250);$t->date('prepared_on');$t->date('operation_date')->nullable();$t->jsonb('proof');$t->timestampTz('created_at');
            $t->unique(['user_id','idempotency_key']);
        });
        Schema::create('h5c2_void_corrections',function(Blueprint $t){
            $t->uuid('original_id')->primary();$t->foreign('original_id')->references('id')->on('h5c1_batches')->restrictOnDelete();
            $t->uuid('successor_id')->unique();$t->foreign('successor_id')->references('id')->on('h5c1_batches')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();$t->uuid('idempotency_key');$t->char('request_hash',64);
            $t->string('reason',250);$t->date('prepared_on');$t->date('operation_date')->nullable();$t->jsonb('proof');$t->timestampTz('created_at');
            $t->unique(['user_id','idempotency_key']);
        });
        Schema::create('h5c2_invoice_reissues',function(Blueprint $t){
            $t->uuid('original_id')->primary();$t->foreign('original_id')->references('id')->on('invoices')->restrictOnDelete();
            $t->uuid('successor_id')->unique();$t->foreign('successor_id')->references('id')->on('invoices')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();$t->uuid('idempotency_key');$t->char('request_hash',64);
            $t->string('reason',250);$t->date('prepared_on');$t->date('operation_date')->nullable();$t->jsonb('proof');$t->timestampTz('created_at');
            $t->unique(['user_id','idempotency_key']);
        });
        DB::unprepared(<<<'SQL'

CREATE FUNCTION h5c2_anchor(f text, doc uuid) RETURNS uuid LANGUAGE plpgsql STABLE AS $$
DECLARE root uuid;
BEGIN
 CASE f WHEN 'h4' THEN SELECT original_id INTO root FROM h5c2_h4_corrections WHERE successor_id=doc;
 WHEN 'h5b' THEN SELECT original_id INTO root FROM h5c2_h5b_corrections WHERE successor_id=doc;
 WHEN 'void' THEN SELECT original_id INTO root FROM h5c2_void_corrections WHERE successor_id=doc;
 ELSE RAISE EXCEPTION 'C2 invalid family'; END CASE;
 RETURN coalesce(root,doc);
END $$;
CREATE FUNCTION h5c2_effective(f text, doc uuid) RETURNS uuid LANGUAGE plpgsql STABLE AS $$
DECLARE child uuid;
BEGIN
 CASE f WHEN 'h4' THEN SELECT successor_id INTO child FROM h5c2_h4_corrections WHERE original_id=doc;
 WHEN 'h5b' THEN SELECT successor_id INTO child FROM h5c2_h5b_corrections WHERE original_id=doc;
 WHEN 'void' THEN SELECT successor_id INTO child FROM h5c2_void_corrections WHERE original_id=doc;
 ELSE RAISE EXCEPTION 'C2 invalid family'; END CASE;
 RETURN coalesce(child,doc);
END $$;
CREATE FUNCTION h5c2_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'C2 immutable lineage'; END $$;
CREATE FUNCTION h5c2_link_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE f text; tbl text; box text; col text; events text; receipts text; event_kind text;
 p jsonb; c jsonb; job jsonb; ev jsonb; rec jsonb; resp jsonb; fresh text; d date; m jsonb; existing boolean; states text;
BEGIN
 CASE TG_TABLE_NAME
 WHEN 'h5c2_h4_corrections' THEN f:='h4';tbl:='h4_summaries';box:='h4_outbox';col:='summary_id';events:='h4_events';receipts:='h4_simulation_receipts';
 WHEN 'h5c2_h5b_corrections' THEN f:='h5b';tbl:='h5b_summaries';box:='h5b_outbox';col:='summary_id';events:='h5b_events';receipts:='h5b_simulation_receipts';
 WHEN 'h5c2_void_corrections' THEN f:='void';tbl:='h5c1_batches';box:='h5c1_outbox';col:='batch_id';events:='h5c1_events';receipts:='h5c1_simulation_receipts';
 WHEN 'h5c2_invoice_reissues' THEN f:='invoice';tbl:='invoices';box:='invoice_outbox';col:='invoice_id';events:='invoice_transport_events';receipts:='simulation_receipts';
 END CASE;
 EXECUTE format('SELECT to_jsonb(t) FROM %I t WHERE id=$1 FOR UPDATE',tbl) INTO p USING NEW.original_id;
 EXECUTE format('SELECT to_jsonb(t),pg_xact_status(xmin::text::xid8) FROM %I t WHERE id=$1',tbl) INTO c,fresh USING NEW.successor_id;
 EXECUTE format('SELECT EXISTS(SELECT 1 FROM %I WHERE successor_id=$1 OR original_id=$1)',TG_TABLE_NAME) INTO existing USING NEW.original_id;
 IF p IS NULL OR c IS NULL OR existing OR NEW.original_id=NEW.successor_id OR fresh IS DISTINCT FROM 'in progress'
  OR (p->>'user_id')::bigint IS DISTINCT FROM NEW.user_id OR (c->>'user_id')::bigint IS DISTINCT FROM NEW.user_id
  OR c->>'idempotency_key' IS DISTINCT FROM NEW.idempotency_key::text OR c->>'payload_hash' IS DISTINCT FROM NEW.request_hash
  OR NEW.prepared_on<'2026-01-01' OR NEW.prepared_on>='2027-01-01' THEN RAISE EXCEPTION 'C2 ineligible source, chain or adopted child'; END IF;
 EXECUTE format('SELECT to_jsonb(t) FROM %I t WHERE %I=$1',box,col) INTO job USING NEW.original_id;
 EXECUTE format('SELECT to_jsonb(t) FROM %I t WHERE %I=$1 AND id=$2',events,col) INTO ev USING NEW.original_id,(NEW.proof->>'event_id')::bigint;
 EXECUTE format('SELECT to_jsonb(t) FROM %I t WHERE %I=$1',receipts,col) INTO rec USING NEW.original_id;
 IF job->>'status' IS DISTINCT FROM 'completed' OR ev IS NULL OR ev->>'attention' IS DISTINCT FROM 'true'
  OR (NEW.proof->>'received_at')::timestamptz IS DISTINCT FROM (ev->>'created_at')::timestamptz THEN RAISE EXCEPTION 'C2 unproven terminal receipt'; END IF;
 IF f='invoice' THEN
  resp:=p->'simulated_response';d:=((p->'snapshot'->>'emitted_at')::timestamptz AT TIME ZONE 'America/Lima')::date;
  IF p->>'transport_status' IS DISTINCT FROM 'simulated_rejected' OR c->>'transport_status' IS DISTINCT FROM 'pending_simulation'
   OR c->'simulated_response' IS DISTINCT FROM 'null'::jsonb OR resp->>'scenario' IS DISTINCT FROM 'rejected'
   OR resp->>'source' IS DISTINCT FROM 'H2_LOCAL_FAKE' OR resp->>'invoice_id' IS DISTINCT FROM NEW.original_id::text
   OR resp->>'code' IS DISTINCT FROM 'SIM-REJECTED' OR rec->'response' IS DISTINCT FROM resp
   OR ev->>'kind' NOT IN ('result','recovered') OR ev->>'attempt_token' IS DISTINCT FROM job->>'attempt_token'
   OR NEW.operation_date IS DISTINCT FROM d OR NEW.prepared_on<d OR NEW.prepared_on>d+3
   OR ((c->'snapshot'->>'emitted_at')::timestamptz AT TIME ZONE 'America/Lima')::date<>NEW.prepared_on
   OR (c->'snapshot')-ARRAY['number','emitted_at'] IS DISTINCT FROM (p->'snapshot')-ARRAY['number','emitted_at']
   OR c->'number'=p->'number' OR EXISTS(SELECT 1 FROM h5_notes WHERE original_id=NEW.original_id)
   OR EXISTS(SELECT 1 FROM h5c1_members WHERE kind='invoice' AND document_id=NEW.original_id)
   OR EXISTS(SELECT 1 FROM h5c1_grants WHERE kind='invoice' AND document_id=NEW.original_id)
   THEN RAISE EXCEPTION 'C2 invalid invoice reissue'; END IF;
 ELSE
  resp:=p->'result'->'response';
  IF p->>'state' IS DISTINCT FROM 'sim_rejected' OR p->'result'->>'outcome' IS DISTINCT FROM 'rejected'
   OR p->'result'->>'official_cdr' IS DISTINCT FROM 'false' OR p->'result'->>'simulated' IS DISTINCT FROM 'true'
   OR c->>'state' IS DISTINCT FROM 'pending_send' OR c->'ticket' IS DISTINCT FROM 'null'::jsonb OR c->'result' IS DISTINCT FROM 'null'::jsonb
   OR resp->>'statusCode' IS DISTINCT FROM '99' OR p->>'ticket' IS NULL OR resp->>'ticket' IS DISTINCT FROM p->>'ticket'
   OR rec->>'ticket' IS DISTINCT FROM p->>'ticket' OR rec->>'request_hash' IS DISTINCT FROM p->'artifacts'->'zip'->>'sha256'
   OR ev->>'kind' IS DISTINCT FROM 'result_saved' OR ev->>'token' IS DISTINCT FROM job->>'token' OR ev->'payload' IS DISTINCT FROM resp
   OR c->>'reference_date' IS DISTINCT FROM p->>'reference_date' OR c->>'issue_date' IS DISTINCT FROM NEW.prepared_on::text
   OR (c->'snapshot')-ARRAY['name','issue_date','number'] IS DISTINCT FROM (p->'snapshot')-ARRAY['name','issue_date','number']
   OR c->>'name'=p->>'name' OR NEW.operation_date IS NOT NULL THEN RAISE EXCEPTION 'C2 incompatible communication'; END IF;
  IF f IN ('h4','h5b') AND (NEW.prepared_on<(p->>'reference_date')::date OR NEW.prepared_on>(p->>'reference_date')::date+7) THEN RAISE EXCEPTION 'C2 original RC deadline'; END IF;
  IF f='void' THEN
   FOR m IN SELECT value FROM jsonb_array_elements(p->'snapshot'->'members') LOOP
    IF NEW.prepared_on>(m->>'emission_date')::date+7 OR NEW.prepared_on>((m->'acceptance'->>'received_at')::timestamptz AT TIME ZONE 'America/Lima')::date+7
     THEN RAISE EXCEPTION 'C2 original void deadline'; END IF;
   END LOOP;
  END IF;
 END IF;
 IF NEW.proof->'response' IS DISTINCT FROM resp OR NEW.proof->'snapshot' IS DISTINCT FROM p->'snapshot'
  OR NEW.proof->'artifacts' IS DISTINCT FROM p->'artifacts' OR NEW.proof->>'document_id' IS DISTINCT FROM NEW.original_id::text
  OR NEW.proof->>'family' IS DISTINCT FROM f OR resp->>'simulated' IS DISTINCT FROM 'true'
  OR NEW.proof->'cause' IS DISTINCT FROM resp->'technical_rejection' OR resp->'technical_rejection'->>'code' IS DISTINCT FROM 'SIM-C2-ZIP-ENTRY'
  OR resp->'technical_rejection'->>'scope' IS DISTINCT FROM 'technical_packaging'
  OR resp->'technical_rejection'->>'family' IS DISTINCT FROM f OR resp->'technical_rejection'->>'document_id' IS DISTINCT FROM NEW.original_id::text
  OR resp->'technical_rejection'->>'actual_entry' IS DISTINCT FROM 'H5C2-INVALID-ENTRY.xml'
  OR resp->'technical_rejection'->>'request_sha256' IS DISTINCT FROM p->'artifacts'->'zip'->>'sha256'
  OR resp->'technical_rejection'->>'xml_sha256' IS DISTINCT FROM p->'artifacts'->'xml'->>'sha256'
  THEN RAISE EXCEPTION 'C2 absent correlated technical cause'; END IF;
 EXECUTE format('SELECT to_jsonb(t) FROM %I t WHERE %I=$1',box,col) INTO job USING NEW.successor_id;
 IF job->>'status' IS DISTINCT FROM 'pending' THEN RAISE EXCEPTION 'C2 missing new durable outbox'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5c2_h4_corrections_guard BEFORE INSERT ON h5c2_h4_corrections FOR EACH ROW EXECUTE FUNCTION h5c2_link_guard();
CREATE TRIGGER h5c2_h4_corrections_frozen BEFORE UPDATE OR DELETE ON h5c2_h4_corrections FOR EACH ROW EXECUTE FUNCTION h5c2_immutable();
CREATE TRIGGER h5c2_h5b_corrections_guard BEFORE INSERT ON h5c2_h5b_corrections FOR EACH ROW EXECUTE FUNCTION h5c2_link_guard();
CREATE TRIGGER h5c2_h5b_corrections_frozen BEFORE UPDATE OR DELETE ON h5c2_h5b_corrections FOR EACH ROW EXECUTE FUNCTION h5c2_immutable();
CREATE TRIGGER h5c2_void_corrections_guard BEFORE INSERT ON h5c2_void_corrections FOR EACH ROW EXECUTE FUNCTION h5c2_link_guard();
CREATE TRIGGER h5c2_void_corrections_frozen BEFORE UPDATE OR DELETE ON h5c2_void_corrections FOR EACH ROW EXECUTE FUNCTION h5c2_immutable();
CREATE TRIGGER h5c2_invoice_reissues_guard BEFORE INSERT ON h5c2_invoice_reissues FOR EACH ROW EXECUTE FUNCTION h5c2_link_guard();
CREATE TRIGGER h5c2_invoice_reissues_frozen BEFORE UPDATE OR DELETE ON h5c2_invoice_reissues FOR EACH ROW EXECUTE FUNCTION h5c2_immutable();
CREATE OR REPLACE FUNCTION h4_members_consistent() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target uuid; expected text[]; actual text[];
BEGIN
    IF TG_TABLE_NAME='h4_summaries' THEN target:=NEW.id; ELSE target:=NEW.summary_id; END IF;
    SELECT array_agg(m.value->>'id' ORDER BY m.ordinality) INTO expected
      FROM h4_summaries s, jsonb_array_elements(s.snapshot->'members') WITH ORDINALITY m(value, ordinality) WHERE s.id=target;
    SELECT array_agg(boleta_id::text ORDER BY position) INTO actual FROM h4_members WHERE summary_id=h5c2_anchor('h4',target);
    IF actual IS DISTINCT FROM expected OR expected IS NULL THEN RAISE EXCEPTION 'H4 members differ from frozen manifest'; END IF;
    IF EXISTS(SELECT 1 FROM h4_members m JOIN h4_boletas b ON b.id=m.boleta_id JOIN h4_summaries s ON s.id=target
              WHERE m.summary_id=h5c2_anchor('h4',target) AND (b.user_id<>s.user_id OR b.emission_date<>s.reference_date)) THEN
        RAISE EXCEPTION 'H4 owner or date mismatch';
    END IF;
    RETURN NEW;
END $$;
CREATE OR REPLACE FUNCTION h5b_note_original() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE source h4_boletas%ROWTYPE; rc h4_summaries%ROWTYPE;
BEGIN
 SELECT * INTO source FROM h4_boletas WHERE id=NEW.original_id FOR UPDATE;
 SELECT s.* INTO rc FROM h4_summaries s WHERE s.id=h5c2_effective('h4',(SELECT summary_id FROM h4_members WHERE boleta_id=source.id));
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
CREATE OR REPLACE FUNCTION h5b_members_consistent() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target uuid; expected text[]; actual text[];
BEGIN
 IF TG_TABLE_NAME='h5b_summaries' THEN target:=NEW.id; ELSE target:=NEW.summary_id; END IF;
 SELECT array_agg(m.value->>'id' ORDER BY m.ordinality) INTO expected FROM h5b_summaries s,
  jsonb_array_elements(s.snapshot->'members') WITH ORDINALITY m(value,ordinality) WHERE s.id=target;
 SELECT array_agg(note_id::text ORDER BY position) INTO actual FROM h5b_members WHERE summary_id=h5c2_anchor('h5b',target);
 IF actual IS DISTINCT FROM expected OR expected IS NULL THEN RAISE EXCEPTION 'H5B frozen members differ'; END IF;
 IF EXISTS(SELECT 1 FROM h5b_members m JOIN h5b_notes n ON n.id=m.note_id JOIN h5b_summaries s ON s.id=target
  WHERE m.summary_id=h5c2_anchor('h5b',target) AND (n.user_id<>s.user_id OR n.emission_date<>s.reference_date)) THEN RAISE EXCEPTION 'H5B member owner/date mismatch'; END IF;
 RETURN NEW;
END $$;
CREATE OR REPLACE FUNCTION h5c1_consistent() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target uuid; expected text[]; actual text[];
BEGIN
 IF TG_TABLE_NAME='h5c1_batches' THEN target:=NEW.id; ELSE target:=NEW.batch_id; END IF;
 SELECT array_agg((m.value->>'kind')||':'|| (m.value->>'id') ORDER BY m.ordinality) INTO expected FROM h5c1_batches b,
  jsonb_array_elements(b.snapshot->'members') WITH ORDINALITY m(value,ordinality) WHERE b.id=target;
 SELECT array_agg(kind||':'||document_id::text ORDER BY position) INTO actual FROM h5c1_members WHERE batch_id=h5c2_anchor('void',target);
 IF actual IS DISTINCT FROM expected OR expected IS NULL THEN RAISE EXCEPTION 'H5C1 frozen members differ'; END IF;
 RETURN NEW;
END $$;

CREATE FUNCTION h5c2_preserve_invoice() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM h5c2_invoice_reissues WHERE original_id=OLD.id) THEN RAISE EXCEPTION 'C2 rejected invoice frozen'; END IF;
 IF EXISTS(SELECT 1 FROM h5c2_invoice_reissues WHERE successor_id=OLD.id) THEN
  IF TG_OP='DELETE' OR (to_jsonb(NEW)-ARRAY['transport_status','simulated_response','updated_at']) IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['transport_status','simulated_response','updated_at'])
   THEN RAISE EXCEPTION 'C2 successor fiscal identity frozen'; END IF;
  IF OLD.transport_status IN ('simulated_accepted','simulated_rejected') THEN RAISE EXCEPTION 'C2 successor terminal'; END IF;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5c2_preserve_invoice BEFORE UPDATE OR DELETE ON invoices FOR EACH ROW EXECUTE FUNCTION h5c2_preserve_invoice();
CREATE FUNCTION h5c2_preserve_receipt() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE col text; doc uuid; links text;
BEGIN
 CASE TG_TABLE_NAME
 WHEN 'invoice_outbox','simulation_receipts','invoice_transport_events' THEN col:='invoice_id';links:='h5c2_invoice_reissues';
 WHEN 'h4_outbox','h4_simulation_receipts','h4_events' THEN col:='summary_id';links:='h5c2_h4_corrections';
 ELSE RETURN OLD;
 END CASE;
 doc:=(to_jsonb(OLD)->>col)::uuid;
 IF links='h5c2_invoice_reissues' AND EXISTS(SELECT 1 FROM h5c2_invoice_reissues WHERE original_id=doc) THEN RAISE EXCEPTION 'C2 source evidence frozen'; END IF;
 IF links='h5c2_h4_corrections' AND EXISTS(SELECT 1 FROM h5c2_h4_corrections WHERE original_id=doc) THEN RAISE EXCEPTION 'C2 source evidence frozen'; END IF;
 IF TG_OP='DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
CREATE TRIGGER h5c2_preserve_receipt BEFORE UPDATE OR DELETE ON invoice_outbox FOR EACH ROW EXECUTE FUNCTION h5c2_preserve_receipt();
CREATE TRIGGER h5c2_preserve_receipt BEFORE UPDATE OR DELETE ON simulation_receipts FOR EACH ROW EXECUTE FUNCTION h5c2_preserve_receipt();
CREATE TRIGGER h5c2_preserve_receipt BEFORE UPDATE OR DELETE ON invoice_transport_events FOR EACH ROW EXECUTE FUNCTION h5c2_preserve_receipt();
CREATE TRIGGER h5c2_preserve_receipt BEFORE UPDATE OR DELETE ON h4_outbox FOR EACH ROW EXECUTE FUNCTION h5c2_preserve_receipt();
CREATE TRIGGER h5c2_preserve_receipt BEFORE UPDATE OR DELETE ON h4_simulation_receipts FOR EACH ROW EXECUTE FUNCTION h5c2_preserve_receipt();
CREATE TRIGGER h5c2_preserve_receipt BEFORE UPDATE OR DELETE ON h4_events FOR EACH ROW EXECUTE FUNCTION h5c2_preserve_receipt();
SQL);
    }
    public function down(): void { throw new RuntimeException('C2 preserva historicos; sin borrado automatico.'); }
};
