<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password'];
}
