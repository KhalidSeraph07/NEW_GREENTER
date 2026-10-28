<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE h5c1_outbox ADD CHECK(status<>'processing' OR (token IS NOT NULL AND reserved_until IS NOT NULL))");
        DB::statement("ALTER TABLE h5c1_outbox ADD CHECK(poll_attempts BETWEEN 0 AND 9)");
        DB::unprepared(<<<'SQL'
CREATE FUNCTION h5c1_initial_envelope() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
 IF NEW.state<>'pending_send' OR NEW.ticket IS NOT NULL OR NEW.result IS NOT NULL
  OR NEW.snapshot->>'mode' IS DISTINCT FROM 'fictitious' OR NEW.snapshot->>'route' IS DISTINCT FROM NEW.route
  OR NEW.snapshot->>'name' IS DISTINCT FROM NEW.name
  OR NEW.snapshot->>'reference_date' IS DISTINCT FROM NEW.reference_date::text
  OR NEW.snapshot->>'issue_date' IS DISTINCT FROM NEW.issue_date::text
  OR NEW.name IS DISTINCT FROM '00000000000-'||NEW.route||'-'||to_char(NEW.issue_date,'YYYYMMDD')||'-'||(NEW.snapshot->>'number')
  OR (NEW.snapshot->>'number')::bigint NOT BETWEEN 1 AND 99999
  OR (NEW.route='RC' AND NEW.snapshot->>'operation' IS DISTINCT FROM '3')
 THEN RAISE EXCEPTION 'H5C1 invalid initial communication'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5c1_initial_envelope BEFORE INSERT ON h5c1_batches FOR EACH ROW EXECUTE FUNCTION h5c1_initial_envelope();
CREATE FUNCTION h5c1_result_envelope() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
 IF NEW.state IN ('sim_accepted','sim_rejected') AND
  (NEW.result->>'simulated' IS DISTINCT FROM 'true' OR NEW.result->>'official_cdr' IS DISTINCT FROM 'false'
   OR NEW.result->>'outcome' IS DISTINCT FROM CASE WHEN NEW.state='sim_accepted' THEN 'accepted' ELSE 'rejected' END
   OR NEW.result->'response'->>'simulated' IS DISTINCT FROM 'true'
   OR NEW.result->'response'->>'source' IS DISTINCT FROM 'H5C1_LOCAL_FAKE'
   OR NEW.result->'response'->>'ticket' IS DISTINCT FROM NEW.ticket OR NEW.ticket IS NULL
   OR NEW.result->'response'->>'statusCode' IS DISTINCT FROM CASE WHEN NEW.state='sim_accepted' THEN '0' ELSE '99' END
   OR NEW.result->'response'->>'constancia' IS NULL)
 THEN RAISE EXCEPTION 'H5C1 missing fictitious result envelope'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER h5c1_result_envelope BEFORE UPDATE ON h5c1_batches FOR EACH ROW EXECUTE FUNCTION h5c1_result_envelope();
SQL);
    }
    public function down(): void { throw new RuntimeException('H5-C1 no retira controles ni borra evidencias automaticamente.'); }
};
