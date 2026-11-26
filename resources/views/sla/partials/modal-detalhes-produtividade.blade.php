<div id="modalDetalhesProdutividade" role="dialog" aria-modal="true" aria-labelledby="modalProdNomeColaborador" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white text-[#17384b] rounded-3xl max-w-2xl w-full p-5 sm:p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        
        <button type="button" onclick="fecharModalDetalhesProd()" aria-label="Fechar detalhes" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700">
            ✕
        </button>

        <div class="flex-shrink-0 mb-6">
            <h2 id="modalProdNomeColaborador" class="pr-7 font-['Fraunces',Georgia,serif] text-2xl font-semibold break-words sm:text-3xl"></h2>
            <p id="modalProdSubtitulo" class="text-sm text-gray-500 mt-1"></p>
        </div>

        <div class="space-y-4 mb-4">
            <div class="relative">
                <input type="search" id="inputBuscaProd" oninput="filtrarTarefasModalProd()" aria-label="Buscar tarefas do colaborador" placeholder="Buscar por tarefa, local, morador ou horário..." class="w-full rounded-xl border border-[#e8d6bf] bg-white px-4 py-3 pr-10 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute right-4 top-3.5 text-[#8b6b43]" aria-hidden="true"></i>
            </div>
        </div>

        <div class="space-y-4 sans-text overflow-y-auto flex-1 pr-1" id="modalProdListaTarefas">
            @foreach ($execucoes as $execucao)
                <div class="item-tarefa-prod flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-[#e8d6bf] bg-white p-3.5"
                     data-usuario="{{ $execucao->id_usuario_responsavel }}"
                     @if ($execucao->pode_atribuir) data-processo-atribuivel="{{ $execucao->processo }}" @endif>
                    <div class="min-w-0">
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <x-icone-tarefa :icone="$execucao->icone" :cor="$execucao->cor_icone" class="h-5 w-5 text-lg" />
                            <span class="min-w-0 break-words">{{ $execucao->tarefa ?? 'Rotina' }}</span>
                        </h3>
                        @if ($execucao->processo !== \App\Services\ExecucoesDiarias::TIPO_DIARIA)
                            <p class="mt-1 text-xs font-semibold text-[#8b6b43]">{{ $nomesProcessos[$execucao->processo] }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-slate-600">
                            {{ $execucao->horario }} · {{ $execucao->local_rotina ?: ($execucao->local_cadastrado ?? 'Local não informado') }} · {{ $execucao->morador ?? 'Tarefa geral' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-600">
                            {{ ['pendente' => 'Pendente', 'em_andamento' => 'Em andamento', 'concluida' => 'Concluída', 'nao_realizada' => 'Não realizada'][$execucao->status] }}
                            @if ($execucao->limite)
                                · Limite em {{ $execucao->limite }}
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($execucao->status !== 'nao_realizada')
                            <span data-sla-execucao="{{ $execucao->situacao_sla }}" @class([
                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-emerald-50 text-emerald-900' => $execucao->situacao_sla === 'noPrazo',
                            'bg-amber-50 text-amber-900' => $execucao->situacao_sla === 'expirando',
                            'bg-rose-100 text-rose-900' => $execucao->situacao_sla === 'atrasado',
                            'bg-slate-100 text-slate-700' => $execucao->situacao_sla === 'semDados',
                            ])>{{ \App\Services\CalculadoraSla::ROTULOS[$execucao->situacao_sla] ?? 'Sem dados de SLA' }}</span>
                        @endif
                        @if ($execucao->pode_atribuir && auth()->user()->can('editar-modulo', 'monitoramento'))
                            <button type="button" onclick="atribuirTarefaIndividual(@js($execucao->processo), @js((string) $execucao->id_execucao), @js((string) $execucao->id_usuario_responsavel))" class="text-xs font-semibold text-slate-700 hover:underline">
                                Atribuir
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-6 border-t border-gray-100 mt-4 flex items-center justify-between gap-4">
            <button type="button" onclick="fecharModalDetalhesProd()" class="w-1/2 py-3.5 rounded-2xl border border-gray-300 text-slate-800 hover:bg-gray-50 text-sm font-bold transition text-center">
                Fechar
            </button>
            @can('editar-modulo', 'monitoramento')
            <button type="button" id="atribuirComColaborador" onclick="abrirAtribuirComColaborador()" hidden class="w-1/2 py-3.5 rounded-2xl bg-[#1e293b] text-white hover:bg-slate-700 text-sm font-bold transition text-center">
                ⇄ Atribuir tarefa
            </button>
            @endcan
        </div>

    </div>
</div>

<script>
    let colaboradorAtualId = null;
    function abrirModalDetalhesProd(idColaborador, nomeColaborador, totalTarefas, unidades) {
        colaboradorAtualId = idColaborador;

        document.getElementById('modalProdNomeColaborador').textContent = nomeColaborador;
        const total = totalTarefas + (totalTarefas === 1 ? ' tarefa' : ' tarefas');
        document.getElementById('modalProdSubtitulo').textContent = [unidades, total].filter(Boolean).join(' · ');
        document.getElementById('inputBuscaProd').value = '';

        const itens = document.querySelectorAll('.item-tarefa-prod');
        const processos = new Set();
        itens.forEach(item => {
            if (item.getAttribute('data-usuario') === String(idColaborador)) {
                item.style.display = "flex";
                if (item.dataset.processoAtribuivel) processos.add(item.dataset.processoAtribuivel);
            } else {
                item.style.display = "none";
            }
        });
        const atribuir = document.getElementById('atribuirComColaborador');
        if (atribuir) {
            atribuir.hidden = processos.size !== 1;
            atribuir.dataset.processo = processos.size === 1 ? [...processos][0] : '';
        }

        const modal = document.getElementById('modalDetalhesProdutividade');
        if (modal) modal.classList.remove('hidden');
    }

    function fecharModalDetalhesProd() {
        const modal = document.getElementById('modalDetalhesProdutividade');
        if (modal) modal.classList.add('hidden');
    }

    function abrirAtribuirComColaborador() {
        const processo = document.getElementById('atribuirComColaborador').dataset.processo;
        if (!processo) return;
        fecharModalDetalhesProd();
        abrirModalAtribuir(processo, colaboradorAtualId);
    }

    function filtrarTarefasModalProd() {
        const termo = document.getElementById('inputBuscaProd').value.toLowerCase();
        const itens = document.querySelectorAll('.item-tarefa-prod');

        itens.forEach(item => {
            const texto = item.textContent.toLowerCase();
            const usuarioMatch = item.getAttribute('data-usuario') === String(colaboradorAtualId);
            if (usuarioMatch && texto.includes(termo)) {
                item.style.display = "flex";
            } else {
                item.style.display = "none";
            }
        });
    }

    function atribuirTarefaIndividual(processo, idExecucao, idUsuario) {
        fecharModalDetalhesProd();
        abrirModalAtribuir(processo, idUsuario, idExecucao);
    }
</script>
