<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('OPERADOR');
            $table->boolean('is_active')->default(true);
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_local_role CHECK (role IN ('ADMIN','OPERADOR'))");
    }
    public function down(): void
    {
        throw new RuntimeException('No retirar permisos o cuentas sin un plan de reversión autorizado.');
    }
};
