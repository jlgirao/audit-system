# Troca de texto: "Processo" → "Projeto" (só texto visível)

Baseado nos arquivos **reais** que você enviou (`sistema-auditoria.zip`),
não numa reconstrução de memória — por isso esse pacote pode ser
aplicado com confiança.

## Arquivos alterados (12 no total)

```
app/Http/Controllers/AuditProcessController.php
resources/views/layouts/app.blade.php
resources/views/admin/dropbox/index.blade.php
resources/views/admin/ia/index.blade.php
resources/views/metricas/index.blade.php
resources/views/processes/create.blade.php
resources/views/processes/index.blade.php
resources/views/processes/edit.blade.php
resources/views/processes/excluidos.blade.php
resources/views/processes/show.blade.php
resources/views/processes/respostas.blade.php
resources/views/processes/_dropbox_picker.blade.php
```

Sem migration, sem rota nova.

## O que foi mantido de propósito (não mudou)

- **Rotas**: `processes.index`, `processes.show`, etc. — continuam
  `/processos/...` na URL.
- **Nomes de permissão**: `criar-processo`, `excluir-processo`,
  `ver-todos-processos` — continuam iguais no banco/`@can()`.
- **Nomes de variável**: `$processo`, `$process`, `$porProcesso` — só
  identificadores internos do código, não aparecem para o usuário.
- **Nome de coluna**: `papel_no_processo` (tabela `process_assignments`)
  — é estrutura de banco, mudar isso seria uma migration, não um texto.
- **Comentários internos do código** (`// ...`, `/** ... */`) — não são
  vistos pelo usuário, deixei como estavam para não aumentar o risco à
  toa.

## O que mudou (lista completa)

**Menu e telas gerais**
- Link "Processos" no menu → "Projetos"
- "Processos de auditoria" (título e cabeçalho) → "Projetos de auditoria"
- "+ Novo processo" → "+ Novo projeto"
- "Nenhum processo encontrado." → "Nenhum projeto encontrado."
- "todos os processos" / "somente os meus processos" → "...projetos"

**Criar/editar**
- "Novo processo" / "Editar processo" → "Novo projeto" / "Editar projeto"
- "Nome do processo" → "Nome do projeto"
- "Criar processo" → "Criar projeto"
- Placeholder de exemplo `/Auditorias/Processo_2026_001` →
  `/Auditorias/Projeto_2026_001`

**Excluir/restaurar**
- "Excluir processo" (botão e confirmação) → "Excluir projeto"
- "Processos excluídos" (título, texto explicativo) → "Projetos excluídos"
- "Restaurar este processo?" → "Restaurar este projeto?"
- "Nenhum processo excluído." → "Nenhum projeto excluído."

**Tela do processo**
- "Este processo tem tarefas em segundo plano..." → "Este projeto tem..."
- "outros processos na frente" → "outros projetos na frente"
- "Você não tem permissão para alterar o status deste processo." →
  "...deste projeto."

**Métricas**
- "Acompanhamento por processo" → "Acompanhamento por projeto"
- "Por processo" (segunda tabela) → "Por projeto"
- Cabeçalho de coluna "Processo" (2x) → "Projeto"
- "Nenhum processo cadastrado ainda." → "Nenhum projeto cadastrado ainda."

**Dropbox**
- Aviso de desconexão: "...todos os processos vai parar" → "...todos os
  projetos vai parar"

**Painel de IA (`/admin/ia`)**
- Texto explicativo mencionando "processos"/"processo" → "projetos"/"projeto"

**Respostas**
- "Aplica em todas as perguntas do processo" → "...do projeto"

**Mensagens do controller (as que aparecem na tela após uma ação)**
- "Processo criado com sucesso." → "Projeto criado com sucesso."
- "Processo atualizado com sucesso." → "Projeto atualizado com sucesso."
- "Processo excluído com sucesso." → "Projeto excluído com sucesso."
- "Processo restaurado com sucesso." → "Projeto restaurado com sucesso."
- Comentário do histórico de status "Processo criado." → "Projeto criado."
- Nome do arquivo exportado: `processos.xlsx` → `projetos.xlsx`

## Teste

1. Percorra as telas principais (`/processos`, criar, editar, excluir,
   restaurar, `/metricas`, `/admin/dropbox`, `/admin/ia`) e confirme
   visualmente que tudo mudou para "Projeto"/"Projetos".
2. Confirme que **os links continuam funcionando** normalmente (as
   URLs `/processos/...` não mudaram, só o texto visível).
3. Exporte a listagem em Excel e confirme que o arquivo baixado se
   chama `projetos.xlsx`.
