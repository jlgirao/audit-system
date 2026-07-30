<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Template do Excel de auditoria
    |--------------------------------------------------------------------------
    |
    | Caminho do arquivo-molde usado para gerar o Excel de saída de cada
    | processo. É um único template, fixo e idêntico para todas as
    | auditorias (conforme definido no planejamento). O admin faz o
    | upload dele pela tela /admin/template.
    |
    */
    'template_path' => storage_path('app/templates/audit_template.xlsx'),

];
