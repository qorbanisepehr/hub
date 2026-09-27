<?php

namespace App\Integrations\Farzin;

use RuntimeException;

/**
 * A Farzin (SQL Server) failure. The bootstrap exception handler converts
 * this to HTTP 503 without leaking vendor details — same contract as the
 * Rahkaran exception (V2 §37).
 */
final class FarzinUnavailableException extends RuntimeException {}
