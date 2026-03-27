<?php

namespace App\Enums;

enum ReviewStatusEnum: int
{
    case Pending = 1;
    case Completed = 2;
    case Failed = 3;
}
