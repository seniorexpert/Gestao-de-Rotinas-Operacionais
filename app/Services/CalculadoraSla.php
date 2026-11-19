<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class CalculadoraSla
{
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
            ];
        }

        if (empty($execucao->iniciado_em)) {
            throw new InvalidArgumentException(
                'A execução não possui data de início.'
            );
        }

        $duracao = (int) ($execucao->duracao_prevista_segundos ?? 0);

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
        ];
    }

    /** @return array{vencido_em: CarbonImmutable, segundos: int}|null */
    public function atraso(object $execucao, DateTimeInterface $agora): ?array
    {
        if ($execucao->iniciado_em && (int) ($execucao->duracao_prevista_segundos ?? 0) <= 0) {
            // Execuções antigas podem ter apenas o prazo registrado ou o SLA em minutos.
            $execucao = clone $execucao;
            $inicio = $this->data($execucao->iniciado_em);
            $duracao = (int) $inicio->diffInSeconds($this->data($execucao->prazo_em));
            $execucao->duracao_prevista_segundos = $duracao > 0
                ? $duracao
                : (int) $execucao->tempo_limite_minutos * 60;
        }

        $sla = $this->calcular($execucao, $agora);
        $prazoInicio = $this->prazoInicio($this->data($execucao->previsto_em));

        if ($execucao->status === 'pendente') {
            return $sla['sla_atual'] === 'atrasado'
                ? ['vencido_em' => $prazoInicio, 'segundos' => (int) $prazoInicio->diffInSeconds($agora)]
                : null;
        }

        $inicio = $this->data($execucao->iniciado_em);
        $iniciouComAtraso = $sla['sla_inicio'] === 'atrasado';

        if ($sla['sla_atual'] === 'atrasado') {
            $prazo = $inicio->addSeconds((int) $execucao->duracao_prevista_segundos);
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

    private function prazoInicio(CarbonImmutable $previsto): CarbonImmutable
    {
        return $previsto->addMinute();
    }

    private function data(
        DateTimeInterface|string $valor
    ): CarbonImmutable {
        return $valor instanceof DateTimeInterface
            ? CarbonImmutable::instance($valor)
            : CarbonImmutable::parse($valor);
    }
}
