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
            colors: {
                brand: {
                    50: '#fdf3f2',
                    100: '#fbe4e2',
                    200: '#f7cbc7',
                    300: '#f0a49e',
                    400: '#e57068',
                    500: '#c01c15',
                    600: '#a01711',
                    700: '#841410',
                    800: '#6d1511',
                    900: '#5b1511',
                    950: '#320705',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
