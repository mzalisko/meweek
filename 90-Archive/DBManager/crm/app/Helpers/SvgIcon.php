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
    public static function render(string $name, string $size = '17'): string
    {
        $name = e(trim($name, "'\" "));
        $size = (int) $size;

        // Інлайн-стилі навмисно: цей SVG генерується в PHP, який Tailwind не сканує,
        // тож Tailwind-класи тут вирізались би з білда. currentColor → іконка бере
        // колір тексту (акцент для активного пункту меню).
        return '<svg style="display:inline-block;width:' . $size . 'px;height:' . $size . 'px;'
            . 'vertical-align:-3px;flex-shrink:0;stroke:currentColor;fill:none;'
            . 'stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round" aria-hidden="true">'
            . "<use href=\"#i-{$name}\"/>"
            . '</svg>';
    }
}
