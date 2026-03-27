<?php

namespace App\Enums;

enum ImprovementStepEnum: int
{
    case ToDo = 1;
    case InProgress = 2;
    case Done = 3;
}
