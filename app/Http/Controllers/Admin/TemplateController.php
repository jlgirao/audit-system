<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\File;

class TemplateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'permission:gerenciar-perguntas',
        ];
    }

    public function index()
    {
        $caminho = config('auditoria.template_path');

        return view('admin.template.index', [
            'existe' => file_exists($caminho),
            'atualizadoEm' => file_exists($caminho) ? date('d/m/Y H:i', filemtime($caminho)) : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'template' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $caminho = config('auditoria.template_path');
        File::ensureDirectoryExists(dirname($caminho));

        $request->file('template')->move(dirname($caminho), basename($caminho));

        return redirect()->route('admin.template.index')->with('status', 'Template atualizado com sucesso.');
    }
}
