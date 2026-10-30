<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;



#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'gr_usuarios';

    // #[Fillable([
    //     'id_grupo_acesso',
    //     'usuario',
    //     'username',
    //     'grupo_acesso_nome',
    //     'password',
    //     'status',
    // ])]

    protected $fillable = [
        'name',
        'username',
        'password',
        'group',
        'profile',
        'unit',
        'photo',
    ];

    // protected $primaryKey = 'id_usuario';

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

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }
}

