<?php

namespace App\Cms\Updates;

use RuntimeException;

/** Błąd sprawdzania lub instalowania aktualizacji; komunikat jest przeznaczony dla administratora. */
class UpdateException extends RuntimeException {}
