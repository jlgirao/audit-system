{{-- Espera receber: $valorAtual (string, pode ser vazio) --}}

<label>Caminho da pasta no Dropbox</label>
<div style="display:flex; gap:8px;">
    <input type="text" name="dropbox_folder_path" id="dropbox_folder_path"
        value="{{ old('dropbox_folder_path', $valorAtual ?? '') }}"
        placeholder="/Auditorias/Processo_2026_001" required style="flex:1; margin-top:0;">
    <button type="button" onclick="dropboxSeletorAbrir()" style="margin-top:0; white-space:nowrap;">
        Escolher no Dropbox
    </button>
</div>
<p style="font-size:12px; color:#666; margin-top:4px;">
    Pode digitar direto ou usar o botão para navegar pelas pastas reais do Dropbox conectado.
</p>

<div id="dropbox-seletor" style="display:none; border:1px solid #ddd; border-radius:6px; padding:12px; margin-top:8px; background:#fafafa;">
    <p id="dropbox-seletor-caminho" style="font-size:13px; font-weight:bold; margin:0 0 8px;"></p>
    <p id="dropbox-seletor-erro" style="font-size:13px; color:#991b1b; display:none;"></p>
    <ul id="dropbox-seletor-lista" style="list-style:none; padding:0; margin:0; max-height:260px; overflow-y:auto; border:1px solid #eee; border-radius:4px;"></ul>
    <div style="margin-top:10px; display:flex; gap:8px;">
        <button type="button" onclick="dropboxSeletorUsar()" style="margin-top:0;">Usar esta pasta</button>
        <button type="button" onclick="dropboxSeletorFechar()" style="margin-top:0; background:#666;">Cancelar</button>
    </div>
</div>

<script>
    let dropboxCaminhoAtual = '';

    function dropboxSeletorAbrir() {
        document.getElementById('dropbox-seletor').style.display = 'block';
        dropboxCarregarPastas('');
    }

    function dropboxSeletorFechar() {
        document.getElementById('dropbox-seletor').style.display = 'none';
    }

    function dropboxSeletorUsar() {
        document.getElementById('dropbox_folder_path').value = dropboxCaminhoAtual || '/';
        dropboxSeletorFechar();
    }

    async function dropboxCarregarPastas(caminho) {
        const listaEl = document.getElementById('dropbox-seletor-lista');
        const erroEl = document.getElementById('dropbox-seletor-erro');
        erroEl.style.display = 'none';
        listaEl.innerHTML = '<li style="padding:8px; color:#666;">Carregando…</li>';

        try {
            const resposta = await fetch(`{{ route('dropbox.pastas') }}?caminho=${encodeURIComponent(caminho)}`);
            const dados = await resposta.json();

            if (!resposta.ok) {
                listaEl.innerHTML = '';
                erroEl.textContent = dados.erro || 'Não foi possível listar as pastas do Dropbox.';
                erroEl.style.display = 'block';
                return;
            }

            dropboxCaminhoAtual = dados.caminho_atual;
            document.getElementById('dropbox-seletor-caminho').textContent =
                'Pasta atual: ' + (dropboxCaminhoAtual || '/ (raiz)');

            listaEl.innerHTML = '';

            if (dropboxCaminhoAtual) {
                const partes = dropboxCaminhoAtual.split('/').filter(Boolean);
                partes.pop();
                const pastaAcima = partes.length ? '/' + partes.join('/') : '';

                const li = document.createElement('li');
                li.style.padding = '6px 10px';
                li.style.borderBottom = '1px solid #eee';
                li.style.cursor = 'pointer';
                li.textContent = '.. (voltar)';
                li.onclick = () => dropboxCarregarPastas(pastaAcima);
                listaEl.appendChild(li);
            }

            if (dados.pastas.length === 0 && !dropboxCaminhoAtual) {
                listaEl.innerHTML += '<li style="padding:8px; color:#666;">Nenhuma subpasta encontrada.</li>';
            }

            dados.pastas.forEach((pasta) => {
                const li = document.createElement('li');
                li.style.padding = '6px 10px';
                li.style.borderBottom = '1px solid #eee';
                li.style.cursor = 'pointer';
                li.textContent = '📁 ' + pasta.nome;
                li.onclick = () => dropboxCarregarPastas(pasta.caminho);
                listaEl.appendChild(li);
            });
        } catch (e) {
            listaEl.innerHTML = '';
            erroEl.textContent = 'Erro de conexão ao buscar as pastas.';
            erroEl.style.display = 'block';
        }
    }
</script>
