<label>Código</label>
<input type="text" name="codigo" value="{{ old('codigo', $pergunta->codigo ?? '') }}" required>

<label>Texto da pergunta</label>
<textarea name="texto_pergunta" rows="2" required>{{ old('texto_pergunta', $pergunta->texto_pergunta ?? '') }}</textarea>

<label>Contexto adicional para a IA (opcional)</label>
<textarea name="contexto_adicional" rows="3" placeholder="Ex: Procure o valor no contrato assinado. Considere a resposta da pergunta H-007. Consulte o Decreto-Lei nº 84/2017. BF = Beneficiário Final.">{{ old('contexto_adicional', $pergunta->contexto_adicional ?? '') }}</textarea>
<p style="font-size:12px; color:#666; margin-top:2px;">
    Orientações extras que a IA deve considerar ao avaliar essa pergunta — onde procurar a
    resposta, referências a outras perguntas, leis a consultar, significado de siglas, etc.
    Isso entra no prompt enviado ao modelo, não afeta a comparação inicial por similaridade.
</p>

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

<div style="display:flex; gap:8px;">
    <button type="submit">Salvar</button>
    <a href="{{ route('questions.index') }}" class="btn" style="background:#57534e;">Cancelar</a>
</div>
