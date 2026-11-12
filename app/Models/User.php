<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'id_grupo_acesso',
        'usuario',
        'username',
        'grupo_acesso_nome',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(
            Empresa::class,
            'empresa_usuario',
            'id_usuario',
            'id_empresa',
            'id_usuario',
            'id_empresa'
        )->withPivot('empresa_nome')->withTimestamps();
    }

    public function grupoAcesso(): BelongsTo
    {
        return $this->belongsTo(
            GrupoAcesso::class,
            'id_grupo_acesso',
            'id_grupo_acesso'
        );
    }
}