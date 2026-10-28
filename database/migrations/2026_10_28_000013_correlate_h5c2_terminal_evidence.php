<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION h5c2_terminal_evidence() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE f text; links text; box text; col text; events text; receipt text; terminal boolean;
 resp jsonb; job jsonb; ev jsonb; rec jsonb; state text;
BEGIN
 CASE TG_TABLE_NAME
 WHEN 'invoices' THEN f:='invoice';links:='h5c2_invoice_reissues';box:='invoice_outbox';col:='invoice_id';events:='invoice_transport_events';receipt:='simulation_receipts';
 WHEN 'h4_summaries' THEN f:='h4';links:='h5c2_h4_corrections';box:='h4_outbox';col:='summary_id';events:='h4_events';receipt:='h4_simulation_receipts';
 WHEN 'h5b_summaries' THEN f:='h5b';links:='h5c2_h5b_corrections';box:='h5b_outbox';col:='summary_id';events:='h5b_events';receipt:='h5b_simulation_receipts';
 WHEN 'h5c1_batches' THEN f:='void';links:='h5c2_void_corrections';box:='h5c1_outbox';col:='batch_id';events:='h5c1_events';receipt:='h5c1_simulation_receipts';
 END CASE;
 EXECUTE format('SELECT EXISTS(SELECT 1 FROM %I WHERE successor_id=$1)',links) INTO terminal USING NEW.id;
 IF NOT terminal THEN RETURN NEW; END IF;
 IF f='invoice' THEN state:=NEW.transport_status;resp:=NEW.simulated_response;
 ELSE state:=NEW.state;resp:=NEW.result->'response'; END IF;
 IF state NOT IN ('sim_accepted','sim_rejected','simulated_accepted','simulated_rejected') THEN RETURN NEW; END IF;
 EXECUTE format('SELECT to_jsonb(t) FROM %I t WHERE %I=$1',box,col) INTO job USING NEW.id;
 EXECUTE format('SELECT to_jsonb(t) FROM %I t WHERE %I=$1',receipt,col) INTO rec USING NEW.id;
 IF f='invoice' THEN
  EXECUTE format('SELECT to_jsonb(t) FROM %I t WHERE %I=$1 AND kind IN (''result'',''recovered'') ORDER BY id LIMIT 1',events,col) INTO ev USING NEW.id;
  IF rec->'response' IS DISTINCT FROM resp OR ev->>'attempt_token' IS DISTINCT FROM job->>'attempt_token' THEN RAISE EXCEPTION 'C2 uncorrelated invoice result'; END IF;
 ELSE
  EXECUTE format('SELECT to_jsonb(t) FROM %I t WHERE %I=$1 AND kind=''result_saved'' ORDER BY id LIMIT 1',events,col) INTO ev USING NEW.id;
  IF job->>'phase' IS DISTINCT FROM 'poll' OR rec->>'ticket' IS DISTINCT FROM NEW.ticket OR rec->>'request_hash' IS DISTINCT FROM NEW.artifacts->'zip'->>'sha256'
   OR ev->>'token' IS DISTINCT FROM job->>'token' OR ev->'payload' IS DISTINCT FROM resp THEN RAISE EXCEPTION 'C2 uncorrelated communication result'; END IF;
 END IF;
 IF job->>'status' IS DISTINCT FROM 'completed' OR ev IS NULL OR (ev->>'attention')::boolean IS DISTINCT FROM (state IN ('sim_rejected','simulated_rejected'))
 THEN RAISE EXCEPTION 'C2 missing durable terminal evidence'; END IF;
 RETURN NEW;
END $$;
CREATE CONSTRAINT TRIGGER h5c2_terminal_evidence AFTER UPDATE ON invoices DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h5c2_terminal_evidence();
CREATE CONSTRAINT TRIGGER h5c2_terminal_evidence AFTER UPDATE ON h4_summaries DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h5c2_terminal_evidence();
CREATE CONSTRAINT TRIGGER h5c2_terminal_evidence AFTER UPDATE ON h5b_summaries DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h5c2_terminal_evidence();
CREATE CONSTRAINT TRIGGER h5c2_terminal_evidence AFTER UPDATE ON h5c1_batches DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h5c2_terminal_evidence();
SQL);
    }
    public function down(): void {throw new RuntimeException('C2 conserva evidencia transaccional.');}
};
