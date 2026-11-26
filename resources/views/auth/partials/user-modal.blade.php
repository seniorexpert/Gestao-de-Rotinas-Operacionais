<dialog id="usuarioModal" aria-labelledby="tituloUsuario" class="w-11/12 max-w-lg max-h-[90vh] m-auto rounded-3xl bg-white shadow-2xl border border-stone-200/60 backdrop:bg-black/40">
    <div class="flex items-center justify-between px-6 sm:px-8 pt-7 pb-5 border-b border-stone-100">
        <h2 id="tituloUsuario" class="text-2xl font-bold text-[#1e2e3e] font-serif">Novo Usuário</h2>
        <button type="button" data-fechar-usuario aria-label="Fechar formulário de usuário" class="text-gray-400 hover:text-gray-600 transition">
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    </div>

    <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" id="formUsuario" data-cadastro="{{ route('users.store') }}" class="p-6 sm:p-8 space-y-5">
        @csrf
        <input type="hidden" name="_method" value="PUT" disabled>
        <input type="hidden" name="usuario_edicao" disabled>
        <input type="hidden" name="remove_photo" value="0">

        @if ($errors->any())
            <ul id="errosUsuario" role="alert" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800" hidden>
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        @endif

        <div class="flex items-start gap-4">
            <div class="w-16 h-16 shrink-0 overflow-hidden rounded-full bg-stone-100 text-stone-600 flex items-center justify-center" aria-hidden="true">
                <span id="avatarPadraoUsuario"><i class="fa-solid fa-user text-xl"></i></span>
                <img id="previewFotoUsuario" alt="" class="hidden w-full h-full object-cover">
            </div>
            <div class="min-w-0 flex-1">
                <label for="fotoUsuario" class="block text-xs font-medium text-stone-700 mb-1.5">Foto</label>
                <input id="fotoUsuario" type="file" name="photo" accept="image/*" aria-describedby="ajudaFotoUsuario"
                    class="block w-full text-xs text-stone-600 file:mr-2 file:rounded-lg file:border-0 file:bg-stone-100 file:px-3 file:py-2 file:font-medium file:text-stone-800 hover:file:bg-stone-200">
                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <p id="ajudaFotoUsuario" class="text-xs text-stone-500">Até 2 MB.</p>
                    <button type="button" id="removerFotoUsuario" class="hidden text-xs font-medium text-rose-700 hover:underline">
                        Remover foto
                    </button>
                </div>
            </div>
        </div>

        <div>
            <label for="nomeCompletoUsuario" class="block text-xs font-medium text-stone-700 mb-1.5">Nome completo</label>
            <input id="nomeCompletoUsuario" type="text" name="usuario" autocomplete="name" required maxlength="255" autofocus
                class="w-full px-4 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 focus:outline-none focus:ring-1 focus:ring-stone-400">
        </div>

        <div>
            <label for="loginUsuario" class="block text-xs font-medium text-stone-700 mb-1.5">Nome de usuário</label>
            <input id="loginUsuario" type="text" name="username" autocomplete="username" required maxlength="100" data-sem-title-case
                class="w-full px-4 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 focus:outline-none focus:ring-1 focus:ring-stone-400 read-only:bg-stone-100">
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="grupoAcessoUsuario" class="block text-xs font-medium text-stone-700 mb-1.5">Grupo de Acesso</label>
                <select id="grupoAcessoUsuario" name="id_grupo_acesso" @required($grupoObrigatorio)
                    class="w-full px-4 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 focus:outline-none focus:ring-1 focus:ring-stone-400">
                    <option value="">{{ $grupoObrigatorio ? 'Selecione um grupo' : 'Sem grupo de acesso' }}</option>
                    @foreach ($gruposAcesso as $grupo)
                        <option value="{{ $grupo->id_grupo_acesso }}">{{ $grupo->grupo_acesso }}{{ $grupo->status ? '' : ' (inativo)' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="statusUsuario" class="block text-xs font-medium text-stone-700 mb-1.5">Situação</label>
                <select id="statusUsuario" name="status" required
                    class="w-full px-4 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 focus:outline-none focus:ring-1 focus:ring-stone-400">
                    <option value="1">Ativo</option>
                    <option value="0">Inativo</option>
                </select>
            </div>
        </div>

        <fieldset>
            <legend class="text-xs font-medium text-stone-700 mb-1.5">Unidades</legend>
            <input type="hidden" name="empresas" value="">
            <div class="max-h-36 overflow-y-auto space-y-2 rounded-xl border border-stone-200 px-4 py-2.5">
                @forelse ($empresas as $empresa)
                    <label class="flex items-center gap-2 text-sm text-stone-800">
                        <input type="checkbox" name="empresas[]" value="{{ $empresa->id_empresa }}" class="accent-[#1e2e3e]">
                        {{ $empresa->empresa }}{{ $empresa->status ? '' : ' (inativa)' }}
                    </label>
                @empty
                    <p class="text-sm text-stone-500">Nenhuma unidade cadastrada.</p>
                @endforelse
            </div>
        </fieldset>

        <div>
            <label for="senhaUsuario" id="rotuloSenhaUsuario" class="block text-xs font-medium text-stone-700 mb-1.5">Senha</label>
            <div class="relative">
                <input id="senhaUsuario" type="password" name="password" autocomplete="new-password" required
                    class="w-full pl-4 pr-10 py-2.5 bg-white border border-stone-200 rounded-xl text-sm text-stone-800 focus:outline-none focus:ring-1 focus:ring-stone-400">
                <button type="button" id="alternarSenhaUsuario" aria-label="Mostrar senha" aria-pressed="false"
                    class="absolute right-3.5 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-600">
                    <i class="fa-regular fa-eye text-sm" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="pt-4 flex gap-3 border-t border-stone-100">
            <button type="button" data-fechar-usuario
                class="flex-1 py-2.5 border border-stone-300 rounded-xl text-sm font-medium text-stone-700 hover:bg-gray-50 transition">
                Cancelar
            </button>
            <button type="submit" class="flex-1 py-2.5 bg-[#1e2e3e] hover:bg-[#15212d] text-white rounded-xl text-sm font-medium transition">
                Salvar
            </button>
        </div>
    </form>
</dialog>

<script>
    const usuarioModal = document.getElementById('usuarioModal');
    const formUsuario = document.getElementById('formUsuario');
    const loginUsuario = document.getElementById('loginUsuario');
    const senhaUsuario = document.getElementById('senhaUsuario');
    const alternarSenhaUsuario = document.getElementById('alternarSenhaUsuario');
    const campoFotoUsuario = document.getElementById('fotoUsuario');
    const previewFotoUsuario = document.getElementById('previewFotoUsuario');
    const removerFotoUsuario = document.getElementById('removerFotoUsuario');
    const campoRemoverFoto = formUsuario.elements.namedItem('remove_photo');
    const botoesEdicaoUsuario = Array.from(document.querySelectorAll('[data-editar-usuario]'));
    let fotoAtual = null;
    let urlFotoLocal = null;

    function atualizarPreviewFoto() {
        if (urlFotoLocal) {
            URL.revokeObjectURL(urlFotoLocal);
        }

        const arquivo = campoFotoUsuario.files[0];
        urlFotoLocal = arquivo ? URL.createObjectURL(arquivo) : null;
        const url = urlFotoLocal ?? (campoRemoverFoto.value === '1' ? null : fotoAtual);

        if (url) {
            previewFotoUsuario.src = url;
        } else {
            previewFotoUsuario.removeAttribute('src');
        }

        previewFotoUsuario.classList.toggle('hidden', !url);
        document.getElementById('avatarPadraoUsuario').classList.toggle('hidden', !!url);
        removerFotoUsuario.classList.toggle('hidden', !url);
    }

    function mostrarSenhaUsuario(mostrar) {
        senhaUsuario.type = mostrar ? 'text' : 'password';
        alternarSenhaUsuario.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
        alternarSenhaUsuario.setAttribute('aria-pressed', String(mostrar));
        alternarSenhaUsuario.querySelector('i').classList.toggle('fa-eye', !mostrar);
        alternarSenhaUsuario.querySelector('i').classList.toggle('fa-eye-slash', mostrar);
    }

    function abrirFormularioUsuario(usuario = null, dadosAnteriores = null) {
        const editando = usuario !== null;
        const dados = dadosAnteriores ?? usuario ?? {};
        const empresas = Array.isArray(dados.empresas) ? dados.empresas.map(String) : [];
        const metodo = formUsuario.elements.namedItem('_method');
        const usuarioEdicao = formUsuario.elements.namedItem('usuario_edicao');

        formUsuario.reset();
        formUsuario.action = usuario?.url ?? formUsuario.dataset.cadastro;
        metodo.disabled = !editando;
        usuarioEdicao.disabled = !editando;
        usuarioEdicao.value = usuario?.id ?? '';
        fotoAtual = usuario?.fotoUrl ?? null;
        campoRemoverFoto.value = String(dadosAnteriores?.remove_photo) === '1' ? '1' : '0';
        atualizarPreviewFoto();

        document.getElementById('tituloUsuario').textContent = editando ? 'Editar Usuário' : 'Novo Usuário';
        document.getElementById('nomeCompletoUsuario').value = typeof dados.usuario === 'string' ? dados.usuario : '';
        document.getElementById('grupoAcessoUsuario').value = dados.id_grupo_acesso ?? '';
        document.getElementById('statusUsuario').value = String(dados.status ?? usuario?.status ?? 1);
        formUsuario.querySelectorAll('input[name="empresas[]"]').forEach(campo => {
            campo.checked = empresas.includes(campo.value);
        });

        loginUsuario.value = editando ? usuario.username : (typeof dados.username === 'string' ? dados.username : '');
        loginUsuario.readOnly = editando;
        senhaUsuario.required = !editando;
        senhaUsuario.placeholder = editando ? 'Em branco para manter a senha atual' : '';
        document.getElementById('rotuloSenhaUsuario').textContent = editando ? 'Nova senha' : 'Senha';
        document.getElementById('errosUsuario')?.toggleAttribute('hidden', dadosAnteriores === null);
        mostrarSenhaUsuario(false);
        usuarioModal.showModal();
    }

    document.getElementById('adicionarUsuario').addEventListener('click', () => abrirFormularioUsuario());
    botoesEdicaoUsuario.forEach(botao => botao.addEventListener('click', () => {
        abrirFormularioUsuario(JSON.parse(botao.dataset.editarUsuario));
    }));
    usuarioModal.querySelectorAll('[data-fechar-usuario]').forEach(botao => {
        botao.addEventListener('click', () => usuarioModal.close());
    });
    alternarSenhaUsuario.addEventListener('click', () => mostrarSenhaUsuario(senhaUsuario.type === 'password'));
    campoFotoUsuario.addEventListener('change', () => {
        if (campoFotoUsuario.files.length) {
            campoRemoverFoto.value = '0';
        }
        atualizarPreviewFoto();
    });
    removerFotoUsuario.addEventListener('click', () => {
        campoFotoUsuario.value = '';
        campoRemoverFoto.value = '1';
        atualizarPreviewFoto();
    });
    usuarioModal.addEventListener('close', () => {
        campoFotoUsuario.value = '';
        atualizarPreviewFoto();
    });

    @if ($errors->any())
        const idUsuarioAnterior = @js(old('usuario_edicao'));
        const botaoUsuarioAnterior = botoesEdicaoUsuario.find(botao => botao.dataset.usuarioId === String(idUsuarioAnterior));
        const dadosUsuarioAnteriores = @js([
            'usuario' => old('usuario'),
            'username' => old('username'),
            'id_grupo_acesso' => old('id_grupo_acesso'),
            'status' => old('status'),
            'empresas' => old('empresas'),
            'remove_photo' => old('remove_photo'),
        ]);
        abrirFormularioUsuario(botaoUsuarioAnterior ? JSON.parse(botaoUsuarioAnterior.dataset.editarUsuario) : null, dadosUsuarioAnteriores);
    @endif
</script>
