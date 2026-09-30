<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'id_rol',
        'nombre_completo',
        'username',
        'correo',
        'telefono',
        'password_hash',
        'estado',
    ];

    protected $hidden = [
        'password_hash',
    ];

    // ─── Autenticación personalizada ───
    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password_hash'] = $value;
    }

    // ─── Anulación de Remember Token (la tabla usuarios carece de remember_token) ───
    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
        // Mutator vacío para evitar caídas si Laravel intenta asignar remember_token
    }

    public function getRememberTokenName()
    {
        return '';
    }

    // ─── Relaciones ───
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function bitacoras()
    {
        return $this->hasMany(BitacoraAuditoria::class, 'id_usuario', 'id_usuario');
    }
}

