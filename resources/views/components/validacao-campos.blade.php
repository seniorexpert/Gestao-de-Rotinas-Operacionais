{{--
    Destaque dos campos obrigatórios não preenchidos.

    Quando o navegador recusa um campo (balão de "preencha este campo"), o campo fica
    com a borda vermelha até ser preenchido. Um campo auxiliar pode apontar o elemento
    que deve ficar vermelho com data-campo-visual="id-do-elemento".
--}}
<style>
    .campo-invalido {
        border-color: #e11d48 !important;
        box-shadow: 0 0 0 3px rgb(225 29 72 / 0.15) !important;
    }
</style>

<script>
    (function () {
        const CLASSE = 'campo-invalido';
        const destaque = (campo) => (campo.dataset.campoVisual && document.getElementById(campo.dataset.campoVisual)) || campo;

        function desmarcar(campo) {
            delete campo.dataset.campoInvalido;
            destaque(campo).classList.remove(CLASSE);
        }

        document.addEventListener('invalid', (evento) => {
            const campo = evento.target;
            campo.dataset.campoInvalido = '1';
            destaque(campo).classList.add(CLASSE);
        }, true);

        function revalidar() {
            document.querySelectorAll('[data-campo-invalido]').forEach((campo) => {
                if (campo.disabled || campo.validity.valid) {
                    desmarcar(campo);
                }
            });
        }

        document.addEventListener('input', revalidar);
        document.addEventListener('change', revalidar);
        document.addEventListener('reset', (evento) => {
            setTimeout(() => evento.target.querySelectorAll('[data-campo-invalido]').forEach(desmarcar), 0);
        });
    })();
</script>
