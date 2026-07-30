/**
 * DIIDS hero slider controller.
 * -----------------------------------------------------------------------------
 * Auto-advances the hero, updates the index counter + dots, and drives BOTH the
 * WebGL plane (window.__diidsHero.goTo, when ready) and the DOM <img> fallback
 * (crossfade via .is-active). Pauses on hover and when the tab/hero is hidden.
 */

function initSlider(root) {
  const interval = parseInt(root.getAttribute("data-hero-interval") || "5200", 10);
  const imgs = [...root.querySelectorAll(".diids-hero__img")];
  const dots = [...root.querySelectorAll("[data-slide-to]")];
  const indexEl = root.querySelector("[data-hero-index]");
  const count = imgs.length || 4;
  if (count < 2) return;

  let i = 0;
  let timer = null;
  let paused = false;

  function render() {
    imgs.forEach((im, n) => im.classList.toggle("is-active", n === i));
    dots.forEach((d, n) => d.classList.toggle("is-active", n === i));
    if (indexEl) indexEl.textContent = String(i + 1).padStart(2, "0");
    if (window.__diidsHero) window.__diidsHero.goTo(i);   // WebGL crossfade
  }

  function go(n) { i = (n + count) % count; render(); }
  function next() { go(i + 1); }

  function start() { stop(); if (!paused) timer = setInterval(next, interval); }
  function stop() { if (timer) { clearInterval(timer); timer = null; } }

  dots.forEach((d) => d.addEventListener("click", () => {
    go(parseInt(d.getAttribute("data-slide-to"), 10));
    start(); // reset cadence after manual nav
  }));

  root.addEventListener("pointerenter", () => { paused = true; stop(); });
  root.addEventListener("pointerleave", () => { paused = false; start(); });
  document.addEventListener("visibilitychange", () => { document.hidden ? stop() : start(); });

  // if WebGL becomes ready after we start, re-sync it to current slide
  root.addEventListener("hero-gl-ready", () => { if (window.__diidsHero) window.__diidsHero.goTo(i); });

  render();
  start();
}

function boot() {
  document.querySelectorAll("[data-hero-slider]").forEach(initSlider);
}

if (document.readyState === "complete") boot();
else window.addEventListener("load", boot, { once: true });

export default boot;
