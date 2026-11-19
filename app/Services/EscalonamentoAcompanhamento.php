<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
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

    private const PROXIMA_CAIXA = [
        self::ENFERMAGEM => self::GERENTE_DA_UNIDADE,
        self::COORDENADOR => self::GERENTE_DA_UNIDADE,
        self::GERENTE_DA_UNIDADE => self::GERENTE_GERAL,
    ];

    public function __construct(private CalculadoraSla $calculadoraSla) {}

    public function caixaDoUsuario(User $usuario): ?string
    {
        $grupo = $usuario->grupoAcesso;

        if (! $usuario->status || ! $grupo?->status) {
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

    /** @return array{atraso_segundos: int, recebimentos: array<string, CarbonImmutable>}|null */
    public function encaminhar(object $execucao, CarbonImmutable $agora): ?array
    {
        $destinos = match ($this->normalizarGrupo($execucao->grupo_origem)) {
            'cuidador', 'cuidadores', 'farmacia' => [self::ENFERMAGEM],
            'estoque', 'manutencao', 'auxiliar de limpeza', 'auxiliar de cozinha' => [self::COORDENADOR],
            'coordenacao', 'coordenador' => [self::COORDENADOR, self::GERENTE_DA_UNIDADE],
            'enfermagem' => [self::GERENTE_DA_UNIDADE],
            'gerente', 'gerente da unidade', 'gerente de unidade', 'gerente unidade' => [self::GERENTE_GERAL],
            default => [],
        };

        if ($destinos === []) {
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

        $recebimentos = [];

        foreach ($destinos as $caixa) {
            $recebidoEm = $atraso['vencido_em'];

            while ($caixa !== null && $recebidoEm->lessThanOrEqualTo($agora)) {
                if (! isset($recebimentos[$caixa]) || $recebidoEm->lessThan($recebimentos[$caixa])) {
                    $recebimentos[$caixa] = $recebidoEm;
                }

                $caixa = self::PROXIMA_CAIXA[$caixa] ?? null;
                $recebidoEm = $this->limite($recebidoEm);
            }
        }

        return [
            'atraso_segundos' => $atraso['segundos'],
            'recebimentos' => $recebimentos,
        ];
    }

    public function limite(CarbonImmutable $recebidoEm): CarbonImmutable
    {
        return $recebidoEm->addMinutes(self::LIMITE_EM_MINUTOS);
    }

    public function situacao(CarbonImmutable $recebidoEm, CarbonImmutable $agora): string
    {
        if ($agora->greaterThanOrEqualTo($this->limite($recebidoEm))) {
            return 'Atrasada';
        }

        if ($agora->greaterThanOrEqualTo($recebidoEm->addMinutes(self::ALERTA_EM_MINUTOS))) {
            return 'Expirando';
        }

        return 'No prazo';
    }

    private function normalizarGrupo(?string $nome): string
    {
        return Str::lower(Str::ascii(Str::squish($nome ?? '')));
    }
}
