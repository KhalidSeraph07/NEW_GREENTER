<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h4_counters', function (Blueprint $t) { $t->string('series')->primary(); $t->bigInteger('next_number'); });
        DB::table('h4_counters')->insert([['series' => 'B000', 'next_number' => 1], ['series' => 'RC', 'next_number' => 1]]);
        DB::statement('ALTER TABLE h4_counters ADD CHECK (next_number > 0)');
        Schema::create('h4_boletas', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('idempotency_key'); $t->char('payload_hash', 64); $t->bigInteger('number')->unique();
            $t->date('emission_date'); $t->jsonb('snapshot'); $t->jsonb('artifacts'); $t->timestampsTz();
            $t->unique(['user_id', 'idempotency_key']);
        });
        DB::statement("ALTER TABLE h4_boletas ADD CHECK (number>0 AND snapshot->>'document_type'='03' AND snapshot->>'series'='B000' AND snapshot->>'issuer_ruc'='00000000000')");
        Schema::create('h4_summaries', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('idempotency_key'); $t->char('payload_hash', 64); $t->string('name')->unique();
            $t->date('reference_date'); $t->date('issue_date'); $t->jsonb('snapshot'); $t->jsonb('artifacts');
            $t->string('state')->default('pending_send'); $t->string('ticket')->nullable()->unique();
            $t->jsonb('result')->nullable(); $t->timestampsTz(); $t->unique(['user_id', 'idempotency_key']);
        });
        DB::statement("ALTER TABLE h4_summaries ADD CHECK (state IN ('pending_send','sending','awaiting','querying','uncertain','sim_accepted','sim_rejected'))");
        DB::statement("ALTER TABLE h4_summaries ADD CHECK (ticket IS NULL OR ticket ~ '^SIM-H4-[0-9a-f-]{36}$')");
        Schema::create('h4_members', function (Blueprint $t) {
            $t->uuid('summary_id'); $t->uuid('boleta_id')->unique(); $t->integer('position');
            $t->foreign('summary_id')->references('id')->on('h4_summaries')->restrictOnDelete();
            $t->foreign('boleta_id')->references('id')->on('h4_boletas')->restrictOnDelete();
            $t->primary(['summary_id', 'boleta_id']); $t->unique(['summary_id', 'position']);
        });
        Schema::create('h4_outbox', function (Blueprint $t) {
            $t->uuid('summary_id')->primary(); $t->foreign('summary_id')->references('id')->on('h4_summaries')->restrictOnDelete();
            $t->string('phase')->default('send'); $t->string('status')->default('pending');
            $t->integer('send_attempts')->default(0); $t->integer('poll_attempts')->default(0); $t->integer('cycle_attempts')->default(0);
            $t->uuid('token')->nullable(); $t->timestampTz('reserved_until')->nullable(); $t->timestampTz('available_at')->nullable(); $t->timestampsTz();
        });
        DB::statement("ALTER TABLE h4_outbox ADD CHECK (phase IN ('send','poll') AND status IN ('pending','processing','uncertain','completed') AND send_attempts BETWEEN 0 AND 1 AND poll_attempts>=0 AND cycle_attempts BETWEEN 0 AND 3)");
        Schema::create('h4_simulation_receipts', function (Blueprint $t) {
            $t->uuid('summary_id')->primary(); $t->foreign('summary_id')->references('id')->on('h4_summaries')->restrictOnDelete();
            $t->string('ticket')->unique(); $t->char('request_hash', 64); $t->timestampTz('created_at');
        });
        Schema::create('h4_events', function (Blueprint $t) {
            $t->id(); $t->uuid('summary_id'); $t->foreign('summary_id')->references('id')->on('h4_summaries')->restrictOnDelete();
            $t->string('kind'); $t->uuid('token')->nullable(); $t->boolean('attention')->default(false);
            $t->jsonb('payload')->nullable(); $t->timestampTz('created_at'); $t->index(['summary_id', 'id']);
        });
        DB::unprepared(<<<'SQL'
CREATE FUNCTION h4_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
    RAISE EXCEPTION 'H4 immutable record';
END $$;
CREATE TRIGGER h4_boleta_frozen BEFORE UPDATE OR DELETE ON h4_boletas FOR EACH ROW EXECUTE FUNCTION h4_immutable();
CREATE TRIGGER h4_members_frozen BEFORE UPDATE OR DELETE ON h4_members FOR EACH ROW EXECUTE FUNCTION h4_immutable();
CREATE FUNCTION h4_summary_frozen() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
    IF TG_OP='DELETE' THEN RAISE EXCEPTION 'H4 immutable summary'; END IF;
    IF (to_jsonb(NEW)-ARRAY['state','ticket','result','updated_at']) IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['state','ticket','result','updated_at'])
       OR (OLD.ticket IS NOT NULL AND NEW.ticket IS DISTINCT FROM OLD.ticket)
       OR OLD.state IN ('sim_accepted','sim_rejected') THEN RAISE EXCEPTION 'H4 immutable content or terminal result'; END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER h4_summary_frozen BEFORE UPDATE OR DELETE ON h4_summaries FOR EACH ROW EXECUTE FUNCTION h4_summary_frozen();
SQL);
    }

    public function down(): void { throw new RuntimeException('Sin borrado automatico de datos/evidencias H4.'); }
};
