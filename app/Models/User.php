<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    public const ROLES = ['ADMIN', 'OPERADOR', 'RECEPTOR'];
    protected $casts = ['is_active' => 'boolean'];

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password'];
}
