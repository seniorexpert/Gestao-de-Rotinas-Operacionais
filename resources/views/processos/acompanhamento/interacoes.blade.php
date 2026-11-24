<script>
    (() => {
        const painel = document.querySelector('[data-painel-acompanhamento]');
        const integrado = Boolean(painel.dataset.urlAcompanhamento);
        const filtros = ['aba', 'busca', 'page'];
        const lista = '[data-lista-acompanhamento]';
        const formulario = '#busca-acompanhamento';
        const abas = '#abas-acompanhamento';
        const resultados = '#resultados-acompanhamento';
        let agendamento;
        let requisicao;

        function enderecoDaLista() {
            const pagina = new URL(location.href);
            if (!integrado) return pagina;

            const endereco = new URL(painel.dataset.urlAcompanhamento);
            for (const filtro of filtros) {
                const valor = pagina.searchParams.get('acomp_' + filtro);
                if (valor !== null) endereco.searchParams.set(filtro, valor);
            }
            return endereco;
        }

        function atualizarEndereco(endereco) {
            if (integrado) {
                const pagina = new URL(location.href);
                for (const filtro of filtros) {
                    const valor = endereco.searchParams.get(filtro);
                    if (valor) pagina.searchParams.set('acomp_' + filtro, valor);
                    else pagina.searchParams.delete('acomp_' + filtro);
                }
                endereco = pagina;
            }
            history.replaceState(history.state, '', endereco);
        }

        async function atualizarLista(endereco, { navegacao = false, automatica = false, foco } = {}) {
            requisicao?.abort();
            const controle = new AbortController();
            requisicao = controle;
            const headers = { 'X-Painel-Acompanhamento': '1', Accept: 'application/json' };
            if (automatica) headers['X-Atualizacao-Automatica'] = '1';

            try {
                const resposta = await fetch(endereco, { signal: controle.signal, cache: 'no-store', headers });
                if (resposta.status === 401 || resposta.status === 403) {
                    painel.replaceChildren();
                    painel.hidden = true;
                    return;
                }
                if (!resposta.ok) throw new Error();

                const documento = new DOMParser().parseFromString(await resposta.text(), 'text/html');
                const novaLista = documento.querySelector(lista);
                if (!novaLista) throw new Error();
                if (controle.signal.aborted) return;

                if (painel.querySelector(lista)) {
                    for (const seletor of [abas, resultados]) {
                        const atual = painel.querySelector(seletor);
                        const novo = novaLista.querySelector(seletor);
                        if (atual.innerHTML !== novo.innerHTML) atual.replaceChildren(...novo.childNodes);
                    }
                    painel.querySelector(formulario).elements.aba.value = novaLista.querySelector(formulario).elements.aba.value;
                } else {
                    painel.replaceChildren(novaLista);
                }
                if (navegacao || resposta.redirected) atualizarEndereco(new URL(resposta.url));
                if (foco) painel.querySelector(foco)?.focus({ preventScroll: true });
            } catch {
                if (!controle.signal.aborted && !painel.querySelector(lista)) {
                    painel.querySelector('[data-carregando-acompanhamento]').hidden = true;
                    painel.querySelector('[data-erro-acompanhamento]').hidden = false;
                }
            } finally {
                if (requisicao === controle) requisicao = null;
            }
        }

        function buscar(form) {
            const endereco = new URL(form.action);
            endereco.search = new URLSearchParams(new FormData(form));
            atualizarLista(endereco, { navegacao: true });
        }

        painel.addEventListener('input', evento => {
            const form = evento.target.form;
            if (form?.id !== 'busca-acompanhamento') return;
            clearTimeout(agendamento);
            requisicao?.abort();
            agendamento = setTimeout(() => {
                agendamento = null;
                buscar(form);
            }, 250);
        });

        if (integrado) {
            painel.addEventListener('submit', evento => {
                evento.preventDefault();
                clearTimeout(agendamento);
                agendamento = null;
                buscar(evento.target);
            });

            painel.addEventListener('click', evento => {
                if (evento.target.closest('[data-tentar-acompanhamento]')) {
                    atualizarLista(enderecoDaLista());
                    return;
                }
                const link = evento.target.closest('a');
                if (!link) return;
                if (link.hasAttribute('data-item-acompanhamento')) {
                    const abaDiaria = document.querySelector('#abasRotinas [aria-selected="true"]')?.dataset.abaRotina;
                    if (abaDiaria) {
                        const endereco = new URL(link.href);
                        endereco.searchParams.set('aba_diaria', abaDiaria);
                        link.href = endereco;
                    }
                    return;
                }
                if (evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;
                evento.preventDefault();
                clearTimeout(agendamento);
                agendamento = null;
                const endereco = new URL(link.href);
                const mudouAba = Boolean(link.closest(abas));
                if (mudouAba) {
                    const termo = painel.querySelector(formulario).elements.busca.value.trim();
                    if (termo) endereco.searchParams.set('busca', termo);
                    else endereco.searchParams.delete('busca');
                }
                atualizarLista(endereco, {
                    navegacao: true,
                    foco: mudouAba ? abas + ' [aria-current="page"]' : resultados,
                });
            });
            atualizarLista(enderecoDaLista());
        }

        setInterval(() => {
            const foco = document.activeElement;
            if (!document.hidden && !painel.hidden && !agendamento && !requisicao && !painel.contains(foco)) {
                atualizarLista(enderecoDaLista(), { automatica: true });
            }
        }, 30000);
    })();
</script>
