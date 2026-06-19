<?php

namespace DBM\Wp;

class FilterController
{
    public function register(): void
    {
        add_filter('the_title', 'do_shortcode');
        add_filter('the_excerpt', 'do_shortcode');
        add_filter('widget_text', 'do_shortcode');

        add_filter('acf/format_value', function ($value) {
            if (is_string($value)) {
                return do_shortcode($value);
            }
            return $value;
        }, 10, 1);
    }
}
