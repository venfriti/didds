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
                navyBlue: "#1F2A44",
                lightOrange: "#F6F2EB",
                darkGreen: '#40994A',
                darkBlue: '#0044F2',
                darkPink: '#F85156',

                diidsBg: "#EDE8E0",
                diidsSurface: "#FAF8F5",
                diidsInk: "#201D1A",
                diidsBlush: "#C98B7A",
                diidsBorder: "#D9D2C7",
            },

            fontFamily: {
                poppins: ["Inter", "-apple-system", "sans-serif"],
                dmserif: ["Fraunces", "Georgia", "serif"],
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
