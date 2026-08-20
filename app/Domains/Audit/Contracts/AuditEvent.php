<?php

namespace App\Domains\Audit\Contracts;

use App\Contracts\AuditEvent as SharedAuditEvent;

/**
 * @deprecated Use App\Contracts\AuditEvent directly.
 * This interface extends the shared contract for backward compatibility.
 */
interface AuditEvent extends SharedAuditEvent {}
