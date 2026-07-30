/**
 * DIIDS scroll effects.
 * -----------------------------------------------------------------------------
 * Premium, fluid scroll experience for the homepage:
 *   1. REVEAL   — sections/cards fade + rise once as they enter view.
 *   2. PARALLAX — marked images drift against scroll for depth.
 *
 * The key to feeling "fluid" rather than stepped: parallax never reads
 * window.scrollY directly into a transform. Raw scroll position is itself
 * jerky (discrete wheel/trackpad deltas), so instead we run one continuous
 * rAF loop that LERPS a smoothed scroll value toward the real one every
 * frame — same technique as hero-parallax.js. That smoothing is what makes
 * the motion glide instead of stepping.
 *
 * Honors prefers-reduced-motion.
 */

const REDUCED = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

/* Sections/blocks that fade + rise on entry. */
const REVEAL_SELECTORS = [
    ".diids-statement__label",
    ".diids-statement__lead",
    ".diids-statement__body",
    ".diids-band__head",
    ".diids-cat",
    ".diids-campaign__overlay",
    "[id='v-products-carousel-template'] .container > .flex",   // section head
];

/* Images that parallax within their container. */
const PARALLAX_SELECTORS = [".diids-cat img", ".diids-campaign__img"];

function initReveal() {
    const set = new Set(document.querySelectorAll("[data-reveal]"));
    REVEAL_SELECTORS.forEach((s) => document.querySelectorAll(s).forEach((el) => set.add(el)));
    const els = [...set];
    if (!els.length) return;

    els.forEach((el) => el.classList.add("diids-fx-reveal"));

    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                // stagger siblings within the same grid/row
                const sibs = [...(entry.target.parentElement?.children || [])].filter((c) =>
                    c.classList.contains("diids-fx-reveal")
                );
                const i = Math.max(0, sibs.indexOf(entry.target));
                entry.target.style.transitionDelay = (i % 4) * 90 + "ms";
                entry.target.classList.add("is-in");
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: "0px 0px -10% 0px" });

    els.forEach((el) => io.observe(el));
}

function initParallax() {
    const nodes = [];
    PARALLAX_SELECTORS.forEach((s) => document.querySelectorAll(s).forEach((el) => {
        el.classList.add("diids-fx-parallax");
        nodes.push({ el, current: 0, target: 0, visible: false });
    }));
    if (!nodes.length) return;

    // Track visibility cheaply via IntersectionObserver instead of measuring
    // getBoundingClientRect on every frame for every node.
    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const n = nodes.find((x) => x.el === entry.target);
            if (n) n.visible = entry.isIntersecting;
        });
    }, { threshold: 0, rootMargin: "200px 0px 200px 0px" });
    nodes.forEach((n) => io.observe(n.el));

    let rafId = null;

    function frame() {
        rafId = null;
        let stillMoving = false;
        const vh = window.innerHeight;

        nodes.forEach((n) => {
            if (!n.visible) return;
            const r = n.el.getBoundingClientRect();
            const progress = (r.top + r.height / 2 - vh / 2) / vh;   // -1..1-ish
            n.target = Math.max(-24, Math.min(24, -progress * 32));

            // continuous smoothing: glide toward the target every frame,
            // regardless of how choppy the underlying scroll events are.
            n.current += (n.target - n.current) * 0.12;

            if (Math.abs(n.target - n.current) > 0.05) stillMoving = true;

            n.el.style.transform = `translate3d(0, ${n.current.toFixed(2)}px, 0) scale(1.06)`;
        });

        // keep the loop alive while anything is still easing or on-screen
        if (stillMoving || nodes.some((n) => n.visible)) {
            rafId = requestAnimationFrame(frame);
        }
    }

    function kick() { if (!rafId) rafId = requestAnimationFrame(frame); }

    window.addEventListener("scroll", kick, { passive: true });
    window.addEventListener("resize", kick, { passive: true });
    kick();
}

function boot() {
    if (REDUCED) return;
    initReveal();
    initParallax();
}

if (document.readyState === "complete") boot();
else window.addEventListener("load", boot, { once: true });

export default boot;
