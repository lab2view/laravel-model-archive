<?php

namespace Lab2view\ModelArchive\Enums;

enum BetweenScriptStep: string
{
    case BEFORE = 'before';
    case AFTER = 'after';
    case ALL = 'all';
}
