# Sistema de Auditoria — Esqueleto Fase 0

Este pacote contém os arquivos **específicos da aplicação** (banco de dados,
models, controllers, rotas, views) do sistema de automação de auditoria,
cobrindo a Fase 0 do roadmap: autenticação, perfis de usuário (com suporte
a múltiplos perfis por pessoa), e CRUD de processos e perguntas.

Ele **não inclui o framework Laravel em si** (isso vem via Composer) —
o passo a passo abaixo mostra como juntar as duas coisas no seu servidor.

## Pré-requisitos no servidor

- PHP 8.2 ou superior
- Composer
- MySQL 8
- Extensões PHP: mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, bcmath

## Passo a passo de instalação

### 1. Criar o projeto Laravel base

```bash
composer create-project laravel/laravel sistema-auditoria "^11.0"
cd sistema-auditoria
```

### 2. Instalar o pacote de perfis (Spatie Permission)

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

Isso vai gerar as migrations de `roles`, `permissions` e tabelas de
associação — necessárias para o sistema de perfis funcionar.

### 3. Copiar os arquivos deste pacote por cima do projeto

Copie estas pastas/arquivos do pacote **sobre** a estrutura do projeto Laravel
recém-criado (substituindo onde já existir):

```
app/Models/               → sistema-auditoria/app/Models/
app/Http/Controllers/     → sistema-auditoria/app/Http/Controllers/
database/migrations/      → sistema-auditoria/database/migrations/
database/seeders/          → sistema-auditoria/database/seeders/
routes/web.php              → sistema-auditoria/routes/web.php
resources/views/            → sistema-auditoria/resources/views/
```

Exemplo com `rsync` (rodando a partir da pasta deste pacote):

```bash
rsync -av app/ ../sistema-auditoria/app/
rsync -av database/ ../sistema-auditoria/database/
rsync -av routes/ ../sistema-auditoria/routes/
rsync -av resources/ ../sistema-auditoria/resources/
```

### 4. Configurar o ambiente

```bash
cd sistema-auditoria
cp .env.example .env      # use o .env.example deste pacote (já ajustado)
php artisan key:generate
```

Edite o `.env` com os dados do seu MySQL (`DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`).

### 5. Rodar as migrations e os seeders

```bash
php artisan migrate
php artisan db:seed
```

Isso cria todas as tabelas do sistema, os perfis (admin, analista, auditor)
com suas permissões, um **usuário administrador inicial**
(`admin@empresa.com` / `trocar-esta-senha` — troque a senha assim que
entrar), e 3 perguntas de exemplo (substitua pelo conteúdo real do seu
Excel de auditoria, editando `database/seeders/AuditQuestionsSeeder.php`
ou cadastrando pela tela).

### 6. Subir o servidor

Em desenvolvimento/teste:

```bash
php artisan serve
```

Em produção on-premises, aponte o document root do Apache/Nginx para a
pasta `public/` do projeto (configuração padrão de qualquer app Laravel).

### 7. Acessar o sistema

Acesse a URL configurada, faça login com o usuário admin, e a partir daí:

1. Cadastre os demais usuários (`/admin/usuarios`) e atribua os perfis —
   lembrando que um mesmo usuário pode ter mais de um perfil (ex: analista
   e auditor ao mesmo tempo).
2. Cadastre/revise as perguntas fixas de auditoria (`/perguntas`).
3. Crie processos de auditoria (`/processos/novo`).

## O que está incluído nesta fase

- Autenticação (login/logout)
- Perfis via Spatie Permission, com suporte nativo a múltiplos perfis por
  usuário
- CRUD de perguntas de auditoria (mapeamento aba/linha/coluna do Excel)
- CRUD de processos de auditoria, com UUID único gerado automaticamente
- Atribuição de múltiplos responsáveis por processo
- Máquina de estados do processo com histórico de transições
  (criado → em análise → em revisão → devolvido/aprovado → concluído →
  reaberto)
- Gestão de usuários e perfis pelo admin

## O que ainda NÃO está incluído (próximas fases)

- Integração com Dropbox (sincronização de evidências)
- Extração de texto (PDF/DOC/XLSX) e OCR
- Geração do Excel de saída
- Matching por IA (embeddings + LLM local via Ollama)
- Notificações por e-mail

Essas entram nas próximas fases do roadmap, construídas sobre esta base.

## Observação de segurança

Antes de ir para produção:
- Troque a senha do usuário admin padrão
- Defina `APP_DEBUG=false` no `.env` (já vem assim por padrão neste pacote)
- Configure HTTPS no seu servidor web
