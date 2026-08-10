# Guia de Deploy em Produção — Sistema de Auditoria

Assumindo **Ubuntu 22.04/24.04** (ajuste os comandos se for outra
distro — CentOS/AlmaLinux usam `dnf` em vez de `apt`, por exemplo).
Onde eu precisar que você preencha algo, uso `SEU_DOMINIO`,
`SEU_IP`, etc.

---

## 1. Preparar o servidor

### 1.1 Atualizar o sistema
```bash
sudo apt update && sudo apt upgrade -y
```

### 1.2 PHP 8.2+ e extensões
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl
```

### 1.3 Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 1.4 MySQL 8
```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Criar o banco e usuário:
```bash
sudo mysql -u root -p
```
```sql
CREATE DATABASE auditoria_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'auditoria_user'@'localhost' IDENTIFIED BY 'SENHA_FORTE_AQUI';
GRANT ALL PRIVILEGES ON auditoria_db.* TO 'auditoria_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 1.5 Nginx
```bash
sudo apt install -y nginx
```

### 1.6 Tesseract OCR + Ghostscript
No Linux isso é bem mais simples que no Windows — sem caminho completo,
sem `.exe`, sem escolher a versão "console":
```bash
sudo apt install -y tesseract-ocr tesseract-ocr-por ghostscript
```
Teste:
```bash
tesseract --version
gs --version
```

### 1.7 Ollama
```bash
curl -fsSL https://ollama.com/install.sh | sh
ollama pull nomic-embed-text
ollama pull qwen2.5:7b
```
Por padrão, o Ollama escuta só em `127.0.0.1:11434` (não exposto à
internet) — é assim que queremos, não precisa mudar nada.

---

## 2. Levar o código para o servidor

Como o projeto já está com vários ajustes aplicados no Windows, o mais
simples é empacotar a pasta inteira do projeto (**sem** `vendor/`,
`node_modules/`, e sem `storage/logs/*`) e enviar via `scp`/`rsync`.

No Windows (PowerShell), a partir da pasta do projeto:
```powershell
Compress-Archive -Path * -DestinationPath ..\projeto.zip -Force
```
(exclua manualmente a pasta `vendor` antes de compactar, ou descompacte
e rode `composer install` de novo no servidor — mais simples: não inclua
`vendor` no zip)

Enviar para o servidor:
```bash
scp projeto.zip usuario@SEU_IP:/tmp/
```

No servidor:
```bash
sudo mkdir -p /var/www/sistema-auditoria
cd /var/www/sistema-auditoria
sudo unzip /tmp/projeto.zip
sudo chown -R $USER:$USER /var/www/sistema-auditoria
composer install --no-dev --optimize-autoloader
```

---

## 3. Configurar o `.env` de produção

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Ajustes principais:
```env
APP_NAME="Sistema de Auditoria"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://SEU_DOMINIO

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=auditoria_db
DB_USERNAME=auditoria_user
DB_PASSWORD=SENHA_FORTE_AQUI

SESSION_DRIVER=database
QUEUE_CONNECTION=database

DROPBOX_APP_KEY=sua_chave
DROPBOX_APP_SECRET=seu_secret

TESSERACT_BINARY=tesseract
GHOSTSCRIPT_BINARY=gs
OCR_IDIOMA=por
OCR_DPI=300
```

**Atenção**: como você rodou `key:generate` de novo, uma **chave nova**
foi gerada — isso significa que qualquer coisa criptografada com a
chave antiga (os tokens do Dropbox salvos no banco) não vai mais
descriptografar. Não tem problema, porque o banco também está sendo
recriado do zero (próximo passo) — só é importante saber que o Dropbox
vai precisar ser reconectado depois.

---

## 4. Banco de dados

```bash
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder
```

**Não rode** o `AuditQuestionsSeeder` (ele só tem perguntas de exemplo).
Para trazer as perguntas reais que vocês já cadastraram no ambiente de
teste, exporte só os dados dessa tabela no XAMPP:
```bash
mysqldump -u root db_sistema_auditoria audit_questions --no-create-info --skip-triggers > perguntas.sql
```
Envie o arquivo para o servidor e importe:
```bash
mysql -u auditoria_user -p auditoria_db < perguntas.sql
```

Crie o usuário admin de produção (ajuste e-mail/senha):
```bash
php artisan tinker --execute="App\Models\User::create(['nome'=>'Administrador','email'=>'admin@suaempresa.com','senha_hash'=>Hash::make('SENHA_TEMPORARIA'),'ativo'=>true,'deve_alterar_senha'=>true])->assignRole('admin');"
```

---

## 5. Permissões de arquivo

```bash
sudo chown -R www-data:www-data /var/www/sistema-auditoria/storage
sudo chown -R www-data:www-data /var/www/sistema-auditoria/bootstrap/cache
sudo chmod -R 775 /var/www/sistema-auditoria/storage
sudo chmod -R 775 /var/www/sistema-auditoria/bootstrap/cache
```

---

## 6. Nginx — configuração do site

```bash
sudo nano /etc/nginx/sites-available/sistema-auditoria
```
```nginx
server {
    listen 80;
    server_name SEU_DOMINIO;
    root /var/www/sistema-auditoria/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 50M;
}
```
```bash
sudo ln -s /etc/nginx/sites-available/sistema-auditoria /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 7. DNS e HTTPS

1. No painel do seu provedor de DNS, crie um registro **A** apontando
   `SEU_DOMINIO` para o IP do servidor. Aguarde propagar (minutos a
   algumas horas).
2. Instale o Certbot:
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d SEU_DOMINIO
```
Isso já configura o HTTPS e o redirecionamento automático de HTTP para
HTTPS no Nginx. O Certbot renova sozinho (via cron/systemd timer
instalado junto).

---

## 8. Worker de fila com Supervisor (essencial — sem isso nada assíncrono funciona)

```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/auditoria-worker.conf
```
```ini
[program:auditoria-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sistema-auditoria/artisan queue:work --tries=1 --timeout=300
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/sistema-auditoria/storage/logs/worker.log
stopwaitsecs=3600
```
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start auditoria-worker:*
```

`numprocs=2` já sobe 2 workers em paralelo — ajuste conforme os núcleos
disponíveis no servidor.

---

## 9. Cron para a sincronização/desbloqueio agendados

```bash
sudo crontab -u www-data -e
```
Adicione:
```
* * * * * cd /var/www/sistema-auditoria && php artisan schedule:run >> /dev/null 2>&1
```

---

## 10. Reconectar Dropbox e reconfigurar IA

1. No **App Console do Dropbox**, atualize o Redirect URI para:
   ```
   https://SEU_DOMINIO/admin/dropbox/callback
   ```
2. Acesse `https://SEU_DOMINIO/admin/dropbox` e clique em "Conectar ao
   Dropbox".
3. Acesse `/admin/ia` e reconfigure (esses dados também estavam só no
   banco do XAMPP, não vêm junto no código):
   - Endpoint: `http://localhost:11434`
   - Modelo de embedding: `nomic-embed-text`
   - Modelo de matching: `qwen2.5:7b`
   - Limiar: `0.55` (ou o que estiver usando)
   - `max_candidatos_por_aba`: `2`
   - `max_candidatos_por_evidencia`: `20`
   - **Copie o prompt customizado** que vocês já ajustaram (com
     `{contexto}` incluído) — ele também só existia no banco de teste.
4. Acesse `/admin/template` e suba de novo o `.xlsx` do template.

---

## 11. Firewall básico

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```
Isso libera só SSH (22), HTTP (80) e HTTPS (443) — a porta do Ollama
(11434) **não** deve ser liberada externamente (ela já só escuta em
localhost por padrão, então nem precisa bloquear explicitamente, mas
não libere por engano).

---

## 12. Teste final (smoke test)

1. Acesse `https://SEU_DOMINIO` — deve cair na tela de login, com
   cadeado (HTTPS) no navegador.
2. Login com o admin criado no passo 4.
3. Confirme que o Dropbox está conectado.
4. Crie ou abra um processo pequeno e sincronize.
5. Acompanhe `sudo tail -f /var/www/sistema-auditoria/storage/logs/worker.log`
   para ver os jobs rodando.
6. Rode o matching de IA num processo pequeno antes de partir para o
   teste de carga grande — confirma que a stack toda funciona no
   servidor novo antes de comprometer horas de processamento nele.

---

## O que ainda fica pendente (não é bloqueante para começar a testar)

- **Backup automático** do banco (`mysqldump` agendado) — recomendo
  configurar antes de colocar em uso real de verdade, mas não impede o
  teste de performance.
- **Backup do `.env`/chave de aplicação** em local seguro fora do
  servidor.
- Monitoramento de uso de CPU/RAM do servidor a médio prazo (para
  decidir se separar o Ollama numa máquina própria compensa, como
  discutimos lá no planejamento).
