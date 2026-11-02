<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->bigInteger('credential_version')->default(0));
        DB::statement('ALTER TABLE users ADD CHECK (credential_version >= 0)');
        Schema::create('local_access_recoveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->bigInteger('requested_user_id');
            $t->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('authorized_by_declared',80);
            $t->bigInteger('version_before')->nullable();
            $t->bigInteger('version_after')->nullable();
            $t->string('outcome',16);$t->string('reason_code',40)->nullable();
            $t->timestampTz('created_at');$t->timestampTz('finished_at')->nullable();
            $t->index(['user_id','version_after']);
        });
        DB::statement("ALTER TABLE local_access_recoveries ADD CHECK (requested_user_id>0 AND outcome IN ('started','completed','rejected','cancelled','failed'))");
        DB::statement("ALTER TABLE local_access_recoveries ADD CHECK ((outcome='started' AND finished_at IS NULL AND version_after IS NULL) OR (outcome<>'started' AND finished_at IS NOT NULL))");
        DB::statement("ALTER TABLE local_access_recoveries ADD CHECK ((outcome='completed' AND user_id IS NOT NULL AND version_before IS NOT NULL AND version_after IS NOT NULL AND user_id=requested_user_id AND version_after=version_before+1 AND reason_code IS NULL) OR (outcome<>'completed' AND version_after IS NULL))");
        DB::statement("CREATE UNIQUE INDEX local_recovery_version ON local_access_recoveries(user_id,version_after) WHERE outcome='completed'");
        // Un cambio SQL de hash también invalida sesiones; sin auditoría correlacionada H2Setup lo rechaza.
        DB::unprepared(<<<'SQL'
CREATE FUNCTION h71_user_credentials() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.password IS DISTINCT FROM OLD.password THEN
        NEW.credential_version := OLD.credential_version + 1;
    ELSIF NEW.credential_version IS DISTINCT FROM OLD.credential_version THEN
        RAISE EXCEPTION 'Credential version cannot change independently';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER h71_user_credentials BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION h71_user_credentials();
CREATE FUNCTION h71_recovery_history() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP='DELETE' OR OLD.outcome<>'started' THEN
        RAISE EXCEPTION 'Recovery history is immutable';
    END IF;
    IF (NEW.id,NEW.requested_user_id,NEW.user_id,NEW.authorized_by_declared,NEW.version_before,NEW.created_at)
       IS DISTINCT FROM (OLD.id,OLD.requested_user_id,OLD.user_id,OLD.authorized_by_declared,OLD.version_before,OLD.created_at)
       OR NEW.outcome='started' THEN
        RAISE EXCEPTION 'Recovery identity is immutable';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER h71_recovery_history BEFORE UPDATE OR DELETE ON local_access_recoveries FOR EACH ROW EXECUTE FUNCTION h71_recovery_history();
SQL);
    }

    public function down(): void { throw new RuntimeException('Reversión de credenciales/auditoría requiere un procedimiento autorizado; no borrar históricos.'); }
};
