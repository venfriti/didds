/**
 * DIIDS hero parallax.
 * -----------------------------------------------------------------------------
 * Subtle pointer + scroll parallax for the cutout-models hero. The figure and
 * the oversized type drift by different amounts to create depth. No WebGL.
 * Respects prefers-reduced-motion.
 */

const REDUCED_MOTION = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

function initParallax(hero) {
    const figure = hero.querySelector("[data-parallax-layer]");
    const words = hero.querySelectorAll(".diids-hero__word");
    if (!figure && !words.length) return;

    // Preserve each word's authored base transform (the editorial X offset)
    // so we add to it rather than overwrite it.
    const wordBase = new Map();
    words.forEach((w) => wordBase.set(w, getComputedStyle(w).transform === "none"
        ? { x: 0 } : null));

    let targetX = 0, targetY = 0;
    let curX = 0, curY = 0;
    let scrollY = 0;
    let raf = null;

    function onMove(e) {
        const r = hero.getBoundingClientRect();
        targetX = ((e.clientX - r.left) / r.width - 0.5) * 2;   // -1..1
        targetY = ((e.clientY - r.top) / r.height - 0.5) * 2;
        schedule();
    }
    function onLeave() { targetX = 0; targetY = 0; schedule(); }
    function onScroll() { scrollY = window.scrollY || 0; schedule(); }

    function schedule() { if (!raf) raf = requestAnimationFrame(tick); }

    function tick() {
        raf = null;
        curX += (targetX - curX) * 0.08;
        curY += (targetY - curY) * 0.08;

        // On tablet/mobile the figure is in normal flex flow (centered by CSS),
        // so we must NOT apply the desktop's translateX(-50%) centering. Keep the
        // motion subtle and centered-safe.
        const mobile = window.innerWidth <= 991;

        // Figure: small counter-move + gentle scroll lift
        if (figure) {
            const fx = curX * -10;
            const fy = curY * -6 - scrollY * 0.06;
            figure.style.transform = mobile
                ? `translateY(${fy * 0.5}px)`
                : `translateX(calc(-50% + ${fx}px)) translateY(${fy}px)`;
        }

        // Words: larger drift (they're "further back"), plus their base offset
        words.forEach((w, i) => {
            const dir = w.classList.contains("diids-hero__word--top") ? -1 : 1;
            const baseVw = dir < 0 ? -4 : 5;             // matches CSS authored offset
            const px = curX * 22 * dir;
            const py = curY * 14 + scrollY * 0.12;
            w.style.transform = mobile
                ? `translateY(${py * 0.4}px)`
                : `translateX(calc(${baseVw}vw + ${px}px)) translateY(${py}px)`;
        });

        if (Math.abs(targetX - curX) > 0.001 || Math.abs(targetY - curY) > 0.001) {
            schedule();
        }
    }

    if (!REDUCED_MOTION) {
        hero.addEventListener("pointermove", onMove, { passive: true });
        hero.addEventListener("pointerleave", onLeave, { passive: true });
        window.addEventListener("scroll", onScroll, { passive: true });
        tick();
    }
}

function boot() {
    document.querySelectorAll("[data-hero-parallax]").forEach(initParallax);
}

/* Boot after Vue mount (window.load), same reasoning as hero-webgl. */
if (document.readyState === "complete") {
    boot();
} else {
    window.addEventListener("load", boot, { once: true });
}

export default boot;
