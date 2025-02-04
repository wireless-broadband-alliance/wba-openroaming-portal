/** @type {import('tailwindcss').Config} */

module.exports = {
    content: [
        "./assets/**/*.css",
        "./assets/**/*.js",
        "./templates/**/*.html.twig"
    ],
    theme: {
        extend: {
            colors: {
                black: '#212529',
                gray: {
                    100: '#F5F5F5',
                },
                primary: '#7BBA29',
                veryDarkPurple: '#594B60',
                veryDarkButton: '#232B35',
                lightGreen: '#57a475',
                veryLightGreen: '#f1f8f4',
                lightGray: '#F7F7F9',
                inputGray: '#D9D9D9',
                infoBgColor: '#DFEBFC',
                infoTextColor: '#1D65A7',
                disableCardsColor: '#E4E4E4'
            },

            fontFamily: {
                sans: ["Asap", "Inter var", "sans-serif"],
                roboto: ["Roboto", "mono"],
                segoe: ["Segoe UI", "sans-serif"]
            },
            animation: {
                'spin-slow': 'spin 3s linear infinite',
                'fadeInAndOut': 'fadeInOut 2.5s cubic-bezier(0.4, 0, 0.2, 1)'
            },
            keyframes: {
                fadeInOut: {
                    '0%': {opacity: 0, transform: 'translateY(-10%)'},
                    '10%': {opacity: 1, transform: 'translateY(0)'},
                    '90%': {opacity: 1, transform: 'translateY(0)'},
                    '100%': {opacity: 0, transform: 'translateY(-10%)'},
                }
            },
        },
        plugins: [require("@tailwindcss/forms")],
    },
}
