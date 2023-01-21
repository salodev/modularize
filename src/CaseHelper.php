<?php

namespace Salodev\Modularize;

use CaseHelper\CamelCaseHelper;

class CaseHelper
{
    public static function toKebab(string $string): string
    {
        return (new CamelCaseHelper())->toKebabCase($string);
    }
    
    public static function toPascalCase(string $string): string
    {
        return (new CamelCaseHelper())->toPascalCase($string);
    }
}
