import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Charte graphique Gogab
 * - primary   : Vert Émeraude #00A86B (~60 %) — boutons principaux, éléments actifs
 * - secondary : Bleu Profond  #1E3A8A (~30 %) — en-têtes, liens, éléments de confiance
 * - accent    : Jaune Soleil  #FBBF24 (~10 %) — accents, CTA secondaires, badges
 *
 * Accessibilité (contraste WCAG AA ≥ 4,5:1) : le blanc sur #00A86B n'atteint que 3,1:1,
 * donc les boutons à texte blanc utilisent primary-600 (#008660, 4,6:1) ; #00A86B
 * (primary / primary-500) sert aux surfaces sans texte, icônes, bordures et au logo.
 * Le jaune est toujours associé à un texte bleu (6,2:1), jamais à du blanc.
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#00A86B',
                    50: '#E6F8F1',
                    100: '#C2EEDC',
                    200: '#8FDFC0',
                    300: '#52CC9E',
                    400: '#1AB67D',
                    500: '#00A86B',
                    600: '#008660',
                    700: '#006F50',
                    800: '#005A41',
                    900: '#004A36',
                },
                secondary: {
                    DEFAULT: '#1E3A8A',
                    50: '#EFF3FB',
                    100: '#DCE4F6',
                    200: '#B7C7EC',
                    300: '#8AA3DD',
                    400: '#5B7BC9',
                    500: '#3A5BB0',
                    600: '#2B489B',
                    700: '#1E3A8A',
                    800: '#182F70',
                    900: '#122356',
                },
                accent: {
                    DEFAULT: '#FBBF24',
                    50: '#FFFBEB',
                    100: '#FEF3C7',
                    200: '#FDE68A',
                    300: '#FCD34D',
                    400: '#FBBF24',
                    500: '#F59E0B',
                    600: '#D97706',
                    700: '#B45309',
                    800: '#92400E',
                    900: '#78350F',
                },
            },
        },
    },

    plugins: [forms],
};
