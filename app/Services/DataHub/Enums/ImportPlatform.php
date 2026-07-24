<?php

declare(strict_types=1);

namespace App\Services\DataHub\Enums;

enum ImportPlatform: string
{
    case TrackMan = 'trackman';
    case HitTrax = 'hittrax';
    case Rapsodo = 'rapsodo';
    case BlastMotion = 'blast-motion';
    case GenericCsv = 'generic-csv';
}
