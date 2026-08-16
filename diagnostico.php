<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EvidenceFile;
use App\Models\AuditQuestion;
use App\Services\VectorMath;

$termoBusca = 'MEMO_Controlo';
$codigoPergunta = 'J-024';

$evidencias = EvidenceFile::where('nome_arquivo', 'like', "%{$termoBusca}%")->get();

echo "Evidências encontradas:\n";
foreach ($evidencias as $e) {
    $tem = $e->embedding_vector ? 'sim' : 'nao';
    echo "  ID {$e->id} | processo {$e->process_id} | embedding: {$tem} | {$e->nome_arquivo}\n";
}

$pergunta = AuditQuestion::where('codigo', $codigoPergunta)->first();

if (! $pergunta) {
    echo "Pergunta {$codigoPergunta} não encontrada.\n";
    exit;
}

foreach ($evidencias as $e) {
    if (! $e->embedding_vector) {
        continue;
    }
    $sim = VectorMath::similaridadeCosseno($e->embedding_vector, $pergunta->embedding_vector);
    echo "Similaridade {$codigoPergunta} x evidência {$e->id} ({$e->nome_arquivo}): ".round($sim, 3)."\n";
}