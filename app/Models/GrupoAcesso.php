<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoAcesso extends Model
{
    protected $table = 'grupos_acesso';

    protected $primaryKey = 'id_grupo_acesso';

    protected $fillable = ['grupo_acesso', 'status'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(
            User::class,
            'id_grupo_acesso',
            'id_grupo_acesso'
        );
    }
}