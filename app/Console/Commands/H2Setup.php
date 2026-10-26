<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class H2Setup extends Command
{
    protected $signature = 'h2:setup {--testing : Preparar únicamente la base emisor_h2_test}';
    protected $description = 'Migración aditiva y operador ficticio; nunca reinicializa ni borra datos';

    public function handle(): int
    {
        if (!app()->environment(['local', 'testing']) || config('database.connections.pgsql.host') !== 'postgres'
            || !in_array(config('database.connections.pgsql.database'), ['emisor_h1', 'emisor_h2_test'], true)) {
            throw new RuntimeException('Preparación limitada al PostgreSQL ficticio de Compose.');
        }
        if ($this->option('testing')) {
            if (!DB::selectOne("SELECT 1 FROM pg_database WHERE datname='emisor_h2_test'")) {
                DB::statement('CREATE DATABASE emisor_h2_test');
            }
            config(['database.connections.pgsql.database' => 'emisor_h2_test']);
            DB::purge('pgsql');
        }
        foreach (['framework/sessions', 'framework/views', 'framework/cache/data', 'framework/testing'] as $directory) {
            if (!is_dir(storage_path($directory))) {
                mkdir(storage_path($directory), 0700, true);
            }
        }
        if ($this->call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException('Migración fallida; no se declara preparado H2.');
        }
        if (!$this->option('testing')) {
            $password = config('h2.operator_password');
            if (!config('app.key') || !is_string($password) || strlen($password) < 24) {
                throw new RuntimeException('Preparar APP_KEY y H2_OPERATOR_PASSWORD locales antes de habilitar HTTP.');
            }
            $user = User::query()->where('email', config('h2.operator_email'))->first();
            if ($user && !Hash::check($password, $user->password)) {
                throw new RuntimeException('El operador existente no coincide; no se modifica su contraseña.');
            }
            if (!$user) {
                User::query()->create(['name' => 'Operador ficticio H2', 'email' => config('h2.operator_email'), 'password' => Hash::make($password)]);
            }
        }
        $this->info('H2 ficticio preparado sin borrar datos. No habilita emisión real.');
        return self::SUCCESS;
    }
}
