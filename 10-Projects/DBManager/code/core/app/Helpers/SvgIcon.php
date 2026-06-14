<?php

namespace App\Helpers;

class SvgIcon
{
    /**
     * Render an inline SVG <use> reference from the admin icon sprite.
     *
     * Usage in Blade: @svg('bell') or @svg($iconName)
     * Produces: <svg class="..."><use href="#i-bell"/></svg>
     */
    public static function render(string $name): string
    {
        $name = e(trim($name, "'\" "));
        return '<svg class="inline w-4 h-4 align-[-3px] stroke-[#7d837f] fill-none" '
            . 'style="stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round">'
            . "<use href=\"#i-{$name}\"/>"
            . '</svg>';
    }
}
