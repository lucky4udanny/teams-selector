<?php

namespace App\Enums;

enum RuleType: string
{
    case Size = 'size';
    case BannedPair = 'banned_pair';
    case RepeatPair = 'repeat_pair';
    case PreferredPair = 'preferred_pair';
    case SkillLeveling = 'skill_leveling';
    case MemberAttribute = 'member_attribute';
}
