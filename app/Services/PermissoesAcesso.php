<?php

namespace App\Services;

use App\Models\GrupoAcesso;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PermissoesAcesso
{
    public const SEM_ACESSO = 0;

    public const VISUALIZAR = 1;

    public const EDITAR = 2;

    public const NIVEIS = [
        self::SEM_ACESSO => 'Sem acesso',
        self::VISUALIZAR => 'Visualizar',
        self::EDITAR => 'Editar',
    ];

    /** @var array<string, array{rotulo: string, rota: string, editavel: bool}> */
    public const MODULOS = [
        'rotinas_diarias' => ['rotulo' => 'Rotinas Diárias', 'rota' => 'home', 'editavel' => true],
        'acompanhamento' => ['rotulo' => 'Rotina de Acompanhamento', 'rota' => 'acompanhamento.index', 'editavel' => true],
        'monitoramento' => ['rotulo' => 'Centro de Monitoramento', 'rota' => 'monitoramento.index', 'editavel' => true],
        'processos' => ['rotulo' => 'Iniciar Processos', 'rota' => 'processos.index', 'editavel' => true],
        'rotinas' => ['rotulo' => 'Cadastro e Relatório de Rotinas', 'rota' => 'relatorios.rotinas', 'editavel' => true],
        'tarefas' => ['rotulo' => 'Cadastro e Relatório de Tarefas', 'rota' => 'relatorios.tarefas', 'editavel' => true],
        'ferramentas' => ['rotulo' => 'Cadastro e Relatório de Ferramentas', 'rota' => 'relatorios.ferramentas', 'editavel' => true],
        'locais' => ['rotulo' => 'Cadastro e Relatório de Locais', 'rota' => 'relatorios.locais', 'editavel' => true],
        'estatisticas' => ['rotulo' => 'Estatísticas', 'rota' => 'relatorios.estatisticas', 'editavel' => false],
        'rotinas_finalizadas' => ['rotulo' => 'Relatório de Rotinas Finalizadas', 'rota' => 'relatorios.rotinas-finalizadas', 'editavel' => false],
        'rotinas_excel' => ['rotulo' => 'Relatório de Rotinas Excel', 'rota' => 'relatorios.excel', 'editavel' => false],
        'auditoria' => ['rotulo' => 'Logs e Auditoria', 'rota' => 'relatorios.logs-rotinas', 'editavel' => false],
        'usuarios' => ['rotulo' => 'Gestão de Usuários', 'rota' => 'usuarios.index', 'editavel' => true],
    ];

    public const ORIGENS = [
        'grupo' => ['permissoes_grupos_acesso', 'id_grupo_acesso'],
        'usuario' => ['permissoes_usuarios', 'id_usuario'],
    ];

    /** @var array<string, array<int, array<string, int>>> */
    private array $regras = [];

    public function __construct(private EscalonamentoAcompanhamento $acompanhamento) {}

    public function nivel(User $usuario, string $modulo): int
    {
        if (! $usuario->status || ! isset(self::MODULOS[$modulo])) {
            return self::SEM_ACESSO;
        }

        if ($usuario->isAdmin()) {
            return self::MODULOS[$modulo]['editavel'] ? self::EDITAR : self::VISUALIZAR;
        }

        $individuais = $usuario->exists ? $this->configuradas('usuario', (int) $usuario->getKey()) : [];

        return $individuais[$modulo] ?? $this->nivelGrupo($usuario->grupoAcesso, $modulo);
    }

    public function nivelGrupo(?GrupoAcesso $grupo, string $modulo): int
    {
        $configuradas = $grupo?->status ? $this->configuradas('grupo', (int) $grupo->getKey()) : [];

        return $configuradas[$modulo] ?? $this->nivelPadrao($grupo, $modulo);
    }

    public function nivelPadrao(?GrupoAcesso $grupo, string $modulo): int
    {
        return match ($modulo) {
            'acompanhamento' => $this->acompanhamento->caixaDoGrupo($grupo) ? self::EDITAR : self::SEM_ACESSO,
            'usuarios' => $this->acompanhamento->caixaDoGrupo($grupo) === EscalonamentoAcompanhamento::GERENTE_GERAL
                ? self::EDITAR : self::SEM_ACESSO,
            default => (self::MODULOS[$modulo]['editavel'] ?? false) ? self::EDITAR : self::VISUALIZAR,
        };
    }

    /** @return array<string, int> */
    public function configuradas(string $tipo, int $id): array
    {
        [$tabela, $chave] = self::ORIGENS[$tipo];

        return $this->regras[$tipo][$id] ??= DB::table($tabela)
            ->where($chave, $id)->orderBy('modulo')->pluck('nivel', 'modulo')
            ->map(fn ($nivel): int => (int) $nivel)->all();
    }

    public function recarregar(string $tipo, int $id): void
    {
        unset($this->regras[$tipo][$id]);
    }

    public function gerenciaGeral(User $usuario): bool
    {
        return $usuario->isAdmin() || $this->acompanhamento->caixaDoUsuario($usuario) === EscalonamentoAcompanhamento::GERENTE_GERAL;
    }
}
