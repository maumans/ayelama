import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{jsx,tsx,js,ts}',
    ],

    theme: {
        extend: {
            colors: {
                // Fond application
                'app-bg': '#FBFAF7',

                // Primaire — Encre / Bleu Notaire
                ink: {
                    DEFAULT: '#15263F',
                    medium: '#1F3A5F',
                    light: '#2C4A75',
                },

                // Accent — Sceau (laiton/or feutré)
                seal: {
                    DEFAULT: '#B0863C',
                    hover: '#9A7331',
                    light: '#F5EDD8',
                },

                // Neutres
                slate: {
                    950: '#0F172A',
                    700: '#334155',
                    500: '#64748B',
                    200: '#E2E8F0',
                    100: '#F1F5F9',
                },

                // Sémantiques (versions douces — fond pâle + texte foncé)
                success: {
                    DEFAULT: '#15803D',
                    bg: '#F0FDF4',
                    text: '#166534',
                },
                warning: {
                    DEFAULT: '#B45309',
                    bg: '#FFFBEB',
                    text: '#92400E',
                },
                danger: {
                    DEFAULT: '#B91C1C',
                    bg: '#FEF2F2',
                    text: '#991B1B',
                },
                info: {
                    DEFAULT: '#1D4ED8',
                    bg: '#EFF6FF',
                    text: '#1E40AF',
                },
            },

            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                serif: ['Source Serif 4', 'Georgia', 'serif'],
                mono: ['Geist Mono', 'JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },

            fontSize: {
                'display-lg': ['2.25rem', { lineHeight: '2.75rem', letterSpacing: '-0.02em', fontWeight: '600' }],
                'display': ['1.75rem', { lineHeight: '2.25rem', letterSpacing: '-0.015em', fontWeight: '600' }],
                'heading': ['1.25rem', { lineHeight: '1.75rem', fontWeight: '600' }],
                'subheading': ['1rem', { lineHeight: '1.5rem', fontWeight: '500' }],
            },

            boxShadow: {
                card: '0 1px 3px 0 rgba(0,0,0,0.06), 0 1px 2px -1px rgba(0,0,0,0.04)',
                'card-hover': '0 4px 12px 0 rgba(0,0,0,0.08), 0 2px 4px -1px rgba(0,0,0,0.06)',
                'dialog': '0 20px 60px 0 rgba(0,0,0,0.15)',
            },

            borderRadius: {
                DEFAULT: '0.5rem',
            },

            animation: {
                'fade-in-up': 'fadeInUp 0.25s ease-out',
                'fade-in': 'fadeIn 0.2s ease-out',
            },

            keyframes: {
                fadeInUp: {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
            },
        },
    },

    plugins: [forms],
};
