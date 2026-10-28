<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        // Defecto probado por SQL adversarial: H4 previo no exigia sobre de resultado.
        // La nueva guarda alcanza solo sucesoras C2; no cambia ciclos historicos H4.
        DB::unprepared(<<<'SQL'
CREATE FUNCTION h5c2_rc_result() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NOT EXISTS(SELECT 1 FROM h5c2_h4_corrections WHERE successor_id=OLD.id) THEN RETURN NEW; END IF;
 IF NOT ((OLD.state='pending_send' AND NEW.state='sending')
   OR (OLD.state='sending' AND NEW.state IN ('awaiting','uncertain'))
   OR (OLD.state='awaiting' AND NEW.state='querying')
   OR (OLD.state='querying' AND NEW.state IN ('awaiting','uncertain','sim_accepted','sim_rejected'))
   OR (OLD.state='uncertain' AND NEW.state='awaiting')) THEN RAISE EXCEPTION 'C2 invalid RC transition'; END IF;
 IF NEW.state IN ('sim_accepted','sim_rejected') AND
  (NEW.result->>'simulated' IS DISTINCT FROM 'true' OR NEW.result->>'official_cdr' IS DISTINCT FROM 'false'
   OR NEW.result->>'outcome' IS DISTINCT FROM CASE WHEN NEW.state='sim_accepted' THEN 'accepted' ELSE 'rejected' END
   OR NEW.result->'response'->>'simulated' IS DISTINCT FROM 'true'
   OR NEW.result->'response'->>'source' IS DISTINCT FROM 'H4_LOCAL_FAKE'
   OR NEW.result->'response'->>'ticket' IS DISTINCT FROM NEW.ticket OR NEW.ticket IS NULL
   OR NEW.result->'response'->>'statusCode' IS DISTINCT FROM CASE WHEN NEW.state='sim_accepted' THEN '0' ELSE '99' END
   OR NEW.result->'response'->>'constancia' IS NULL
   OR NOT EXISTS(SELECT 1 FROM h4_simulation_receipts WHERE summary_id=NEW.id AND ticket=NEW.ticket AND request_hash=NEW.artifacts->'zip'->>'sha256'))
 THEN RAISE EXCEPTION 'C2 unproven RC result'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5c2_rc_result BEFORE UPDATE ON h4_summaries FOR EACH ROW EXECUTE FUNCTION h5c2_rc_result();
SQL);
    }
    public function down(): void {throw new RuntimeException('C2 preserva guardas y evidencias.');}
};
