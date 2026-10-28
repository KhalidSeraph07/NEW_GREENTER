<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION h5c2_link_guard() RETURNS trigger LANGUAGE plpgsql AS $$
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
  OR c->>'idempotency_key' IS DISTINCT FROM NEW.idempotency_key::text OR (f<>'invoice' AND c->>'payload_hash' IS DISTINCT FROM NEW.request_hash)
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
   OR job->>'phase' IS DISTINCT FROM 'poll' OR ev->>'kind' IS DISTINCT FROM 'result_saved' OR ev->>'token' IS DISTINCT FROM job->>'token' OR ev->'payload' IS DISTINCT FROM resp
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
 IF job->>'status' IS DISTINCT FROM 'pending' OR (f='invoice' AND (job->>'attempts')::int<>0) OR (f<>'invoice' AND (job->>'send_attempts')::int<>0) THEN RAISE EXCEPTION 'C2 missing new durable outbox'; END IF;
 RETURN NEW;
END $$;
CREATE OR REPLACE FUNCTION h5c2_preserve_invoice() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM h5c2_invoice_reissues WHERE original_id=OLD.id) THEN RAISE EXCEPTION 'C2 rejected invoice frozen'; END IF;
 IF EXISTS(SELECT 1 FROM h5c2_invoice_reissues WHERE successor_id=OLD.id) THEN
  IF TG_OP='DELETE' OR (to_jsonb(NEW)-ARRAY['transport_status','simulated_response','updated_at']) IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['transport_status','simulated_response','updated_at'])
   THEN RAISE EXCEPTION 'C2 successor fiscal identity frozen'; END IF;
  IF NOT ((OLD.transport_status='pending_simulation' AND NEW.transport_status='simulation_processing')
    OR (OLD.transport_status='simulation_retry_wait' AND NEW.transport_status='simulation_processing')
    OR (OLD.transport_status='simulation_processing' AND NEW.transport_status IN ('simulation_retry_wait','simulation_uncertain','simulation_failed','simulated_accepted','simulated_rejected'))
    OR (OLD.transport_status='simulation_uncertain' AND NEW.transport_status IN ('simulated_accepted','simulated_rejected'))) THEN RAISE EXCEPTION 'C2 successor invalid transition'; END IF;
  IF NEW.transport_status IN ('simulated_accepted','simulated_rejected') AND
    (NEW.simulated_response IS DISTINCT FROM (SELECT response FROM simulation_receipts WHERE invoice_id=NEW.id)
      OR NEW.simulated_response->>'simulated' IS DISTINCT FROM 'true' OR NEW.simulated_response->>'source' IS DISTINCT FROM 'H2_LOCAL_FAKE'
      OR NEW.simulated_response->>'invoice_id' IS DISTINCT FROM NEW.id::text
      OR NEW.simulated_response->>'request_sha256' IS DISTINCT FROM NEW.artifacts->'zip'->>'sha256'
      OR NEW.simulated_response->>'scenario' IS DISTINCT FROM CASE WHEN NEW.transport_status='simulated_accepted' THEN 'accepted' ELSE 'rejected' END)
    THEN RAISE EXCEPTION 'C2 unproven successor result'; END IF;
 END IF;
 IF TG_OP='DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
SQL);
    }
    public function down(): void {throw new RuntimeException('C2 no retira guardas automaticamente.');}
};
