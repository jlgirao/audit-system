<label>Código</label>
<input type="text" name="codigo" value="{{ old('codigo', $pergunta->codigo ?? '') }}" required>

<label>Texto da pergunta</label>
<textarea name="texto_pergunta" rows="2" required>{{ old('texto_pergunta', $pergunta->texto_pergunta ?? '') }}</textarea>

<label>Categoria</label>
<input type="text" name="categoria" value="{{ old('categoria', $pergunta->categoria ?? '') }}">

<label>Aba no Excel</label>
<input type="text" name="aba_excel" value="{{ old('aba_excel', $pergunta->aba_excel ?? '') }}" required>

<label>Linha no Excel</label>
<input type="number" name="linha_excel" value="{{ old('linha_excel', $pergunta->linha_excel ?? '') }}" required>

<p style="font-size:13px; color:#666; margin-top:16px;">
    Colunas de saída no Excel — cada campo respondido vai para uma coluna própria:
</p>

<label>Coluna — Resposta (Sim / Não / Não aplicável)</label>
<input type="text" name="coluna_ha_evidencia" value="{{ old('coluna_ha_evidencia', $pergunta->coluna_ha_evidencia ?? '') }}" required>

<label>Coluna — Observações</label>
<input type="text" name="coluna_observacoes" value="{{ old('coluna_observacoes', $pergunta->coluna_observacoes ?? '') }}" required>

<label>Coluna — Arquivo da Evidência (caminho no Dropbox)</label>
<input type="text" name="coluna_evidencia" value="{{ old('coluna_evidencia', $pergunta->coluna_evidencia ?? '') }}" required>

<label>Coluna — Parecer</label>
<input type="text" name="coluna_parecer" value="{{ old('coluna_parecer', $pergunta->coluna_parecer ?? '') }}" required>

<label>Ordem de exibição</label>
<input type="number" name="ordem" value="{{ old('ordem', $pergunta->ordem ?? 0) }}">

<button type="submit">Salvar</button>
