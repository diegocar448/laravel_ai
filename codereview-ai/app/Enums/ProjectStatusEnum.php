<?php

namespace App\Enums;

enum ProjectStatusEnum: int
{
    case Active = 1;
    case Completed = 2;
    case Archived = 3;
}
