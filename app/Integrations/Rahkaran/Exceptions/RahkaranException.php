<?php

namespace App\Integrations\Rahkaran\Exceptions;

use RuntimeException;

/**
 * Base for every Rahkaran failure. External error details (status codes,
 * vendor messages) are converted at the integration boundary and must not
 * leak beyond it (V2 §37); handlers translate these to HTTP semantics.
 */
abstract class RahkaranException extends RuntimeException {}
