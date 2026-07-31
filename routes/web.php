<?php

use App\Http\Controllers\Admin\AiConfigController;
use App\Http\Controllers\Admin\DropboxConnectionController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditProcessController;
use App\Http\Controllers\AuditQuestionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DropboxBrowseController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\OutputFileController;
use App\Http\Controllers\ProcessAnswerController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('processes.index'));

Route::middleware('auth')->get('/metricas', [MetricsController::class, 'index'])->name('metricas.index');

// Autenticação
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Minha conta (ponto 1) e troca obrigatória de senha (ponto 2)
Route::middleware('auth')->group(function () {
    Route::get('/minha-conta', [AccountController::class, 'editar'])->name('conta.editar');
    Route::put('/minha-conta/senha', [AccountController::class, 'atualizarSenha'])->name('conta.senha');

    Route::get('/trocar-senha', [AccountController::class, 'editarForcado'])->name('senha.forcar.editar');
    Route::put('/trocar-senha', [AccountController::class, 'atualizarSenhaForcado'])->name('senha.forcar.atualizar');
});

// Processos de auditoria
Route::middleware('auth')->group(function () {
    Route::get('/processos', [AuditProcessController::class, 'index'])->name('processes.index');
    Route::get('/processos/novo', [AuditProcessController::class, 'create'])->name('processes.create');
    Route::post('/processos', [AuditProcessController::class, 'store'])->name('processes.store');
    Route::get('/processos/{process}', [AuditProcessController::class, 'show'])->name('processes.show');
    Route::get('/processos/{process}/editar', [AuditProcessController::class, 'edit'])->name('processes.edit');
    Route::put('/processos/{process}', [AuditProcessController::class, 'update'])->name('processes.update');
    Route::post('/processos/{process}/transicionar', [AuditProcessController::class, 'transicionar'])
        ->name('processes.transicionar');
    Route::delete('/processos/{process}', [AuditProcessController::class, 'destroy'])->name('processes.destroy');
    Route::post('/processos/{process}/sincronizar', [AuditProcessController::class, 'sincronizar'])
        ->name('processes.sincronizar');
    Route::get('/dropbox/pastas', [DropboxBrowseController::class, 'pastas'])->name('dropbox.pastas');

    Route::get('/processos/{process}/respostas', [ProcessAnswerController::class, 'edit'])->name('processes.respostas.edit');
    Route::put('/processos/{process}/respostas', [ProcessAnswerController::class, 'update'])->name('processes.respostas.update');
    Route::post('/processos/{process}/respostas/aplicar-nao', [ProcessAnswerController::class, 'aplicarNaoAsPendentes'])->name('processes.respostas.aplicar_nao');

    Route::post('/processos/{process}/excel', [OutputFileController::class, 'gerar'])->name('processes.excel.gerar');
    Route::get('/processos/{process}/excel/{outputFile}', [OutputFileController::class, 'download'])->name('processes.excel.download');
    Route::post('/processos/{process}/evidencias/{evidence}/reprocessar', [EvidenceController::class, 'reprocessar'])->name('evidences.reprocessar');
    Route::post('/processos/{process}/matching', [AuditProcessController::class, 'rodarMatching'])->name('processes.matching');
});

// Perguntas de auditoria (admin)
Route::middleware('auth')->prefix('perguntas')->name('questions.')->group(function () {
    Route::get('/', [AuditQuestionController::class, 'index'])->name('index');
    Route::get('/nova', [AuditQuestionController::class, 'create'])->name('create');
    Route::post('/', [AuditQuestionController::class, 'store'])->name('store');
    Route::get('/{question}/editar', [AuditQuestionController::class, 'edit'])->name('edit');
    Route::put('/{question}', [AuditQuestionController::class, 'update'])->name('update');
    Route::post('/{question}/duplicar', [AuditQuestionController::class, 'duplicar'])->name('duplicar');
    Route::delete('/{question}', [AuditQuestionController::class, 'destroy'])->name('destroy');
});

// Conexão com o Dropbox (admin)
Route::middleware('auth')->prefix('admin/dropbox')->name('admin.dropbox.')->group(function () {
    Route::get('/', [DropboxConnectionController::class, 'index'])->name('index');
    Route::get('/conectar', [DropboxConnectionController::class, 'conectar'])->name('conectar');
    Route::get('/callback', [DropboxConnectionController::class, 'callback'])->name('callback');
    Route::delete('/', [DropboxConnectionController::class, 'desconectar'])->name('desconectar');
});

// Configuração da IA (admin)
Route::middleware('auth')->prefix('admin/ia')->name('admin.ia.')->group(function () {
    Route::get('/', [AiConfigController::class, 'index'])->name('index');
    Route::put('/', [AiConfigController::class, 'update'])->name('update');
});

// Template fixo do Excel de auditoria (admin)
Route::middleware('auth')->prefix('admin/template')->name('admin.template.')->group(function () {
    Route::get('/', [TemplateController::class, 'index'])->name('index');
    Route::post('/', [TemplateController::class, 'store'])->name('store');
});

// Administração de usuários
Route::middleware('auth')->prefix('admin/usuarios')->name('admin.users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/novo', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
});
