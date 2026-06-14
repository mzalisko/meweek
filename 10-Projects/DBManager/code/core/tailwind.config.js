import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                acc: { DEFAULT: '#54708c', bg: '#e9eef3', tx: '#3a526b', bd: '#c8d4de' },
                canvas: '#f4f5f3',
                ink: '#2f3438',
                mut: '#7d837f',
                ok: { bg: '#e9f0ec', tx: '#52705f' },
                warn: { bg: '#f5eedd', tx: '#96752f' },
                bad: { bg: '#f3e5e2', tx: '#96514a' },
            },
        },
    },

    safelist: [
        'bg-ok-bg', 'text-ok-tx',
        'bg-warn-bg', 'text-warn-tx',
        'bg-bad-bg', 'text-bad-tx',
    ],

    plugins: [forms],
};
