<?php

namespace App\Services\Dropbox;

use RuntimeException;

/**
 * Lançada quando o cursor salvo não é mais aceito pelo Dropbox (ex: a pasta
 * foi movida/recriada). Quem capturar essa exceção deve limpar o
 * dropbox_cursor do processo e reiniciar a sincronização do zero.
 */
class DropboxCursorInvalidoException extends RuntimeException
{
}
