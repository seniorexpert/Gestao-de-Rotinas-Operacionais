@props(['previsto', 'auxiliares'])

@php($nomes = $previsto ? collect($auxiliares)->implode('; ') : '')

<span>{{ $previsto ? 'Sim' : 'Não' }}{{ $nomes !== '' ? ' - '.$nomes : '' }}</span>
