<?php

namespace App\Enums;

enum ImprovementTypeEnum: int
{
    case Refactor = 1;
    case Fix = 2;
    case Optimization = 3;
}
