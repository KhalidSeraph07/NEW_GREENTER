<?php
namespace App\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Hash, Validator};
use Illuminate\Validation\Rules\Password;
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
                'role' => 'required|in:ADMIN,OPERADOR',
                'password' => ['required', 'string', 'confirmed', Password::min(16)->letters()->mixedCase()->numbers(),
                    function ($attribute, $value, $fail) { if (is_string($value) && strlen($value) > 72) { $fail('Máximo 72 bytes de contraseña.'); } }],
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
    private function lock(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))', ['h6-local-accounts']);
    }
}
