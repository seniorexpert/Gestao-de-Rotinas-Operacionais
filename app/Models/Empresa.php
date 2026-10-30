<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $primaryKey = 'id_empresa';

    protected $fillable = ['empresa', 'status'];

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'empresa_usuario',
            'id_empresa',
            'id_usuario',
            'id_empresa',
            'id_usuario'
        )->withPivot('empresa_nome')->withTimestamps();
    }
}