<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsultaRotinas
{
    public function empresas(User $usuario): Builder
    {
        return DB::table('empresa_usuario')
            ->select('id_empresa')
            ->where('id_usuario', $usuario->getKey());
    }

    public function colaboradores(User $usuario): Builder
    {
        return DB::table('usuarios as u')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->whereIn('u.id_usuario', DB::table('empresa_usuario')
                ->select('id_usuario')
                ->whereIn('id_empresa', $this->empresas($usuario)))
            ->select('u.id_usuario', 'u.usuario', 'g.grupo_acesso')
            ->orderBy('u.usuario');
    }

    public function rotinas(User $usuario): Builder
    {
        return DB::table('rotinas as r')
            ->join('usuarios as u', 'r.id_usuario', '=', 'u.id_usuario')
            ->join('empresas as e', 'r.id_empresa', '=', 'e.id_empresa')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('locais as l', 'r.id_local', '=', 'l.id_local')
            ->whereIn('r.id_empresa', $this->empresas($usuario));
    }

    /** @return array{empresas: Collection, tarefas: Collection, locais: Collection} */
    public function catalogos(User $usuario): array
    {
        return [
            'empresas' => DB::table('empresas')->whereIn('id_empresa', $this->empresas($usuario))
                ->orderBy('empresa')->get(['id_empresa', 'empresa']),
            'tarefas' => DB::table('tarefas')->orderBy('tarefa')->get(['id_tarefa', 'tarefa']),
            'locais' => DB::table('locais')->orderBy('local')->get(['id_local', 'local']),
        ];
    }
}
