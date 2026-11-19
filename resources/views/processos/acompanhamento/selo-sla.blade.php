<span @class([
    'rounded-full px-2.5 py-1 text-xs font-bold',
    'bg-emerald-50 text-emerald-800' => $situacao === 'No prazo',
    'bg-amber-50 text-amber-900' => $situacao === 'Expirando',
    'bg-rose-100 text-rose-800' => $situacao === 'Atrasada',
])>
    Acompanhamento · {{ $situacao }}
</span>
