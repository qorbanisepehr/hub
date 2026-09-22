<?php

namespace App\Integrations\Rahkaran\Exceptions;

/**
 * Rahkaran could not be reached or answered with a usable response. The
 * client sees a generic unavailable condition — never the raw vendor error
 * (V2 §37).
 */
final class RahkaranUnavailableException extends RahkaranException {}
