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
                // Mukta Malar (self-hosted via pesu-tokens.css) covers Tamil and Latin, as on the board.
                sans: ['"Mukta Malar"', '"Noto Sans Tamil"', 'Latha', '"Nirmala UI"', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
