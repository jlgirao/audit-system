# Ajustes — Pontos 1 a 6

Este pacote contém os arquivos **novos ou alterados** para atender aos 6
pontos que você levantou. Ele parte da estrutura que você já ajustou para
Laravel 11 / PHP 8.4 (padrão `HasMiddleware`, `bootstrap/app.php` sem
`Kernel.php`), então mantive o mesmo estilo.

## Como aplicar

### 1. Copiar os arquivos por cima do projeto

```
app/Models/User.php                          → substitui
app/Models/AuditProcess.php                   → substitui
app/Http/Controllers/AccountController.php    → NOVO
app/Http/Controllers/AuditProcessController.php → substitui
app/Http/Controllers/UserController.php       → substitui (fica em app/Http/Controllers/Admin/)
app/Http/Middleware/ForcarTrocaSenha.php       → NOVO
bootstrap/app.php                              → substitui
routes/web.php                                 → substitui
database/migrations/*.php                      → adiciona (2 arquivos novos)
database/seeders/RolesAndPermissionsSeeder.php → substitui
database/seeders/AdminUserSeeder.php           → substitui
resources/views/layouts/app.blade.php          → substitui
resources/views/account/editar.blade.php       → NOVO
resources/views/processes/*.blade.php          → substitui (index, create, show) + NOVO (edit)
resources/views/admin/users/edit.blade.php     → substitui
```

Atenção ao `UserController.php`: neste pacote ele está na raiz por
simplicidade de envio, mas o namespace do arquivo é `App\Http\Controllers\Admin`
— ele deve ir para `app/Http/Controllers/Admin/UserController.php`, como já
estava no seu projeto.

### 2. Rodar as migrations novas

```bash
php artisan migrate
```

Isso adiciona a coluna `deve_alterar_senha` em `users` e `descricao` em
`audit_processes`, sem alterar nada que já existia.

### 3. Rodar o seeder de permissões novamente

```bash
php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder
```

Isso cria a nova permissão `concluir-processo` e reatribui as permissões
de cada perfil (usa `firstOrCreate`/`syncPermissions`, então é seguro rodar
de novo sem duplicar nada nem apagar usuários existentes).

### 4. Usuários já existentes

Se você já tinha usuários cadastrados antes deste ajuste, o admin
provavelmente não está com `deve_alterar_senha = true` retroativamente —
isso é esperado (a coluna nasce com `false` por padrão). Se quiser forçar a
troca de senha de alguém já existente, basta abrir a edição do usuário e
marcar "Forçar troca de senha no próximo login".

## O que foi implementado, ponto a ponto

**1. Usuário altera a própria senha**
Tela nova em `/minha-conta` (`AccountController::editar` /
`atualizarSenha`), exige a senha atual antes de trocar. Link adicionado no
cabeçalho (nome do usuário agora é clicável).

**2. Admin altera senha de outros e/ou força troca no próximo login**
No formulário de edição de usuário (`admin/users/edit.blade.php`), dois
campos novos e independentes:
- "Nova senha" (opcional) — se preenchido, já troca a senha.
- "Forçar troca de senha no próximo login" — marca a flag
  `deve_alterar_senha`, mesmo sem definir uma nova senha agora.

Um middleware global (`ForcarTrocaSenha`, registrado em
`bootstrap/app.php`) intercepta **qualquer** requisição de um usuário
autenticado com essa flag ativa e redireciona para `/trocar-senha` — uma
tela dedicada que não pede senha atual (o usuário pode não saber a senha
temporária que o admin definiu, do ponto de vista de já estar logado via
sessão). Usuários novos (`UserController::store`) já nascem com essa flag
ativa.

**3. Admin vê todos os processos**
A permissão `ver-todos-processos` (que o admin já tinha por ter todas as
permissões) agora faz a listagem mostrar todos os processos **por
padrão**, sem precisar de parâmetro na URL — antes era o contrário
(precisava passar `?todos=1`). Quem tem essa permissão pode alternar para
"ver só os meus" com um link na tela.

**4. Descrição e responsáveis editáveis**
Novo campo `descricao` no processo (migration + model + telas de criar e
editar). Nova rota/tela `/processos/{id}/editar` que permite reatribuir
completamente a lista de responsáveis. Autorização em
`AuditProcess::podeSerEditadoPor()`: admin sempre pode; qualquer outro
usuário só pode editar processos onde ele é um dos responsáveis
atribuídos ("o seu").

**5 e 6. Transições de status controladas por permissão**
Centralizei a regra em `AuditProcess::statusDisponiveisPara()` e
`AuditProcess::PERMISSAO_POR_STATUS`:
- `devolvido` exige `revisar-processo`
- `aprovado` exige `aprovar-processo`
- `concluido` exige `concluir-processo` (permissão nova)
- `reaberto` exige `reabrir-processo`
- `em_analise`/`em_revisao` ficam livres para qualquer responsável
  atribuído ao processo (ou admin)

Como o perfil `analista` não tem `aprovar-processo` nem
`concluir-processo` (ver seeder), essas opções **não aparecem** no
`<select>` da tela para ele (ponto 6 — "não deve nem ficar disponível") —
e mesmo que alguém tentasse forçar via requisição direta, o controller
revalida a mesma lista de permissões no servidor antes de aplicar a
transição. Como o admin tem todas as permissões, ele sempre vê e pode
usar qualquer status (ponto 5).

## Ponto em aberto para vocês decidirem

Ficou definido que **auditor** pode aprovar e concluir. Não ficou claro
se ele também deveria poder **devolver para reabertura** sem ser admin —
deixei do jeito que já estava (auditor tem `reabrir-processo`), mas é só
uma linha no seeder para mudar se vocês quiserem restringir isso só ao
admin.
