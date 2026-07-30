# Ajuste — Status de sincronização explícito (cobre o tempo na fila)

## O que faltava

O indicador de processamento dependia inteiramente das linhas de
`evidence_files` para saber se algo estava rodando. Mas quando a
sincronização com o Dropbox ainda está **na fila**, esperando a vez
(porque o worker está ocupado com outro processo), não existe nenhuma
linha de evidência ainda — nada para o indicador olhar. Resultado: um
processo recém-criado, com a sincronização enfileirada mas não iniciada,
parecia "parado" quando na verdade só estava esperando a vez.

## Arquivos

```
database/migrations/2024_07_02_000001_add_status_sincronizacao_to_audit_processes_table.php → NOVO
app/Models/AuditProcess.php                          → substitui
app/Jobs/SyncProcessEvidenceJob.php                  → substitui
app/Http/Controllers/AuditProcessController.php     → substitui
app/Console/Commands/SincronizarDropboxCommand.php  → substitui
resources/views/processes/index.blade.php             → substitui
resources/views/processes/show.blade.php               → substitui
```

## O que mudou

Nova coluna `audit_processes.status_sincronizacao`
(`nunca` / `na_fila` / `sincronizando` / `concluido` / `erro`):

- **`na_fila`** é marcado no controller (botão "Sincronizar agora") e no
  comando agendado, **antes** de despachar o job — cobre exatamente o
  período de espera na fila que motivou este ajuste.
- **`sincronizando`** é marcado pelo próprio `SyncProcessEvidenceJob`
  assim que o worker realmente pega o job e começa a rodar.
- **`concluido`** ao terminar com sucesso; **`erro`** se algo falhar
  (a exceção continua sendo relançada depois, então o comportamento
  normal de retry/`failed_jobs` do Laravel não muda).

O banner da tela do processo e o selo da listagem agora também
verificam esse status — um processo na fila aguardando sincronização já
aparece como "⏳ Processando", com uma mensagem específica ("na fila,
aguardando a vez" vs. "sincronizando agora").

## Passo a passo

```bash
php artisan migrate
```

Reinicie o `queue:work`. Para testar bem o cenário da fila, force um
congestionamento: enfileire a sincronização de 2-3 processos ao mesmo
tempo e confirme que os que ainda não começaram aparecem como
"⏳ Processando" com a mensagem "na fila, aguardando a vez", mesmo sem
nenhuma evidência ainda sincronizada.
