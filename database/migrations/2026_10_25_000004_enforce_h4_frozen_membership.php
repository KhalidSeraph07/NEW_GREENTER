<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Verificacion diferida: el RC y todos sus miembros se crean en un solo commit.
        DB::unprepared(<<<'SQL'
CREATE FUNCTION h4_members_consistent() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target uuid; expected text[]; actual text[];
BEGIN
    IF TG_TABLE_NAME='h4_summaries' THEN target:=NEW.id; ELSE target:=NEW.summary_id; END IF;
    SELECT array_agg(m.value->>'id' ORDER BY m.ordinality) INTO expected
      FROM h4_summaries s, jsonb_array_elements(s.snapshot->'members') WITH ORDINALITY m(value, ordinality) WHERE s.id=target;
    SELECT array_agg(boleta_id::text ORDER BY position) INTO actual FROM h4_members WHERE summary_id=target;
    IF actual IS DISTINCT FROM expected OR expected IS NULL THEN RAISE EXCEPTION 'H4 members differ from frozen manifest'; END IF;
    IF EXISTS(SELECT 1 FROM h4_members m JOIN h4_boletas b ON b.id=m.boleta_id JOIN h4_summaries s ON s.id=m.summary_id
              WHERE s.id=target AND (b.user_id<>s.user_id OR b.emission_date<>s.reference_date)) THEN
        RAISE EXCEPTION 'H4 owner or date mismatch';
    END IF;
    RETURN NEW;
END $$;
CREATE CONSTRAINT TRIGGER h4_members_consistent AFTER INSERT ON h4_members DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h4_members_consistent();
CREATE CONSTRAINT TRIGGER h4_summary_consistent AFTER INSERT ON h4_summaries DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION h4_members_consistent();
SQL);
    }

    public function down(): void { throw new RuntimeException('Sin retirada automatica de integridad H4.'); }
};
