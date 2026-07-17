/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./src/Resources/**/*.blade.php", "./src/Resources/**/*.js"],

    theme: {
        container: {
            center: true,

            screens: {
                "2xl": "1440px",
            },

            padding: {
                DEFAULT: "90px",
            },
        },

        screens: {
            sm: "525px",
            md: "768px",
            lg: "1024px",
            xl: "1240px",
            "2xl": "1440px",
            1180: "1180px",
            1060: "1060px",
            991: "991px",
            868: "868px",
        },

        extend: {
            colors: {
                navyBlue: "#141414",
                lightOrange: "#F6F2EB",
                darkGreen: '#40994A',
                darkBlue: '#0044F2',
                darkPink: '#F85156',

                diidsBg: "#E9E9E7",
                diidsSurface: "#F5F5F3",
                diidsInk: "#141414",
                diidsBlush: "#C98B7A",
                diidsBorder: "#D6D6D3",
            },

            fontFamily: {
                poppins: ["Manrope", "-apple-system", "sans-serif"],
                dmserif: ["Manrope", "-apple-system", "sans-serif"],
                mono: ["IBM Plex Mono", "monospace"],
            },
        }
    },

    plugins: [],

    safelist: [
        {
            pattern: /icon-/,
        }
    ]
};
