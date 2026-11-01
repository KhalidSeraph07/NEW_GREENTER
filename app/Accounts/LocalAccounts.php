<?php
namespace App\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Hash, Validator};
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class LocalAccounts
{
    public function create(array $input, bool $bootstrap = false): User
    {
        return DB::transaction(function () use ($input, $bootstrap) {
            $this->lock();
            if ($bootstrap && User::query()->where('role', 'ADMIN')->exists()) {
                throw new ConflictHttpException('Ya existe ADMIN; usar la administración de cuentas.');
            }
            $email = $input['email'] ?? '';
            $input['email'] = is_string($email) ? strtolower(trim($email)) : $email;
            $v = Validator::make($input, [
                'name' => 'required|string|max:120', 'email' => 'required|email:rfc|max:254|unique:users,email',
                'role' => 'required|in:ADMIN,OPERADOR,RECEPTOR',
                'password' => $this->passwordRules(),
            ])->after(function ($validator) use ($input) {
                if (is_string($input['email']) && User::query()->whereRaw('lower(email) = ?', [$input['email']])->exists()) {
                    $validator->errors()->add('email', 'Ya existe una cuenta con ese correo.');
                }
            })->validate();
            $user = new User();
            $user->name = $v['name']; $user->email = $v['email'];
            $user->password = Hash::make($v['password']);
            $user->role = $bootstrap ? 'ADMIN' : $v['role']; $user->is_active = true;
            $user->save();
            return $user;
        });
    }
    public function deactivate(int $id): void
    {
        DB::transaction(function () use ($id) {
            $this->lock();
            $user = User::query()->lockForUpdate()->findOrFail($id);
            if (!$user->is_active) { return; }
            if ($user->role === 'ADMIN' && User::query()->where('role', 'ADMIN')->where('is_active', true)->count() <= 1) {
                throw new ConflictHttpException('No se puede desactivar al último ADMIN activo.');
            }
            // No cambiar timestamps, credenciales ni propiedad de documentos históricos.
            User::query()->whereKey($id)->toBase()->update(['is_active' => false]);
        });
    }

    public function startRecovery(int $id, string $authorizer): array
    {
        $this->recoveryEnvironment();
        Validator::make(['id'=>$id,'authorizer'=>$authorizer],[
            'id'=>'required|integer|min:1','authorizer'=>['required','string','regex:/\A[A-Z0-9][A-Z0-9._:-]{2,79}\z/'],
        ])->validate();
        $user=User::query()->find($id);$attempt=(string) Str::uuid();
        DB::table('local_access_recoveries')->insert(['id'=>$attempt,'requested_user_id'=>$id,'user_id'=>$user?->id,
            'authorized_by_declared'=>$authorizer,'version_before'=>$user?->credential_version,'outcome'=>'started','created_at'=>now('UTC')]);
        return [$user,$attempt];
    }

    public function finishRecovery(string $attempt, string $outcome, string $reason): void
    {
        $this->recoveryEnvironment();
        if (!in_array($outcome,['rejected','cancelled','failed'],true)
            || !in_array($reason,['unknown_account','inactive_account','invalid_role','cancelled','validation','account_changed','password_reused','persistence_error'],true)) {
            throw new \InvalidArgumentException('Resultado de recuperación no permitido.');
        }
        // No sobrescribir un éxito si se perdió la respuesta al COMMIT.
        DB::table('local_access_recoveries')->where('id',$attempt)->where('outcome','started')
            ->update(['outcome'=>$outcome,'reason_code'=>$reason,'finished_at'=>now('UTC')]);
    }

    public function recover(User $identified, string $attempt, string $password, string $confirmation): int
    {
        $this->recoveryEnvironment();
        try {
            Validator::make(['password'=>$password,'password_confirmation'=>$confirmation],['password'=>$this->passwordRules()])->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->finishRecovery($attempt,'rejected','validation');throw $e;
        }
        $hash=Hash::make($password);
        try {
            return DB::transaction(function () use ($identified,$attempt,$password,$hash) {
                $this->lock();$user=User::query()->lockForUpdate()->find($identified->id);
                $audit=DB::table('local_access_recoveries')->where('id',$attempt)->lockForUpdate()->sole();
                if (!$user || !$user->is_active || !in_array($user->role,User::ROLES,true)
                    || $user->role!==$identified->role || $user->email!==$identified->email
                    || $user->credential_version!==$identified->credential_version
                    || (int)$audit->user_id!==$user->id || (int)$audit->version_before!==$user->credential_version || $audit->outcome!=='started') {
                    throw new ConflictHttpException('account_changed');
                }
                if (Hash::check($password,$user->password)) { throw new ConflictHttpException('password_reused'); }
                User::query()->whereKey($user->id)->toBase()->update(['password'=>$hash]);
                $version=$user->fresh()->credential_version;
                DB::table('local_access_recoveries')->where('id',$attempt)->update([
                    'outcome'=>'completed','version_after'=>$version,'finished_at'=>now('UTC'),
                ]);
                return $version;
            });
        } catch (\Throwable $e) {
            $reason=$e instanceof ConflictHttpException ? $e->getMessage() : 'persistence_error';
            try { $this->finishRecovery($attempt,$e instanceof ConflictHttpException?'rejected':'failed',$reason); }
            catch (\Throwable) { /* El registro started queda duradero; no afirmar rollback ni éxito si la conexión es incierta. */ }
            throw $e;
        }
    }

    public function hasRecordedRecovery(User $user): bool
    {
        return $user->credential_version>0 && DB::table('local_access_recoveries')->where('user_id',$user->id)
            ->where('version_after',$user->credential_version)->where('outcome','completed')->exists();
    }

    private function recoveryEnvironment(): void
    {
        if (!app()->runningInConsole() || !app()->environment(['local','testing']) || config('h2.mode')!=='fictitious'
            || config('database.connections.pgsql.host')!=='postgres'
            || !in_array(config('database.connections.pgsql.database'),['emisor_h1','emisor_h2_test'],true)) {
            throw new \RuntimeException('Recuperación solo en consola local ficticia autorizada.');
        }
    }

    private function passwordRules(): array
    {
        return ['required','string','confirmed',Password::min(16)->letters()->mixedCase()->numbers(),
            function ($attribute,$value,$fail) { if (is_string($value) && strlen($value)>72) { $fail('Máximo 72 bytes de contraseña.'); } }];
    }
    private function lock(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))', ['h6-local-accounts']);
    }
}
