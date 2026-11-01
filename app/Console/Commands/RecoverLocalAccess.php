<?php
namespace App\Console\Commands;

use App\Accounts\LocalAccounts;
use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RecoverLocalAccess extends Command
{
    protected $signature='h7:recover-access {--user-id= : ID numérico de una cuenta existente} {--authorized-by= : Referencia declarada del responsable, sin secretos}';
    protected $description='Recuperación supervisada local ficticia; sin crear, promover ni reactivar cuentas';

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try { return parent::run($input,$output); }
        catch (\Throwable) {
            // También proteger errores de sintaxis: no reflejar argumentos que pudieran contener secretos.
            $output->writeln('No se confirma el resultado de la recuperación. Revisar sintaxis/entorno y auditoría si se inició una intervención.');
            return self::FAILURE;
        }
    }

    public function handle(LocalAccounts $accounts): int
    {
        if (!$this->input->isInteractive()) { $this->error('Requiere consola interactiva y entrada oculta.');return self::FAILURE; }
        $raw=$this->option('user-id');
        if (!is_string($raw) || !ctype_digit($raw) || (string)(int)$raw!==$raw || (int)$raw<1) {
            $this->error('Indica un ID numérico inequívoco de cuenta existente.');return self::FAILURE;
        }
        [$user,$attempt]=$accounts->startRecovery((int)$raw,(string)$this->option('authorized-by'));
        $this->line('Referencia de intervención: '.$attempt);
        if (!$user) { $accounts->finishRecovery($attempt,'rejected','unknown_account');$this->error('Cuenta inexistente.');return self::FAILURE; }
        $safe=fn($v)=>preg_replace('/\p{C}/u','?',(string)$v);
        $this->line('Cuenta '.$user->id.' · '.$safe($user->email).' · '.$safe($user->name).' · Rol '.$safe($user->role));
        $this->line('Responsable declarado: '.$this->option('authorized-by').'. Declaración externa; el CLI no acredita su identidad ni autorización digital.');
        if (!$user->is_active || !in_array($user->role,User::ROLES,true)) {
            $accounts->finishRecovery($attempt,'rejected',!$user->is_active?'inactive_account':'invalid_role');
            $this->error('Cuenta desactivada o rol no permitido; no se cambia.');return self::FAILURE;
        }
        if (!$this->confirm('¿Confirmas autorización operativa explícita para recuperar únicamente esta cuenta?',false)) {
            $accounts->finishRecovery($attempt,'cancelled','cancelled');$this->warn('Intervención cancelada.');return self::FAILURE;
        }
        $password=$this->secret('Contraseña nueva (política H6: mínimo 16 caracteres, mayúsculas, minúsculas y números; máximo 72 bytes)',false);
        $confirmation=$this->secret('Confirmar contraseña nueva',false);
        try { $version=$accounts->recover($user,$attempt,(string)$password,(string)$confirmation); }
        catch (\Illuminate\Validation\ValidationException) {
            $this->error('Contraseña rechazada por la política H6 o confirmación distinta. Sin recuperación completada.');return self::FAILURE;
        } catch (\Throwable) {
            $this->error('No se confirma la recuperación. Revisar la intervención en auditoría; no repetir a ciegas.');return self::FAILURE;
        }
        $this->info('Recuperación completada para cuenta '.$user->id.', versión '.$version.'. Las sesiones previas dejan de autorizar nuevas operaciones.');
        return self::SUCCESS;
    }
}
