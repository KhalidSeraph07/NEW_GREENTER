<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION h5c2_preserve_receipt() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE col text; doc uuid; links text; protected boolean;
BEGIN
 CASE TG_TABLE_NAME
 WHEN 'invoice_outbox','simulation_receipts','invoice_transport_events' THEN col:='invoice_id';links:='h5c2_invoice_reissues';
 WHEN 'h4_outbox','h4_simulation_receipts','h4_events' THEN col:='summary_id';links:='h5c2_h4_corrections';
 WHEN 'h5b_outbox' THEN col:='summary_id';links:='h5c2_h5b_corrections';
 WHEN 'h5c1_outbox' THEN col:='batch_id';links:='h5c2_void_corrections';
 ELSE RAISE EXCEPTION 'C2 unknown evidence table'; END CASE;
 doc:=(to_jsonb(OLD)->>col)::uuid;
 IF TG_TABLE_NAME LIKE '%outbox' THEN
  EXECUTE format('SELECT EXISTS(SELECT 1 FROM %I WHERE original_id=$1)',links) INTO protected USING doc;
 ELSE
  EXECUTE format('SELECT EXISTS(SELECT 1 FROM %I WHERE original_id=$1 OR successor_id=$1)',links) INTO protected USING doc;
 END IF;
 IF protected THEN RAISE EXCEPTION 'C2 causal evidence frozen'; END IF;
 IF TG_OP='DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
CREATE TRIGGER h5c2_preserve_receipt BEFORE UPDATE OR DELETE ON h5b_outbox FOR EACH ROW EXECUTE FUNCTION h5c2_preserve_receipt();
CREATE TRIGGER h5c2_preserve_receipt BEFORE UPDATE OR DELETE ON h5c1_outbox FOR EACH ROW EXECUTE FUNCTION h5c2_preserve_receipt();
SQL);}
    public function down(): void {throw new RuntimeException('C2 no desprotege pruebas causales.');}
};
