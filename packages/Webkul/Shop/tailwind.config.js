/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./src/Resources/**/*.blade.php",
        "./src/Resources/**/*.js",
        "../SocialLogin/src/Resources/**/*.blade.php",
    ],

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
                // Pure black & white system. Legacy accent tokens remapped to
                // ink so any lingering references stay on-brand (no stray color).
                navyBlue: "#0A0A0A",
                lightOrange: "#FFFFFF",
                darkGreen: '#0A0A0A',
                darkBlue: '#0A0A0A',
                darkPink: '#0A0A0A',

                diidsBg: "#FFFFFF",
                diidsSurface: "#FFFFFF",
                diidsInk: "#0A0A0A",
                diidsBlush: "#0A0A0A",
                diidsBorder: "#E4E4E4",
                diidsMuted: "#6B6B6B",
            },

            fontFamily: {
                // Grift = grotesque sans, used for ALL UI/body/headings.
                // Magste is hero-only and applied via .diids-hero CSS, NOT via
                // these utility tokens — so font-dmserif/font-poppins resolve to
                // Grift everywhere in the templates.
                poppins: ["Grift", "-apple-system", "Helvetica Neue", "sans-serif"],
                grift: ["Grift", "-apple-system", "Helvetica Neue", "sans-serif"],
                dmserif: ["Grift", "-apple-system", "Helvetica Neue", "sans-serif"],
                serif: ["Grift", "-apple-system", "Helvetica Neue", "sans-serif"],
                magste: ["Magste", "Times New Roman", "serif"],
                mono: ["IBM Plex Mono", "monospace"],
            },

            letterSpacing: {
                tightest: "-0.04em",
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
