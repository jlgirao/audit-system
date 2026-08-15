# Guia de Deploy em Produção — Sistema de Auditoria (AlmaLinux 9)

Assumindo **AlmaLinux 9** com acesso root/sudo. Onde precisar que você
preencha algo, uso `SEU_DOMINIO`, `SEU_IP`, etc.

> **Diferenças importantes em relação ao Ubuntu** (se você já tinha visto
> a versão anterior deste guia): gerenciador de pacotes é `dnf`, o
> PHP 8.3 não vem nos repositórios padrão (precisa do repositório Remi),
> o **SELinux vem ativo por padrão** (e precisa de configuração
> específica, ou a aplicação simplesmente não funciona com erros
> confusos de permissão), e o usuário padrão do PHP-FPM é diferente do
> usuário do Nginx — tratei os três pontos abaixo.

---

## 1. Preparar o servidor

### 1.1 Atualizar o sistema
```bash
sudo dnf update -y
```

### 1.2 Repositórios necessários (EPEL + Remi, para PHP 8.3)

O AlmaLinux não traz PHP 8.3 nos repositórios padrão — precisa do
repositório Remi:
```bash
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.3 -y
```

### 1.3 PHP 8.3 e extensões
```bash
sudo dnf install -y php php-fpm php-cli php-mysqlnd php-mbstring \
    php-xml php-curl php-zip php-bcmath php-gd php-intl php-common
```

### 1.4 Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 1.5 MySQL 8

O módulo `mysql` do AppStream do AlmaLinux 9 já é MySQL 8.0 (não
precisa de repositório externo):
```bash
sudo dnf install -y mysql-server
sudo systemctl enable --now mysqld
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

### 1.6 Nginx
```bash
sudo dnf install -y nginx
sudo systemctl enable nginx
```

### 1.7 Tesseract OCR + Ghostscript
```bash
sudo dnf install -y tesseract tesseract-langpack-por ghostscript
```
Teste:
```bash
tesseract --version
gs --version
```
Se `tesseract-langpack-por` não for encontrado, procure o nome exato
disponível:
```bash
dnf search tesseract
```

### 1.8 Ollama
```bash
curl -fsSL https://ollama.com/install.sh | sh
ollama pull nomic-embed-text
ollama pull qwen2.5:7b
```
Continua escutando só em `127.0.0.1:11434` por padrão — não precisa
mexer em nada aqui.

---

## 2. Ajustar o usuário do PHP-FPM (importante)

Por padrão, o PHP-FPM instalado via Remi roda como usuário **`apache`**,
mas o Nginx no AlmaLinux roda como usuário **`nginx`** — são usuários
diferentes, o que causa problemas de permissão de arquivo entre os dois.
O mais simples é fazer o PHP-FPM rodar como `nginx` também:

```bash
sudo nano /etc/php-fpm.d/www.conf
```
Altere as linhas:
```ini
user = nginx
group = nginx
```
(estavam como `apache` antes)

```bash
sudo systemctl enable --now php-fpm
sudo systemctl restart php-fpm
```

---

## 3. Levar o código para o servidor

Empacote o projeto (sem `vendor/`, sem `storage/logs/*`) e envie:
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

## 4. Configurar o `.env` de produção

```bash
cp .env.example .env
php artisan key:generate
nano .env
```
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

Como você rodou `key:generate` de novo, o Dropbox vai precisar ser
reconectado depois (a chave antiga não descriptografa mais os tokens
salvos — mas o banco também está sendo recriado do zero, então não tem
nada para descriptografar mesmo).

---

## 5. Banco de dados

```bash
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder
```

**Não rode** o `AuditQuestionsSeeder` (só tem perguntas de exemplo).
Traga as perguntas reais via `mysqldump` do ambiente de teste:
```bash
mysqldump -u root db_sistema_auditoria audit_questions --no-create-info --skip-triggers > perguntas.sql
mysql -u auditoria_user -p auditoria_db < perguntas.sql
```

Criar o admin de produção:
```bash
php artisan tinker --execute="App\Models\User::create(['nome'=>'Administrador','email'=>'admin@suaempresa.com','senha_hash'=>Hash::make('SENHA_TEMPORARIA'),'ativo'=>true,'deve_alterar_senha'=>true])->assignRole('admin');"
```

---

## 6. Permissões de arquivo (usuário `nginx`, já que ajustamos o PHP-FPM para ele)

```bash
sudo chown -R nginx:nginx /var/www/sistema-auditoria/storage
sudo chown -R nginx:nginx /var/www/sistema-auditoria/bootstrap/cache
sudo chmod -R 775 /var/www/sistema-auditoria/storage
sudo chmod -R 775 /var/www/sistema-auditoria/bootstrap/cache
```

---

## 7. SELinux (⚠️ passo que não existe no Ubuntu — não pule)

O AlmaLinux vem com SELinux **ativo por padrão** (modo enforcing). Sem
os ajustes abaixo, o Nginx/PHP-FPM recebe "Permission denied" mesmo com
as permissões de arquivo corretas — é uma camada de segurança adicional
do SELinux, separada das permissões normais do Linux.

### 7.1 Instalar as ferramentas de gerenciamento do SELinux
```bash
sudo dnf install -y policycoreutils-python-utils
```

### 7.2 Liberar rede de saída para o Nginx/PHP (necessário para chamadas ao Dropbox e ao Ollama)
```bash
sudo setsebool -P httpd_can_network_connect on
```

### 7.3 Marcar a pasta `storage` e `bootstrap/cache` como graváveis para o SELinux
```bash
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/sistema-auditoria/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/sistema-auditoria/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/sistema-auditoria
```

### 7.4 Confirmar (se algo não funcionar depois, comece verificando aqui)
```bash
sudo ausearch -m avc -ts recent
```
Esse comando mostra qualquer bloqueio do SELinux registrado recentemente
— se aparecer algo relacionado à pasta do projeto, geralmente dá para
resolver com `semanage fcontext` apontando para o caminho específico
que apareceu no erro.

---

## 8. Nginx — configuração do site

```bash
sudo nano /etc/nginx/conf.d/sistema-auditoria.conf
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
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 50M;
}
```
> Confirme o caminho do socket do PHP-FPM em `/etc/php-fpm.d/www.conf`
> (procure `listen = `) — no AlmaLinux/Remi costuma ser
> `/run/php-fpm/www.sock`, mas vale conferir.

```bash
sudo nginx -t
sudo systemctl restart nginx
```

---

## 9. DNS e HTTPS

1. Crie um registro **A** apontando `SEU_DOMINIO` para o IP do
   servidor.
2. Instale o Certbot:
```bash
sudo dnf install -y certbot python3-certbot-nginx
sudo certbot --nginx -d SEU_DOMINIO
```

---

## 10. Worker de fila com Supervisor

```bash
sudo dnf install -y supervisor
sudo systemctl enable --now supervisord
```

> No AlmaLinux/EPEL, os arquivos de configuração de cada programa ficam
> em `/etc/supervisord.d/*.ini` (não `/etc/supervisor/conf.d/*.conf`
> como no Ubuntu — repare na extensão `.ini` e no nome do serviço
> `supervisord`, com "d" no final).

```bash
sudo nano /etc/supervisord.d/auditoria-worker.ini
```
```ini
[program:auditoria-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sistema-auditoria/artisan queue:work --tries=1 --timeout=300
autostart=true
autorestart=true
user=nginx
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

---

## 11. Cron para a sincronização/desbloqueio agendados

```bash
sudo dnf install -y cronie
sudo systemctl enable --now crond
sudo crontab -u nginx -e
```
Adicione:
```
* * * * * cd /var/www/sistema-auditoria && php artisan schedule:run >> /dev/null 2>&1
```

---

## 12. Reconectar Dropbox e reconfigurar IA

Igual ao Ubuntu, sem diferença aqui:

1. Atualize o Redirect URI no App Console do Dropbox para
   `https://SEU_DOMINIO/admin/dropbox/callback`.
2. Acesse `/admin/dropbox` e conecte.
3. Acesse `/admin/ia` e reconfigure endpoint, modelos, limiar,
   `max_candidatos_por_aba`/`max_candidatos_por_evidencia`, e **copie o
   prompt customizado** (com `{contexto}`) do ambiente de teste.
4. Acesse `/admin/template` e suba o `.xlsx` do template.

---

## 13. Firewall (firewalld, não ufw)

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --reload
```

Confirme o que está liberado:
```bash
sudo firewall-cmd --list-all
```

A porta do Ollama (11434) não deve aparecer nessa lista — ela só escuta
em localhost por padrão, não precisa (e não deve) ser liberada.

---

## 14. Teste final (smoke test)

1. Acesse `https://SEU_DOMINIO` — tela de login com cadeado.
2. Login com o admin criado no passo 5.
3. Confirme Dropbox conectado.
4. Sincronize um processo pequeno.
5. Acompanhe:
   ```bash
   sudo tail -f /var/www/sistema-auditoria/storage/logs/worker.log
   ```
6. Se der erro de permissão em qualquer ponto, **antes de mexer em
   permissões de arquivo de novo**, confira o SELinux primeiro:
   ```bash
   sudo ausearch -m avc -ts recent
   ```
   É a causa mais comum de "funcionava no teste, deu erro estranho em
   produção" especificamente no AlmaLinux/RHEL.
7. Só depois de um processo pequeno funcionar de ponta a ponta, parta
   para o teste de carga grande.

---

## Pendências (não bloqueiam o início dos testes)

- Backup automático do banco (`mysqldump` agendado via cron).
- Backup do `.env`/chave de aplicação em local seguro fora do servidor.
- Monitoramento de CPU/RAM a médio prazo, para decidir se separar o
  Ollama numa máquina própria compensa.
