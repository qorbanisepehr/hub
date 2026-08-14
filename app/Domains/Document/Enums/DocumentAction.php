<?php

namespace App\Domains\Document\Enums;

/**
 * Operation vocabulary for document authorization. Permission names stay out of
 * the Document domain: the adapter maps each action to the concrete Role-based
 * permissions (see App\Services\DocumentAuthorizationService).
 */
enum DocumentAction: string
{
    case View = 'view';
    case Download = 'download';
    case Upload = 'upload';
    case Replace = 'replace';
    case Delete = 'delete';
    case Restore = 'restore';
    case ForceDelete = 'force_delete';
    case LibrarySelect = 'library_select';
    case HistoryView = 'history_view';
    case HistoryDownload = 'history_download';
}
