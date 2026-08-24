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
            fontFamily: {
                sans: ['Inter', 'system-ui', ...defaultTheme.fontFamily.sans],
                display: ['Gilroy', 'Inter', 'system-ui', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                seas: {
                    950: '#0A0A0A',
                    900: '#111111',
                    800: '#1A1A1A',
                    700: '#2A2A2A',
                    600: '#3D3D3D',
                    500: '#525252',
                    400: '#737373',
                    200: '#D4D4D4',
                    100: '#E5E7EB',
                    50: '#F3F4F6',
                },
                canvas: {
                    DEFAULT: '#E8ECF1',
                    soft: '#F0F3F7',
                },
                volt: {
                    DEFAULT: '#E10600',
                    soft: '#FFE8E6',
                    deep: '#B10500',
                    glow: '#FF3B30',
                },
                ember: {
                    DEFAULT: '#E10600',
                    soft: '#FFE8E6',
                },
            },
            boxShadow: {
                seas: '0 4px 6px -2px rgba(15, 23, 42, 0.04), 0 12px 28px -8px rgba(15, 23, 42, 0.12)',
                'seas-lg': '0 8px 16px -4px rgba(15, 23, 42, 0.08), 0 24px 48px -12px rgba(15, 23, 42, 0.18)',
                glow: '0 10px 32px rgba(225, 6, 0, 0.28)',
                card: '0 1px 2px rgba(15,23,42,0.04), 0 8px 24px rgba(15,23,42,0.08)',
            },
            maxWidth: {
                desktop: '1480px',
                'desktop-wide': '1600px',
            },
            backgroundImage: {
                'seas-mesh':
                    'radial-gradient(ellipse 70% 50% at 100% -10%, rgba(225,6,0,0.07), transparent 45%), radial-gradient(ellipse 50% 40% at 0% 100%, rgba(15,23,42,0.05), transparent 40%), linear-gradient(165deg, #E4E9F0 0%, #EEF1F6 40%, #E8ECF1 100%)',
                'seas-hero':
                    'linear-gradient(145deg, #0A0A0A 0%, #1A1A1A 55%, #2A0A0A 100%)',
                'seas-grid':
                    'linear-gradient(rgba(15,23,42,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(15,23,42,0.05) 1px, transparent 1px)',
            },
            animation: {
                'fade-up': 'fadeUp 0.55s cubic-bezier(0.22, 1, 0.36, 1) both',
                'fade-in': 'fadeIn 0.4s ease both',
                'slide-right': 'slideRight 0.45s cubic-bezier(0.22, 1, 0.36, 1) both',
                'pulse-soft': 'pulseSoft 2.8s ease-in-out infinite',
                'float': 'floatY 4s ease-in-out infinite',
            },
            keyframes: {
                fadeUp: {
                    from: { opacity: '0', transform: 'translateY(16px)' },
                    to: { opacity: '1', transform: 'none' },
                },
                fadeIn: {
                    from: { opacity: '0' },
                    to: { opacity: '1' },
                },
                slideRight: {
                    from: { opacity: '0', transform: 'translateX(-12px)' },
                    to: { opacity: '1', transform: 'none' },
                },
                pulseSoft: {
                    '0%, 100%': { opacity: '0.55' },
                    '50%': { opacity: '1' },
                },
                floatY: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-6px)' },
                },
            },
        },
    },

    plugins: [forms],
};
