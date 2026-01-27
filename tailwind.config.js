import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                // Primary brand color
                primary: '#137fec',
                
                // Background colors
                'background-light': '#f6f7f8',
                'background-dark': '#101922',
                
                // Surface colors for cards/panels
                'surface-dark': '#1e2630',
                'surface-darker': '#111418',
                
                // Border colors
                'border-dark': '#283039',
                
                // Text colors
                'text-secondary': '#9dabb9',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                DEFAULT: '0.25rem',
                lg: '0.5rem',
                xl: '0.75rem',
            },
        },
    },

    plugins: [forms],
};
