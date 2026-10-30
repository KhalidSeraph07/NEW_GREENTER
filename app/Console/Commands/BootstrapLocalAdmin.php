<?php
namespace App\Console\Commands;

use App\Accounts\LocalAccounts;
use Illuminate\Console\Command;

final class BootstrapLocalAdmin extends Command
{
    protected $signature = 'h6:bootstrap-admin {--email=} {--name=}';
    protected $description = 'Crear el primer ADMIN local explícito; nunca promover ni sobrescribir cuentas.';
    public function handle(LocalAccounts $accounts): int
    {
        if (!app()->environment(['local','testing']) || config('h2.mode') !== 'fictitious'
            || config('database.connections.pgsql.host') !== 'postgres'
            || !in_array(config('database.connections.pgsql.database'), ['emisor_h1','emisor_h2_test'], true)) {
            throw new \RuntimeException('Solo instalación local ficticia.');
        }
        if (!$this->input->isInteractive()) { throw new \RuntimeException('Requiere terminal interactivo y contraseña oculta.'); }
        $password = $this->secret('Contraseña nueva (mínimo 16 caracteres, mayúsculas, minúsculas y números)');
        $confirmation = $this->secret('Confirmar contraseña');
        try { $user = $accounts->create(['email'=>$this->option('email'), 'name'=>$this->option('name'), 'role'=>'ADMIN',
            'password'=>$password, 'password_confirmation'=>$confirmation], true);
        } catch (\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\ConflictHttpException $error) {
            $this->error('No se creó ADMIN: cuenta existente, contraseña inválida o ADMIN ya establecido.');
            return self::FAILURE;
        }
        $this->info('ADMIN local creado, id '.$user->id.'. Sin credenciales fiscales.');
        return self::SUCCESS;
    }
}
