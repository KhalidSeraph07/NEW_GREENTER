<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        // PostgreSQL aplica la ampliación del CHECK y la tabla en la transacción de migración.
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_local_role');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_local_role CHECK (role IN ('ADMIN','OPERADOR','RECEPTOR'))");
        Schema::create('recipient_document_access', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $t->string('kind', 16); $t->uuid('document_id');
            $t->timestampTz('created_at'); $t->timestampTz('revoked_at')->nullable();
            $t->index(['recipient_user_id','revoked_at']);
        });
        DB::statement("ALTER TABLE recipient_document_access ADD CHECK (kind IN ('invoice','boleta','invoice_note','boleta_note') AND recipient_user_id<>owner_user_id)");
        DB::statement('CREATE UNIQUE INDEX recipient_document_active ON recipient_document_access(recipient_user_id,kind,document_id) WHERE revoked_at IS NULL');
    }

    public function down(): void { throw new RuntimeException('No borrar autorizaciones ni retirar roles sin un plan de reversión autorizado.'); }
};
