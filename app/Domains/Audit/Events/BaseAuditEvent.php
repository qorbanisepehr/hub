<?php

namespace App\Domains\Audit\Events;

use App\Events\BaseAuditEvent as SharedBaseAuditEvent;

/**
 * @deprecated Use App\Events\BaseAuditEvent directly.
 * This class extends the shared base for backward compatibility.
 */
abstract class BaseAuditEvent extends SharedBaseAuditEvent {}
