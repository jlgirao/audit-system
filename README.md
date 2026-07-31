# Fase 4 — Painel de Métricas de Acerto da IA (+ correção do link do Template)

## Arquivos

```
app/Http/Controllers/MetricsController.php       → NOVO
database/seeders/RolesAndPermissionsSeeder.php  → substitui (nova permissão)
routes/web.php                                    → substitui (confira antes, como sempre)
resources/views/metricas/index.blade.php           → NOVO
resources/views/layouts/app.blade.php               → substitui (já inclui a correção do link
                                                        "Template Excel" que tinha sumido desde a Fase 3,
                                                        + o link novo "Métricas")
```

Sem migration — usa só os dados que já existem em `question_evidence_match`.

## O que o painel mostra

Acesse `/metricas` (link "Métricas" no menu, para admin e auditor).

**Resumo geral**: total de sugestões geradas pela IA, quantas foram
confirmadas, quantas rejeitadas, quantas ainda aguardam revisão, e a
taxa de acerto (confirmadas ÷ (confirmadas + rejeitadas) — sugestões
ainda não revisadas não entram nessa conta, porque ainda não sabemos se
estão certas ou erradas).

Também mostra a **confiança média** das sugestões confirmadas vs.
rejeitadas — se os dois números estiverem próximos, é sinal de que o
campo `confianca` que o LLM retorna não está discriminando bem entre
"provavelmente certo" e "provavelmente errado", o que pode indicar que
vale ajustar o prompt para o modelo ser mais criterioso nesse campo.

**Por pergunta**: uma tabela mostrando, para cada pergunta do cadastro,
quantas sugestões ela já gerou e a taxa de acerto — com uma barrinha
colorida (verde ≥70%, laranja 40-69%, vermelho <40%). Isso ajuda a
identificar rapidamente **quais perguntas a IA acerta bem** e **quais
precisam de atenção** — seja ajustando o prompt, seja revendo se a
pergunta está redigida de um jeito difícil para o modelo entender.

**Por processo**: mesma lógica, agrupado por processo — útil se algum
processo específico (ex: com documentos de qualidade de OCR ruim) estiver
puxando a taxa de acerto geral para baixo.

## Passo a passo

```bash
php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder
```

(seguro rodar de novo — só adiciona a permissão nova, não duplica nada)

Depois, acesse `/metricas` logado como admin ou auditor.

## Sobre o link do Template Excel

Como você notou, o link "Template Excel" tinha sumido do menu desde o
pacote da Fase 3 — a rota e a tela nunca deixaram de existir, só o link
foi perdido numa montagem de pacote anterior. Já incluí a correção nesse
mesmo `layouts/app.blade.php`, então aplicar este pacote resolve os dois
problemas de uma vez.
