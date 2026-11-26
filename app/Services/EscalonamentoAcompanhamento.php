<?php

namespace App\Services;

use App\Models\GrupoAcesso;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EscalonamentoAcompanhamento
{
    public const ENFERMAGEM = 'Enfermagem';

    public const COORDENADOR = 'Coordenador';

    public const GERENTE_DA_UNIDADE = 'Gerente da Unidade';

    public const GERENTE_GERAL = 'Gerente Geral';

    private const ALERTA_EM_MINUTOS = 15;

    private const LIMITE_EM_MINUTOS = 30;

    private const ALERTA_AUXILIO_EM_MINUTOS = 5;

    private const LIMITE_AUXILIO_EM_MINUTOS = 10;

    private const PROXIMA_CAIXA = [
        self::ENFERMAGEM => self::GERENTE_DA_UNIDADE,
        self::COORDENADOR => self::GERENTE_DA_UNIDADE,
        self::GERENTE_DA_UNIDADE => self::GERENTE_GERAL,
    ];

    private const CAIXAS_VERIFICACOES_LEGADAS = [self::GERENTE_DA_UNIDADE, self::GERENTE_GERAL];

    public function __construct(private CalculadoraSla $calculadoraSla) {}

    public function caixaDoUsuario(User $usuario): ?string
    {
        return $usuario->status ? $this->caixaDoGrupo($usuario->grupoAcesso) : null;
    }

    public function caixaDoGrupo(?GrupoAcesso $grupo): ?string
    {
        if (! $grupo?->status) {
            return null;
        }

        return match ($this->normalizarGrupo($grupo->grupo_acesso)) {
            'enfermagem' => self::ENFERMAGEM,
            'coordenador', 'coordenacao' => self::COORDENADOR,
            'gerente', 'gerente da unidade', 'gerente de unidade', 'gerente unidade' => self::GERENTE_DA_UNIDADE,
            'gerente geral' => self::GERENTE_GERAL,
            default => null,
        };
    }

    public function superiorDoGrupo(?string $grupo): ?string
    {
        return match ($this->normalizarGrupo($grupo)) {
            'cuidador', 'cuidadores', 'farmacia' => self::ENFERMAGEM,
            'estoque', 'manutencao', 'auxiliar de limpeza', 'auxiliar de cozinha' => self::COORDENADOR,
            'coordenacao', 'coordenador', 'enfermagem' => self::GERENTE_DA_UNIDADE,
            'gerente', 'gerente da unidade', 'gerente de unidade', 'gerente unidade' => self::GERENTE_GERAL,
            default => null,
        };
    }

    /** @return array{atraso_segundos: int, recebimentos: array<string, CarbonImmutable>}|null */
    public function encaminhar(object $execucao, CarbonImmutable $agora): ?array
    {
        $superior = $this->superiorDoGrupo($execucao->grupo_origem);
        if ($superior === null) {
            return null;
        }

        try {
            $atraso = $this->calculadoraSla->atraso($execucao, $agora);
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($atraso === null) {
            return null;
        }

        $destinos = in_array($this->normalizarGrupo($execucao->grupo_origem), ['coordenacao', 'coordenador'], true)
            ? [self::COORDENADOR, $superior]
            : [$superior];

        return [
            'atraso_segundos' => $atraso['segundos'],
            'recebimentos' => $this->recebimentos($destinos, $atraso['vencido_em'], $agora),
        ];
    }

    /** @return array{atraso_segundos: ?int, recebimentos: array<string, CarbonImmutable>}|null */
    public function encaminharItem(object $item, CarbonImmutable $agora): ?array
    {
        if ($item->tipo === 'atraso') {
            return $this->encaminhar($item, $agora);
        }

        return [
            'atraso_segundos' => null,
            'recebimentos' => $this->recebimentos(
                [$item->caixa_inicial], CarbonImmutable::parse($item->entrada, 'UTC'), $agora, $item->tipo === 'auxilio',
            ),
        ];
    }

    public function podeConsultarVerificacao(object $verificacao, ?string $caixa, User $usuario): bool
    {
        if ($caixa === null || (string) $verificacao->id_usuario_verificacao === (string) $usuario->getKey()) {
            return true;
        }

        if ($verificacao->encaminhamentos === null) {
            return in_array($caixa, self::CAIXAS_VERIFICACOES_LEGADAS, true);
        }

        $recebimentos = json_decode($verificacao->encaminhamentos, true);

        return isset($recebimentos[$caixa]);
    }

    public function filtrarVerificacoes(Builder $consulta, User $usuario): Builder
    {
        $caixa = $usuario->isAdmin() ? null : $this->caixaDoUsuario($usuario);
        if ($caixa === null) {
            return $consulta;
        }

        return $consulta->where(fn (Builder $visiveis) => $visiveis
            ->where('v.id_usuario_verificacao', $usuario->getKey())
            ->orWhereNotNull('v.encaminhamentos->'.$caixa)
            ->when(in_array($caixa, self::CAIXAS_VERIFICACOES_LEGADAS, true), fn (Builder $legadas) => $legadas->orWhereNull('v.encaminhamentos')));
    }

    /**
     * @param  list<string>  $destinos
     * @return array<string, CarbonImmutable>
     */
    public function recebimentos(array $destinos, CarbonImmutable $entrada, CarbonImmutable $agora, bool $auxilio = false): array
    {
        $recebimentos = [];

        foreach ($destinos as $caixa) {
            $recebidoEm = $entrada;
            while ($caixa !== null && $recebidoEm->lessThanOrEqualTo($agora)) {
                if (! isset($recebimentos[$caixa]) || $recebidoEm->lessThan($recebimentos[$caixa])) {
                    $recebimentos[$caixa] = $recebidoEm;
                }

                $caixa = self::PROXIMA_CAIXA[$caixa] ?? null;
                $recebidoEm = $this->limite($recebidoEm, $auxilio);
            }
        }

        return $recebimentos;
    }

    public function limite(CarbonImmutable $recebidoEm, bool $auxilio = false): CarbonImmutable
    {
        return $recebidoEm->addMinutes($auxilio ? self::LIMITE_AUXILIO_EM_MINUTOS : self::LIMITE_EM_MINUTOS);
    }

    public function situacao(CarbonImmutable $recebidoEm, CarbonImmutable $agora, bool $auxilio = false): string
    {
        return CalculadoraSla::ROTULOS[$this->classificarSla($recebidoEm, $agora, $auxilio)];
    }

    /** @return 'atrasado'|'expirando'|'noPrazo' */
    public function classificarSla(CarbonImmutable $recebidoEm, CarbonImmutable $referencia, bool $auxilio = false): string
    {
        if ($referencia->greaterThanOrEqualTo($this->limite($recebidoEm, $auxilio))) {
            return 'atrasado';
        }

        $alerta = $auxilio ? self::ALERTA_AUXILIO_EM_MINUTOS : self::ALERTA_EM_MINUTOS;
        if ($referencia->greaterThanOrEqualTo($recebidoEm->addMinutes($alerta))) {
            return 'expirando';
        }

        return 'noPrazo';
    }

    private function normalizarGrupo(?string $nome): string
    {
        return Str::lower(Str::ascii(Str::squish($nome ?? '')));
    }
}
