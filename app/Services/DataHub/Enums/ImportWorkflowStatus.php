<?php

declare(strict_types=1);

namespace App\Services\DataHub\Enums;

enum ImportWorkflowStatus: string
{
    case PlatformSelection = 'platform_selection';
    case FileSelection = 'file_selection';
    case DestinationSelection = 'destination_selection';
    case Preview = 'preview';
    case PreviewComplete = 'preview_complete';
}
