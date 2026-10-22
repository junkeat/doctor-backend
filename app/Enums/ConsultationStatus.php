<?php

namespace App\Enums;

enum ConsultationStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
}
