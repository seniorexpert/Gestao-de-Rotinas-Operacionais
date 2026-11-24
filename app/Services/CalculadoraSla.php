<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class CalculadoraSla
{
    public const LIMITE_EMERGENCIAL_MINUTOS = 30;

    private const ALERTA_EMERGENCIAL_MINUTOS = 15;

    public const ROTULOS = [
        'noPrazo' => 'No prazo',
        'expirando' => 'Expirando',
        'atrasado' => 'Atrasada',
    ];

    public function segundosDaTarefa(?string $tempo): int
    {
        if ($tempo === null || ! preg_match('/^(\d{2}):([0-5]\d):([0-5]\d)(?:\.\d+)?$/', $tempo, $partes)) {
            return 0;
        }

        return (int) $partes[1] * 3600 + (int) $partes[2] * 60 + (int) $partes[3];
    }

    public function pendente(
        DateTimeInterface|string $previstoEm,
        DateTimeInterface|string|null $referencia = null
    ): string {
        $previsto = $this->data($previstoEm);

        $momento = $referencia === null
            ? CarbonImmutable::now()
            : $this->data($referencia);

        if ($momento->greaterThanOrEqualTo($this->prazoInicio($previsto))) {
            return 'atrasado';
        }

        if ($momento->greaterThanOrEqualTo($previsto->subMinutes(5))) {
            return 'expirando';
        }

        return 'noPrazo';
    }

    public function duranteExecucao(
        DateTimeInterface|string $iniciadoEm,
        int $duracaoSegundos,
        DateTimeInterface|string|null $referencia = null
    ): string {
        if ($duracaoSegundos <= 0) {
            throw new InvalidArgumentException(
                'A duração prevista deve ser maior que zero.'
            );
        }

        $inicio = $this->data($iniciadoEm);

        $momento = $referencia === null
            ? CarbonImmutable::now()
            : $this->data($referencia);

        if ($momento->lessThan($inicio)) {
            throw new InvalidArgumentException(
                'O momento avaliado não pode ser anterior ao início.'
            );
        }

        $limite = $inicio->addSeconds($duracaoSegundos);

        // Até 5 minutos: alerta nos últimos 50% da duração.
        // Acima de 5 minutos: alerta nos últimos 5 minutos.
        $janelaAlerta = $duracaoSegundos <= 300
            ? $duracaoSegundos / 2
            : 300;

        $inicioAlerta = $limite->subMilliseconds(
            (int) round($janelaAlerta * 1000)
        );

        if ($momento->greaterThanOrEqualTo($limite)) {
            return 'atrasado';
        }

        if ($momento->greaterThanOrEqualTo($inicioAlerta)) {
            return 'expirando';
        }

        return 'noPrazo';
    }

    /** @return array{sla_atual: string, sla_inicio: ?string, sla_conclusao: ?string, prazo_em: CarbonImmutable} */
    public function calcular(
        object $execucao,
        DateTimeInterface|string|null $referencia = null
    ): array {
        $momento = $referencia === null
            ? CarbonImmutable::now()
            : $this->data($referencia);

        if (! in_array(
            $execucao->status,
            ['pendente', 'em_andamento', 'concluida'],
            true
        )) {
            throw new InvalidArgumentException(
                'Situação não incluída no cálculo de SLA.'
            );
        }

        if (empty($execucao->previsto_em)) {
            throw new InvalidArgumentException(
                'A execução não possui horário previsto.'
            );
        }

        if (($execucao->tipo_rotina ?? null) === ExecucoesDiarias::TIPO_EMERGENCIAL) {
            return $this->calcularEmergencial($execucao, $momento);
        }

        if ($execucao->status === 'pendente') {
            if (
                ! empty($execucao->iniciado_em)
                || ! empty($execucao->concluido_em)
            ) {
                throw new InvalidArgumentException(
                    'Execução pendente possui início ou conclusão preenchidos.'
                );
            }

            return [
                'sla_atual' => $this->pendente(
                    $execucao->previsto_em,
                    $momento
                ),
                'sla_inicio' => null,
                'sla_conclusao' => null,
                'prazo_em' => $this->prazoInicio($execucao->previsto_em),
            ];
        }

        if (empty($execucao->iniciado_em)) {
            throw new InvalidArgumentException(
                'A execução não possui data de início.'
            );
        }

        $duracao = $this->duracaoPrevista($execucao);

        if ($duracao <= 0) {
            throw new InvalidArgumentException(
                'A execução não possui duração prevista válida.'
            );
        }

        // Preserva a classificação do momento em que começou.
        $slaInicio = $this->pendente(
            $execucao->previsto_em,
            $execucao->iniciado_em
        );
        $prazoConclusao = $this->data($execucao->iniciado_em)->addSeconds($duracao);

        if ($execucao->status === 'concluida') {
            if (empty($execucao->concluido_em)) {
                throw new InvalidArgumentException(
                    'A execução concluída não possui data de conclusão.'
                );
            }

            // Usa a conclusão, e não o horário atual.
            $slaConclusao = $this->duranteExecucao(
                $execucao->iniciado_em,
                $duracao,
                $execucao->concluido_em
            );

            return [
                'sla_atual' => $slaConclusao,
                'sla_inicio' => $slaInicio,
                'sla_conclusao' => $slaConclusao,
                'prazo_em' => $prazoConclusao,
            ];
        }

        if (! empty($execucao->concluido_em)) {
            throw new InvalidArgumentException(
                'Execução em andamento possui conclusão preenchida.'
            );
        }

        return [
            'sla_atual' => $this->duranteExecucao(
                $execucao->iniciado_em,
                $duracao,
                $momento
            ),
            'sla_inicio' => $slaInicio,
            'sla_conclusao' => null,
            'prazo_em' => $prazoConclusao,
        ];
    }

    /** @return array{vencido_em: CarbonImmutable, segundos: int}|null */
    public function atraso(object $execucao, DateTimeInterface $agora): ?array
    {
        $sla = $this->calcular($execucao, $agora);
        if (($execucao->tipo_rotina ?? null) === ExecucoesDiarias::TIPO_EMERGENCIAL) {
            return $sla['sla_atual'] === 'atrasado'
                ? [
                    'vencido_em' => $sla['prazo_em'],
                    'segundos' => (int) $sla['prazo_em']->diffInSeconds($this->data($execucao->concluido_em ?? $agora)),
                ]
                : null;
        }

        $prazoInicio = $this->prazoInicio($execucao->previsto_em);

        if ($execucao->status === 'pendente') {
            return $sla['sla_atual'] === 'atrasado'
                ? ['vencido_em' => $prazoInicio, 'segundos' => (int) $prazoInicio->diffInSeconds($agora)]
                : null;
        }

        $inicio = $this->data($execucao->iniciado_em);
        $iniciouComAtraso = $sla['sla_inicio'] === 'atrasado';

        if ($sla['sla_atual'] === 'atrasado') {
            $prazo = $sla['prazo_em'];
            $referencia = $execucao->concluido_em ? $this->data($execucao->concluido_em) : $agora;

            return [
                'vencido_em' => $iniciouComAtraso ? $prazoInicio : $prazo,
                'segundos' => (int) $prazo->diffInSeconds($referencia),
            ];
        }

        return $iniciouComAtraso
            ? ['vencido_em' => $prazoInicio, 'segundos' => (int) $prazoInicio->diffInSeconds($inicio)]
            : null;
    }

    public function prazoInicio(DateTimeInterface|string $previsto): CarbonImmutable
    {
        return $this->data($previsto)->addMinute();
    }

    public function prazoEmergencial(DateTimeInterface|string $entrada): CarbonImmutable
    {
        return $this->data($entrada)->addMinutes(self::LIMITE_EMERGENCIAL_MINUTOS);
    }

    /** @return array{sla_atual: string, sla_inicio: ?string, sla_conclusao: ?string, prazo_em: CarbonImmutable} */
    private function calcularEmergencial(object $execucao, CarbonImmutable $momento): array
    {
        $previsto = $this->data($execucao->previsto_em);
        $entrada = max($previsto, $this->data($execucao->created_at ?? $previsto));
        $prazo = $this->prazoEmergencial($entrada);
        $alerta = $entrada->addMinutes(self::ALERTA_EMERGENCIAL_MINUTOS);
        $inicio = ! empty($execucao->iniciado_em) ? $this->data($execucao->iniciado_em) : null;
        $conclusao = ! empty($execucao->concluido_em) ? $this->data($execucao->concluido_em) : null;

        if (
            ($execucao->status === 'pendente' && ($inicio || $conclusao))
            || ($execucao->status !== 'pendente' && ! $inicio)
            || ($execucao->status === 'concluida' && ! $conclusao)
            || ($execucao->status === 'em_andamento' && $conclusao)
        ) {
            throw new InvalidArgumentException('A execução emergencial possui datas inconsistentes.');
        }

        $situacao = fn (CarbonImmutable $referencia): string => match (true) {
            $referencia >= $prazo => 'atrasado',
            $referencia >= $alerta => 'expirando',
            default => 'noPrazo',
        };

        return [
            'sla_atual' => $situacao($conclusao ?? $momento),
            'sla_inicio' => $inicio ? $situacao($inicio) : null,
            'sla_conclusao' => $conclusao ? $situacao($conclusao) : null,
            'prazo_em' => $prazo,
        ];
    }

    private function duracaoPrevista(object $execucao): int
    {
        $duracao = (int) ($execucao->duracao_prevista_segundos ?? 0);
        if ($duracao > 0) {
            return $duracao;
        }

        if (! empty($execucao->prazo_em)) {
            $inicio = $this->data($execucao->iniciado_em);
            $duracao = (int) $inicio->diffInSeconds($this->data($execucao->prazo_em));
        }

        return $duracao > 0 ? $duracao : (int) ($execucao->tempo_limite_minutos ?? 0) * 60;
    }

    private function data(
        DateTimeInterface|string $valor
    ): CarbonImmutable {
        return $valor instanceof DateTimeInterface
            ? CarbonImmutable::instance($valor)
            : CarbonImmutable::parse($valor, 'UTC');
    }
}
