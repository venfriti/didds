/**
 * DIIDS — WebGL hero slider with scroll displacement + slide crossfade.
 * -----------------------------------------------------------------------------
 * Renders the ACTIVE cutout slide on a WebGL plane (alpha kept) and cross-fades
 * to the next slide's texture on change. Displacement is driven by scroll +
 * pointer + time. All slide textures are loaded up front (small webp cutouts).
 *
 * Optimizations: three.js dynamically imported; DPR capped at 2; RAF pauses
 * off-screen; reduced-motion / no-WebGL keeps the <img> stack as fallback.
 *
 * A tiny global bus (window.__diidsHero) lets the slider controller drive the
 * active texture; the same controller also animates the DOM <img> fallback.
 */

const REDUCED_MOTION = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

const vertexShader = /* glsl */ `
  varying vec2 vUv;
  void main(){ vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position,1.0); }
`;

const fragmentShader = /* glsl */ `
  precision highp float;
  uniform sampler2D uTexA;
  uniform sampler2D uTexB;
  uniform float uMix;         // 0 = A, 1 = B (crossfade)
  uniform vec2  uCoverA;
  uniform vec2  uCoverB;
  uniform vec2  uMouse;
  uniform float uTime;
  uniform float uScroll;
  uniform float uHover;
  varying vec2 vUv;

  vec2 hash2(vec2 p){ p=vec2(dot(p,vec2(127.1,311.7)),dot(p,vec2(269.5,183.3))); return -1.+2.*fract(sin(p)*43758.5453); }
  float noise(vec2 p){ vec2 i=floor(p),f=fract(p),u=f*f*(3.-2.*f);
    return mix(mix(dot(hash2(i),f),dot(hash2(i+vec2(1,0)),f-vec2(1,0)),u.x),
               mix(dot(hash2(i+vec2(0,1)),f-vec2(0,1)),dot(hash2(i+vec2(1,1)),f-vec2(1,1)),u.x),u.y); }

  vec2 displace(vec2 uv){
    float t = uTime*0.05;
    vec2 flow = vec2(noise(uv*3.0+vec2(t,0.0)), noise(uv*3.0+vec2(0.0,t)+5.2));
    float amp = 0.006 + uScroll*0.05 + uHover*0.015;
    vec2 m = uMouse*0.5+0.5;
    float d = distance(uv,m);
    float ripple = sin(d*20.0 - uTime*2.0)*exp(-d*6.0);
    return uv + flow*amp + vec2(0.0, ripple*0.015*uHover) + vec2(0.0,-uScroll*0.08*(1.0-uv.y));
  }

  void main(){
    vec2 uvA = (vUv-0.5)*uCoverA+0.5;
    vec2 uvB = (vUv-0.5)*uCoverB+0.5;
    // slide-change wipe: nudge the fade with a little vertical offset for life
    float slideShift = (1.0-uMix)*0.04;
    vec4 a = texture2D(uTexA, displace(uvA) + vec2(0.0, -uMix*0.04));
    vec4 b = texture2D(uTexB, displace(uvB) + vec2(0.0, slideShift));
    vec4 col = mix(a, b, smoothstep(0.0,1.0,uMix));
    col.rgb = clamp((col.rgb-0.5)*1.06+0.5, 0.0, 1.0);
    col.a *= (1.0 - uScroll*0.35);
    gl_FragColor = col;
  }
`;

async function initHero(el) {
  const list = (el.getAttribute("data-hero-srcs") || "").split(",").filter(Boolean);
  if (!list.length || REDUCED_MOTION) return;

  let THREE;
  try { THREE = await import("three"); } catch (e) { return; }

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, premultipliedAlpha: false, powerPreference: "high-performance" });
  } catch (e) { return; }

  const scene = new THREE.Scene();
  const camera = new THREE.OrthographicCamera(-0.5, 0.5, 0.5, -0.5, 0, 10);
  camera.position.z = 1;
  renderer.setClearColor(0x000000, 0);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(el.clientWidth, el.clientHeight);
  Object.assign(renderer.domElement.style, { display: "block", width: "100%", height: "100%" });
  el.appendChild(renderer.domElement);

  const uniforms = {
    uTexA: { value: null }, uTexB: { value: null },
    uMix: { value: 0 },
    uCoverA: { value: new THREE.Vector2(1, 1) }, uCoverB: { value: new THREE.Vector2(1, 1) },
    uMouse: { value: new THREE.Vector2(0, 0) },
    uTime: { value: 0 }, uScroll: { value: 0 }, uHover: { value: 0 },
  };
  const mesh = new THREE.Mesh(
    new THREE.PlaneGeometry(1, 1, 1, 1),
    new THREE.ShaderMaterial({ vertexShader, fragmentShader, uniforms, transparent: true })
  );
  scene.add(mesh);

  const coverFor = (tex) => {
    const ia = tex.image.width / tex.image.height;
    const ea = el.clientWidth / el.clientHeight;
    return ea > ia ? new THREE.Vector2(ea / ia, 1) : new THREE.Vector2(1, ia / ea);
  };

  // preload all textures
  const loader = new THREE.TextureLoader();
  const textures = await Promise.all(list.map((src) => new Promise((res) => {
    loader.load(src, (t) => { t.minFilter = THREE.LinearFilter; t.magFilter = THREE.LinearFilter; t.generateMipmaps = false; res(t); }, undefined, () => res(null));
  })));
  if (!textures[0]) return;

  let current = 0;
  uniforms.uTexA.value = textures[0];
  uniforms.uTexB.value = textures[0];
  uniforms.uCoverA.value.copy(coverFor(textures[0]));
  uniforms.uCoverB.value.copy(coverFor(textures[0]));
  el.classList.add("hero-gl--ready");

  // crossfade to slide n
  let fadeReq = null;
  function goTo(n) {
    if (n === current || !textures[n]) return;
    uniforms.uTexA.value = textures[current];
    uniforms.uCoverA.value.copy(coverFor(textures[current]));
    uniforms.uTexB.value = textures[n];
    uniforms.uCoverB.value.copy(coverFor(textures[n]));
    uniforms.uMix.value = 0;
    const start = performance.now();
    const dur = 900;
    cancelAnimationFrame(fadeReq);
    (function step(now) {
      const p = Math.min((now - start) / dur, 1);
      uniforms.uMix.value = p;
      if (p < 1) fadeReq = requestAnimationFrame(step);
      else { current = n; uniforms.uTexA.value = textures[n]; uniforms.uCoverA.value.copy(coverFor(textures[n])); uniforms.uMix.value = 0; }
    })(start);
  }

  // expose to the slider controller
  window.__diidsHero = { goTo, count: textures.length };
  el.dispatchEvent(new CustomEvent("hero-gl-ready", { bubbles: true }));

  // interaction
  const tMouse = new THREE.Vector2(0, 0);
  let tHover = 0;
  el.addEventListener("pointermove", (e) => {
    const r = el.getBoundingClientRect();
    tMouse.set(((e.clientX - r.left) / r.width) * 2 - 1, -(((e.clientY - r.top) / r.height) * 2 - 1));
    tHover = 1;
  }, { passive: true });
  el.addEventListener("pointerleave", () => { tHover = 0; }, { passive: true });

  function onScroll() {
    const r = el.getBoundingClientRect();
    uniforms.uScroll.value = Math.min(Math.max(-r.top / (r.height || 1), 0), 1);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  function onResize() {
    renderer.setSize(el.clientWidth, el.clientHeight);
    if (uniforms.uTexA.value) uniforms.uCoverA.value.copy(coverFor(uniforms.uTexA.value));
    if (uniforms.uTexB.value) uniforms.uCoverB.value.copy(coverFor(uniforms.uTexB.value));
  }
  window.addEventListener("resize", onResize, { passive: true });

  let visible = true;
  new IntersectionObserver((es) => { visible = es[0].isIntersecting; }, { threshold: 0 }).observe(el);

  const clock = new THREE.Clock();
  (function loop() {
    requestAnimationFrame(loop);
    if (!visible) return;
    uniforms.uTime.value += clock.getDelta();
    uniforms.uMouse.value.lerp(tMouse, 0.06);
    uniforms.uHover.value += (tHover - uniforms.uHover.value) * 0.05;
    renderer.render(scene, camera);
  })();
}

function boot() {
  document.querySelectorAll("[data-hero-srcs]").forEach(initHero);
}

if (document.readyState === "complete") boot();
else window.addEventListener("load", boot, { once: true });

export default boot;
