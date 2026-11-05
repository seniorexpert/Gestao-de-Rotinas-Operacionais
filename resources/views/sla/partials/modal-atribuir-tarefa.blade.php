<div id="modalAtribuirTarefa" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        
        <button type="button" onclick="fecharModalAtribuir()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            ✕
        </button>

        <div class="flex-shrink-0 mb-6">
            <h2 class="text-3xl font-bold text-[#1e2d3d] font-serif">Atribuir tarefa</h2>
            <p class="text-sm text-gray-500 mt-1">Escolha a tarefa e o colaborador que vai assumi-la.</p>
        </div>

        <form action="{{ route('rotinas.atribuir') }}" method="POST" class="space-y-6 sans-text overflow-y-auto flex-1 pr-1">
            @csrf
            
            <div class="border border-gray-200 rounded-3xl p-6 bg-white shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-[#1e2d3d]">1. Tarefa</h3>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Colaborador atual</label>
                    <select id="filtroColaboradorAtual" onchange="filtrarTarefasPorColaborador()" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                        <option value="">Todos os colaboradores</option>
                        @foreach(DB::table('gr_usuarios')->orderBy('name')->get() as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2 max-h-48 overflow-y-auto pr-1" id="listaTarefasModal">
                    @php
                        $rotinasCompletas = DB::table('gr_rotinas')
                            ->join('gr_tarefas', 'gr_rotinas.id_tarefa', '=', 'gr_tarefas.id')
                            ->leftJoin('gr_usuarios', 'gr_rotinas.id_usuario', '=', 'gr_usuarios.id')
                            ->select('gr_rotinas.*', 'gr_tarefas.nome_tarefa', 'gr_usuarios.name as usuario_nome')
                            ->get();
                    @endphp

                    @foreach($rotinasCompletas as $rotina)
                        <div class="item-tarefa border border-gray-200 rounded-2xl p-4 flex items-center justify-between bg-white cursor-pointer hover:bg-gray-50 transition" data-usuario="{{ $rotina->id_usuario }}">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-slate-700">{{ $rotina->horario }}</span>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">{{ $rotina->nome_tarefa ?? 'Tarefa' }}</p>
                                    <span class="text-[10px] text-gray-400">Local: {{ $rotina->local }} | Resp: {{ $rotina->usuario_nome ?? 'Não atribuído' }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-2.5 py-1 text-[10px] font-semibold bg-emerald-50 text-emerald-700 rounded-full border border-emerald-100">Ativa</span>
                                <input type="checkbox" name="rotinas_ids[]" value="{{ $rotina->id_rotina }}" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d]">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border border-gray-200 rounded-3xl p-6 bg-white shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-[#1e2d3d]">2. Atribuir para</h3>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Novo responsável</label>
                    <select name="novo_colaborador_id" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                        <option value="" disabled selected>Selecione o novo colaborador</option>
                        @foreach(DB::table('gr_usuarios')->orderBy('name')->get() as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="text-xs text-gray-400">Selecione uma ou mais tarefas acima e o novo responsável para realizar a atribuição.</p>
            </div>

            <div class="pt-6 border-t border-gray-100 mt-4 flex items-center justify-end gap-4">
                <button type="button" onclick="fecharModalAtribuir()" class="w-1/2 py-3.5 rounded-2xl border border-gray-300 text-slate-800 hover:bg-gray-50 text-sm font-bold transition text-center">
                    Cancelar
                </button>
                <button type="submit" class="w-1/2 py-3.5 rounded-2xl bg-[#1e293b] text-white hover:bg-slate-700 text-sm font-bold transition text-center">
                    Atribuir tarefa
                </button>
            </div>
        </form>

    </div>
</div>

<script>
    function abrirModalAtribuir() {
        const modal = document.getElementById('modalAtribuirTarefa');
        if (modal) modal.classList.remove('hidden');
    }

    function fecharModalAtribuir() {
        const modal = document.getElementById('modalAtribuirTarefa');
        if (modal) modal.classList.add('hidden');
    }

    function filtrarTarefasPorColaborador() {
        const idSelecionado = document.getElementById('filtroColaboradorAtual').value;
        const itens = document.querySelectorAll('.item-tarefa');

        itens.forEach(item => {
            const usuarioIdItem = item.getAttribute('data-usuario');
            if (idSelecionado === "" || usuarioIdItem === idSelecionado) {
                item.style.display = "flex";
            } else {
                item.style.display = "none";
            }
        });
    }
</script>