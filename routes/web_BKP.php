<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditProcessController;
use App\Http\Controllers\AuditQuestionController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('processes.index'));

// Autenticação
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Processos de auditoria
Route::middleware('auth')->group(function () {
    Route::get('/processos', [AuditProcessController::class, 'index'])->name('processes.index');
    Route::get('/processos/novo', [AuditProcessController::class, 'create'])->name('processes.create');
    Route::post('/processos', [AuditProcessController::class, 'store'])->name('processes.store');
    Route::get('/processos/{process}', [AuditProcessController::class, 'show'])->name('processes.show');
    Route::post('/processos/{process}/transicionar', [AuditProcessController::class, 'transicionar'])
        ->name('processes.transicionar');
});

// Perguntas de auditoria (admin)
Route::middleware('auth')->prefix('perguntas')->name('questions.')->group(function () {
    Route::get('/', [AuditQuestionController::class, 'index'])->name('index');
    Route::get('/nova', [AuditQuestionController::class, 'create'])->name('create');
    Route::post('/', [AuditQuestionController::class, 'store'])->name('store');
    Route::get('/{question}/editar', [AuditQuestionController::class, 'edit'])->name('edit');
    Route::put('/{question}', [AuditQuestionController::class, 'update'])->name('update');
    Route::delete('/{question}', [AuditQuestionController::class, 'destroy'])->name('destroy');
});

// Administração de usuários
Route::middleware('auth')->prefix('admin/usuarios')->name('admin.users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/novo', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
});
