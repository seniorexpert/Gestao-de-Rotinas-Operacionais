<div id="modalDetalhesProdutividade" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        
        <button type="button" onclick="fecharModalDetalhesProd()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            ✕
        </button>

        <div class="flex-shrink-0 mb-6">
            <h2 id="modalProdNomeColaborador" class="text-3xl font-bold text-[#1e2d3d] font-serif"></h2>
            <p id="modalProdSubtitulo" class="text-sm text-gray-500 mt-1"></p>
        </div>

        <div class="space-y-4 mb-4">
            <div class="relative">
                <input type="text" id="inputBuscaProd" onkeyup="filtrarTarefasModalProd()" placeholder="Buscar por tarefa, local, morador ou horário..." class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3 pl-4 pr-10 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                <span class="absolute right-4 top-3.5 text-gray-400">🔍</span>
            </div>
        </div>

        <div class="space-y-4 sans-text overflow-y-auto flex-1 pr-1" id="modalProdListaTarefas">
            @foreach ($execucoes as $execucao)
                <div class="item-tarefa-prod flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4" data-usuario="{{ $execucao->id_usuario_responsavel }}">
                    <div>
                        <h3 class="font-semibold text-slate-800">{{ $execucao->tarefa ?? 'Rotina' }}</h3>
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
                        @if ($execucao->status === 'pendente' && $execucao->rotina_ativa)
                            <button type="button" onclick="atribuirTarefaIndividual(@js((string) $execucao->id_rotina), @js((string) $execucao->rotina_responsavel))" class="text-xs font-semibold text-slate-700 hover:underline">
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
            <button type="button" onclick="abrirAtribuirComColaborador()" class="w-1/2 py-3.5 rounded-2xl bg-[#1e293b] text-white hover:bg-slate-700 text-sm font-bold transition text-center">
                ⇄ Atribuir tarefa
            </button>
        </div>

    </div>
</div>

<script>
    let colaboradorAtualId = null;
    function abrirModalDetalhesProd(idColaborador, nomeColaborador, totalTarefas, unidades) {
        colaboradorAtualId = idColaborador;

        document.getElementById('modalProdNomeColaborador').textContent = nomeColaborador;
        document.getElementById('modalProdSubtitulo').textContent = [unidades, totalTarefas + ' tarefas no dia'].filter(Boolean).join(' · ');
        document.getElementById('inputBuscaProd').value = '';

        const itens = document.querySelectorAll('.item-tarefa-prod');
        itens.forEach(item => {
            if (item.getAttribute('data-usuario') === String(idColaborador)) {
                item.style.display = "flex";
            } else {
                item.style.display = "none";
            }
        });

        const modal = document.getElementById('modalDetalhesProdutividade');
        if (modal) modal.classList.remove('hidden');
    }

    function fecharModalDetalhesProd() {
        const modal = document.getElementById('modalDetalhesProdutividade');
        if (modal) modal.classList.add('hidden');
    }

    function abrirAtribuirComColaborador() {
        fecharModalDetalhesProd();
        abrirModalAtribuir();

        const selectColab = document.getElementById('filtroColaboradorAtual');
        if (selectColab) {
            selectColab.value = colaboradorAtualId;
            filtrarTarefasPorColaborador();
        }
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

    function atribuirTarefaIndividual(idRotina, idUsuario) {
        fecharModalDetalhesProd();
        
        abrirModalAtribuir();

        const selectColab = document.getElementById('filtroColaboradorAtual');
        if (selectColab) {
            selectColab.value = idUsuario;
            filtrarTarefasPorColaborador();
        }

        setTimeout(() => {
            const checkboxes = document.querySelectorAll('#listaTarefasModal input[type="checkbox"]');
            checkboxes.forEach(cb => {
                if (cb.value === String(idRotina)) {
                    cb.checked = true;
                } else {
                    cb.checked = false; 
                }
            });
        }, 100); 
    }
</script>
