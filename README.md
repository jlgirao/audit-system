# Fase 2 — OCR de imagens e PDFs escaneados

Processa automaticamente o que ficava "pendente" desde a Fase 1: imagens
(PNG/JPEG) e PDFs escaneados (sem texto nativo). Sem migration nova — usa
as colunas que já existiam em `evidence_files` desde a Fase 0.

## Arquitetura (por que assim)

- **Tesseract OCR** faz o reconhecimento de texto em si.
- **Ghostscript**, chamado diretamente via linha de comando, converte
  páginas de PDF em imagens PNG antes do OCR — evitamos depender da
  extensão `Imagick` do PHP, que é notoriamente difícil de instalar e
  configurar corretamente no Windows/XAMPP.
- O encadeamento é automático: ao sincronizar, PDFs/DOC/XLS tentam
  extração nativa primeiro (mais rápida); se um PDF não tiver texto
  nativo (documento escaneado), ele **automaticamente** cai para o OCR,
  sem precisar de ação manual. Imagens (PNG/JPEG) vão direto para o OCR.

## Arquivos

```
config/ocr.php                                    → NOVO
app/Services/Ocr/PdfToImageConverter.php          → NOVO
app/Services/Ocr/TesseractRunner.php              → NOVO
app/Jobs/OcrEvidenceJob.php                        → NOVO
app/Jobs/SyncProcessEvidenceJob.php                → substitui
app/Jobs/ExtractEvidenceTextJob.php                → substitui
app/Http/Controllers/EvidenceController.php       → NOVO
routes/web.php                                     → substitui (confira antes, como sempre)
resources/views/processes/show.blade.php           → substitui
```

**Atenção**: o `show.blade.php` usa as classes `.acao-btn` / `.acao-duplicar`
que criamos no ajuste anterior (botões de ícone). Se por algum motivo
vocês não aplicaram aquele pacote, o botão de reprocessar vai aparecer
sem estilo (funciona, mas feio) — vale confirmar que o `layouts/app.blade.php`
já tem aquelas classes.

## 1. Instalar o Tesseract OCR

### Windows

1. Baixe o instalador em: https://github.com/UB-Mannheim/tesseract/wiki
2. Durante a instalação, **marque o pacote de idioma Português** (na
   tela de seleção de componentes, em "Additional language data").
   Se esquecer, dá para baixar depois o arquivo `por.traineddata` em
   https://github.com/tesseract-ocr/tessdata e colocar na pasta
   `tessdata` da instalação (geralmente
   `C:\Program Files\Tesseract-OCR\tessdata`).
3. Anote o caminho do executável, normalmente:
   ```
   C:\Program Files\Tesseract-OCR\tesseract.exe
   ```
4. **Teste direto no terminal antes de testar pela aplicação** (evita
   confundir bug de instalação com bug de código, como já aconteceu
   antes com o certificado SSL e o Ghostscript):
   ```bash
   "C:\Program Files\Tesseract-OCR\tesseract.exe" --version
   ```

### Linux (referência, caso o servidor final seja Linux)

```bash
sudo apt install tesseract-ocr tesseract-ocr-por
```

## 2. Instalar o Ghostscript

### Windows

1. Baixe em: https://www.ghostscript.com/releases/gsdnld.html (versão
   "AGPL", normal para uso interno)
2. Instale normalmente. O executável de linha de comando (o que
   interessa aqui, não o `gswin64.exe` da versão com interface gráfica)
   fica em algo como:
   ```
   C:\Program Files\gs\gs10.03.0\bin\gswin64c.exe
   ```
   (o número da versão muda; **use o que termina em `c.exe`**, é a
   versão "console" — sem essa letra "c" é a versão com janela, que
   trava esperando interação).
3. Teste direto no terminal:
   ```bash
   "C:\Program Files\gs\gs10.03.0\bin\gswin64c.exe" --version
   ```

### Linux (referência)

```bash
sudo apt install ghostscript
```

## 3. Instalar o pacote PHP do Tesseract

```bash
composer require thiagoalessio/tesseract_ocr
```

(Não precisa de mais nada — o Ghostscript é chamado direto via
`Symfony\Process`, que já vem com o Laravel.)

## 4. Configurar o `.env`

```
TESSERACT_BINARY="C:\Program Files\Tesseract-OCR\tesseract.exe"
GHOSTSCRIPT_BINARY="C:\Program Files\gs\gs10.03.0\bin\gswin64c.exe"
OCR_IDIOMA=por
OCR_DPI=300
```

Ajuste os caminhos conforme onde cada programa foi instalado no seu
servidor. Em Linux, geralmente basta:
```
TESSERACT_BINARY=tesseract
GHOSTSCRIPT_BINARY=gs
```

## 5. Copiar os arquivos e limpar cache

```bash
php artisan config:clear
```

## 6. Reiniciar o worker de fila

```bash
php artisan queue:work --tries=1
```

## 7. Testar

1. Sincronize um processo que tenha pelo menos uma imagem (PNG/JPEG) ou
   um PDF escaneado na pasta do Dropbox.
2. Acompanhe o terminal do `queue:work` — deve aparecer o
   `OcrEvidenceJob` rodando (para PDFs escaneados, primeiro roda o
   `ExtractEvidenceTextJob`, que detecta a ausência de texto nativo e
   dispara o OCR sozinho).
3. Atualize a tela do processo — a evidência deve aparecer com status
   "Concluído" e origem do texto "Ocr".

Se der erro, a mensagem na coluna "Observação" da tabela de evidências
já aponta a causa mais provável (Tesseract ou Ghostscript não encontrado,
caminho errado, etc.) — corrija o `.env`, rode `config:clear`, reinicie o
`queue:work`, e clique no botão 🔄 "Reprocessar" que aparece ao lado de
evidências com erro (sem precisar ressincronizar o processo inteiro).

## Sobre qualidade do OCR

- 300 DPI é um bom equilíbrio para a maioria dos documentos escaneados.
  Se a qualidade do texto reconhecido vier ruim (muitos erros de
  caracteres), vale tentar aumentar para 400-600 no `OCR_DPI` — mas isso
  deixa o processamento mais lento e os arquivos temporários maiores.
- Documentos escaneados tortos, com baixa resolução original, ou com
  fundo colorido/marca d'água tendem a dar OCR de qualidade inferior —
  isso é uma limitação do OCR em si, não do código.
