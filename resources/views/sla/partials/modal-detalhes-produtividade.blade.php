<div id="modalDetalhesProdutividade" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        
        <button type="button" onclick="fecharModalDetalhesProd()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            ✕
        </button>

        <div class="flex-shrink-0 mb-6">
            <h2 id="modalProdNomeColaborador" class="text-3xl font-bold text-[#1e2d3d] font-serif">Nome do Colaborador</h2>
            <p id="modalProdSubtitulo" class="text-sm text-gray-500 mt-1">Gerência · Garden Ville · Tarefas do dia</p>
        </div>

        <div class="space-y-4 mb-4">
            <div class="relative">
                <input type="text" id="inputBuscaProd" onkeyup="filtrarTarefasModalProd()" placeholder="Buscar por tarefa, local, morador ou horário..." class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3 pl-4 pr-10 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                <span class="absolute right-4 top-3.5 text-gray-400">🔍</span>
            </div>
        </div>

        <div class="space-y-4 sans-text overflow-y-auto flex-1 pr-1" id="modalProdListaTarefas">
            @php
                $todasRotinas = DB::table('gr_rotinas')
                    ->join('gr_tarefas', 'gr_rotinas.id_tarefa', '=', 'gr_tarefas.id')
                    ->leftJoin('gr_usuarios', 'gr_rotinas.id_usuario', '=', 'gr_usuarios.id')
                    ->select('gr_rotinas.*', 'gr_tarefas.nome_tarefa', 'gr_usuarios.name as usuario_nome')
                    ->get();
            @endphp

            @foreach($todasRotinas as $rotina)
                <div class="item-tarefa-prod border border-gray-200 rounded-2xl p-4 flex items-center justify-between bg-white shadow-sm" data-usuario="{{ $rotina->id_usuario }}">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center text-2xl">
                            🛁
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-slate-800">{{ $rotina->nome_tarefa ?? 'Rotina' }}</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Onde: {{ $rotina->local }} | Morador: {{ $rotina->morador ?? 'N/A' }}</p>
                            <span class="text-xs font-semibold text-gray-700 mt-1 block">Horário: {{ $rotina->horario }}</span>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="px-2.5 py-1 text-[10px] font-semibold bg-emerald-50 text-emerald-700 rounded-full border border-emerald-100">No prazo</span>
                        <button type="button" onclick="atribuirTarefaIndividual('{{ $rotina->id_rotina }}', '{{ $rotina->id_usuario }}')" class="text-xs font-bold text-slate-700 hover:text-[#1e2d3d] flex items-center gap-1 transition cursor-pointer">
                            ⇄ Atribuir
                        </button>
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
    let colaboradorAtualNome = '';

    function abrirModalDetalhesProd(idColaborador, nomeColaborador, totalTarefas) {
        colaboradorAtualId = idColaborador;
        colaboradorAtualNome = nomeColaborador;

        document.getElementById('modalProdNomeColaborador').textContent = nomeColaborador;
        document.getElementById('modalProdSubtitulo').textContent = `Gerência · Garden Ville · ${totalTarefas} tarefas no dia`;

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