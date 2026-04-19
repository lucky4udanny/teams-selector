<?php

namespace App\Enums;

enum RuleType: string
{
    case TeamSize = 'team_size';
    case GroupSize = 'group_size';
    case BannedPair = 'banned_pair';
    case RepeatPair = 'repeat_pair';
}
