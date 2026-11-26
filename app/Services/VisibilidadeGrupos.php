<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class VisibilidadeGrupos
{
    public const TABELA = 'grupos_acesso_visibilidade';

    /** @var array<int, list<int>> */
    private array $configuracoes = [];

    /** @return list<int> */
    public function configurados(int $grupo): array
    {
        return $this->configuracoes[$grupo] ??= DB::table(self::TABELA)
            ->where('id_grupo_origem', $grupo)->orderBy('id_grupo_visivel')->pluck('id_grupo_visivel')
            ->map(fn ($id): int => (int) $id)->all();
    }

    public function recarregar(int $grupo): void
    {
        unset($this->configuracoes[$grupo]);
    }

    /** @return list<int>|null */
    public function grupos(User $usuario): ?array
    {
        if ($usuario->isAdmin() || $usuario->id_grupo_acesso === null) {
            return null;
        }

        return $this->configurados((int) $usuario->id_grupo_acesso) ?: null;
    }

    public function colaboradores(User $usuario): ?Builder
    {
        $grupos = $this->grupos($usuario);

        return $grupos === null ? null : DB::table('usuarios')->select('id_usuario')->whereIntegerInRaw('id_grupo_acesso', $grupos);
    }

    public function regraDeColaboradorNaUnidade(User $usuario, int $unidade): Exists
    {
        $regra = Rule::exists('empresa_usuario', 'id_usuario')->where('id_empresa', $unidade);
        $visiveis = $this->colaboradores($usuario);
        if ($visiveis !== null) {
            $regra->where(fn (Builder $vinculos) => $vinculos->whereIn('id_usuario', $visiveis));
        }

        return $regra;
    }
}
