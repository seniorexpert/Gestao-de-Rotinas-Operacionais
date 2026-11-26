<dialog id="modalAtribuirTarefa" aria-labelledby="tituloAtribuicao"
        class="m-auto max-h-[calc(100dvh-1.5rem)] w-[calc(100%-1.5rem)] max-w-5xl overflow-y-auto rounded-2xl border border-[#e8d6bf] bg-white p-0 text-[#17384b] shadow-xl backdrop:bg-slate-950/45 sm:max-h-[90dvh]">
    <header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-[#e8d6bf] bg-white px-4 py-3 sm:px-6">
        <h2 id="tituloAtribuicao" class="font-['Fraunces',Georgia,serif] text-xl font-semibold sm:text-2xl">Atribuir tarefa</h2>
        <button type="button" data-fechar-atribuicao autofocus aria-label="Fechar"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#e8d6bf] hover:bg-[#f9f7f2]">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </header>
    <div id="conteudoAtribuicao" class="p-4 sm:p-6"></div>
</dialog>

<script>
    (() => {
        const modal = document.getElementById('modalAtribuirTarefa');
        const titulo = document.getElementById('tituloAtribuicao');
        const conteudo = document.getElementById('conteudoAtribuicao');
        const rota = @js(route('monitoramento.processo', ['processo' => 'PROCESSO']));
        let consulta;
        let salvando = false;

        const mensagem = (texto, erro = false) => {
            const aviso = document.createElement('p');
            aviso.className = erro ? 'text-sm text-rose-800' : 'text-sm text-[#536b7b]';
            aviso.setAttribute('role', erro ? 'alert' : 'status');
            aviso.textContent = texto;
            conteudo.replaceChildren(aviso);
        };

        const normalizar = texto => texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
        const campoDaExecucao = '[name="execucoes_ids[]"]';
        const camposVisiveis = () => [...conteudo.querySelectorAll('[data-tarefa-atribuicao]:not([hidden]) ' + campoDaExecucao)];

        function atualizar() {
            const busca = normalizar(conteudo.querySelector('[data-busca-atribuicao]').value);
            const responsavel = conteudo.querySelector('[data-responsavel-atribuicao]').value;
            const status = conteudo.querySelector('[data-status-atribuicao]').value;
            let visiveis = 0;

            conteudo.querySelectorAll('[data-tarefa-atribuicao]').forEach(tarefa => {
                tarefa.hidden = (responsavel !== '' && tarefa.dataset.responsavel !== responsavel)
                    || (status !== '' && tarefa.dataset.status !== status)
                    || !normalizar(tarefa.textContent).includes(busca);
                if (tarefa.hidden) {
                    const campo = tarefa.querySelector(campoDaExecucao);
                    if (campo) campo.checked = false;
                } else {
                    visiveis++;
                }
            });
            conteudo.querySelector('[data-vazio-atribuicao]').hidden = visiveis > 0;

            const selecionarTodas = conteudo.querySelector('[data-selecionar-todas]');
            if (!selecionarTodas) return;

            const campos = camposVisiveis();
            const selecionadas = campos.filter(campo => campo.checked).length;
            selecionarTodas.disabled = campos.length === 0;
            selecionarTodas.checked = selecionadas > 0 && selecionadas === campos.length;
            selecionarTodas.indeterminate = selecionadas > 0 && selecionadas < campos.length;
            conteudo.querySelector('[data-total-atribuicao]').textContent = selecionadas + (selecionadas === 1 ? ' tarefa selecionada' : ' tarefas selecionadas');

            const destino = conteudo.querySelector('[name="novo_colaborador_id"]');
            if (destino) {
                const grupo = destino.selectedOptions[0]?.dataset.grupo;
                conteudo.querySelector('[data-grupo-atribuicao]').textContent = grupo ? 'Grupo de acesso · ' + grupo : '';
                conteudo.querySelector('[data-enviar-atribuicao]').disabled = salvando || !selecionadas || !destino.value;
            }
            const concluir = conteudo.querySelector('[data-concluir-selecionadas]');
            if (concluir) {
                concluir.disabled = salvando || !selecionadas || !conteudo.querySelector('[name="comentario"]').value.trim();
            }
        }

        window.abrirModalAtribuir = async (processo, colaborador = '', execucao = '') => {
            if (!processo || salvando) return;
            consulta?.abort();
            const requisicao = new AbortController();
            consulta = requisicao;
            titulo.textContent = 'Atribuir tarefa';
            mensagem('Carregando as tarefas do processo…');
            modal.showModal();

            try {
                const resposta = await fetch(rota.replace('/PROCESSO', '/' + encodeURIComponent(processo)), {
                    cache: 'no-store',
                    signal: requisicao.signal,
                });
                if (!resposta.ok || resposta.redirected) throw new Error();
                const fragmento = await resposta.text();
                if (requisicao.signal.aborted) return;
                conteudo.innerHTML = fragmento;

                const processoAberto = conteudo.querySelector('[data-painel-atribuicao]');
                titulo.textContent = processoAberto.dataset.titulo;
                const responsavel = conteudo.querySelector('[data-responsavel-atribuicao]');
                if ([...responsavel.options].some(opcao => opcao.value === colaborador)) responsavel.value = colaborador;
                const tarefa = [...conteudo.querySelectorAll(campoDaExecucao)].find(campo => campo.value === execucao);
                if (tarefa) tarefa.checked = true;
                atualizar();
            } catch {
                if (!requisicao.signal.aborted) mensagem('Não foi possível carregar as tarefas do processo. Feche o popup e tente novamente.', true);
            }
        };

        modal.addEventListener('click', evento => {
            if (!salvando && evento.target.closest('[data-fechar-atribuicao]')) modal.close();
        });
        modal.addEventListener('cancel', evento => {
            if (salvando) evento.preventDefault();
        });
        modal.addEventListener('close', () => consulta?.abort());
        conteudo.addEventListener('input', evento => {
            if (evento.target.matches('[data-busca-atribuicao], [name="comentario"]')) atualizar();
        });
        conteudo.addEventListener('change', evento => {
            if (evento.target.matches('[data-selecionar-todas]')) {
                camposVisiveis().forEach(campo => campo.checked = evento.target.checked);
            }
            atualizar();
        });
        conteudo.addEventListener('submit', async evento => {
            evento.preventDefault();
            const formulario = evento.target;
            const botao = evento.submitter;
            if (!botao || botao.disabled || salvando) return;

            const concluir = botao.matches('[data-concluir-selecionadas]');
            const mensagemDeErro = concluir ? 'Não foi possível concluir as tarefas selecionadas.' : 'Não foi possível atribuir as tarefas selecionadas.';
            const dadosFormulario = new FormData(formulario);
            salvando = true;
            atualizar();
            const erro = formulario.querySelector('[data-erro-atribuicao]');
            erro.hidden = true;
            try {
                const resposta = await fetch(concluir ? botao.formAction : formulario.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': dadosFormulario.get('_token') },
                    body: JSON.stringify({
                        processo: dadosFormulario.get('processo'),
                        execucoes_ids: dadosFormulario.getAll('execucoes_ids[]'),
                        ...(concluir ? { comentario: dadosFormulario.get('comentario') } : { novo_colaborador_id: dadosFormulario.get('novo_colaborador_id') }),
                    }),
                });
                if (resposta.redirected) throw new Error();
                if (resposta.ok) {
                    location.reload();
                    return;
                }

                const dados = resposta.status === 422 ? await resposta.json() : null;
                erro.textContent = Object.values(dados?.errors ?? {}).flat().join(' ') || mensagemDeErro;
            } catch {
                erro.textContent = mensagemDeErro;
            }
            erro.hidden = false;
            salvando = false;
            atualizar();
        });
    })();
</script>
