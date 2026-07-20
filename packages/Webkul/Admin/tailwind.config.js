/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./src/Resources/**/*.blade.php", "./src/Resources/**/*.js"],

    theme: {
        container: {
            center: true,

            screens: {
                "2xl": "1920px",
            },

            padding: {
                DEFAULT: "16px",
            },
        },

        screens: {
            sm: "525px",
            md: "768px",
            lg: "1024px",
            xl: "1240px",
            "2xl": "1920px",
        },

        extend: {
            colors: {
                darkGreen: '#40994A',
                darkBlue: '#0044F2',
                darkPink: '#F85156',

                navyBlue: "#141414",
                diidsSurface: "#F5F5F3",
                diidsInk: "#141414",
                diidsBlush: "#C98B7A",
                diidsBorder: "#D6D6D3",
            },

            fontFamily: {
                inter: ['Inter'],
                icon: ['icomoon'],
                poppins: ["Manrope", "-apple-system", "sans-serif"],
                dmserif: ["Manrope", "-apple-system", "sans-serif"],
                mono: ["IBM Plex Mono", "monospace"],
            }
        },
    },

    darkMode: 'class',

    plugins: [],

    safelist: [
        {
            pattern: /icon-/,
        }
    ]
};
