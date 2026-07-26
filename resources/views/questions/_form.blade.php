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

<label>Coluna da resposta (ex: D)</label>
<input type="text" name="coluna_resposta" value="{{ old('coluna_resposta', $pergunta->coluna_resposta ?? '') }}" required>

<label>Coluna da evidência (ex: E)</label>
<input type="text" name="coluna_evidencia" value="{{ old('coluna_evidencia', $pergunta->coluna_evidencia ?? '') }}" required>

<label>Ordem de exibição</label>
<input type="number" name="ordem" value="{{ old('ordem', $pergunta->ordem ?? 0) }}">

<button type="submit">Salvar</button>
