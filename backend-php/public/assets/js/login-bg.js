// Auto-sliding library background for the login page.
//
// Drops your photos in as slides and cross-fades between them on a timer, with
// a slow Ken Burns zoom (the zoom + fade are pure CSS transitions in
// styles.css — this file only decides which slide is active and when).
//
// Photos: put 1–5 images at assets/img/login/bg-1.jpg … bg-5.jpg (landscape,
// ~1920×1080, premium library shots look best). Any that are missing simply
// drop out of the rotation. If NONE are present yet, a set of premium gradient
// scenes is used so the slideshow still runs — swap in real photos any time.

document.addEventListener('DOMContentLoaded', () => {
  const bg = document.getElementById('login-bg');
  if (!bg) return;
  // Slides go in above the base but below the blue grade + overlay, which is
  // what lets the grade recolour whichever slide is showing.
  const gradeLayer = bg.querySelector('.login-bg-grade') || bg.querySelector('.login-bg-overlay');

  // Premium library photos (Unsplash CDN — whitelisted in .htaccess CSP).
  // To use your own instead, drop files in assets/img/login/ and list their
  // local paths here (e.g. '/assets/img/login/bg-1.jpg'); any URL that fails
  // to load simply drops out of the rotation.
  const PHOTO_SOURCES = [
    // British Museum reading room — arched windows, domed ceiling
    'https://images.unsplash.com/photo-1765394715510-9bdf7dacc77f?auto=format&fit=crop&w=1920&q=75',
    'https://images.unsplash.com/photo-1765394715568-889eab558ed2?auto=format&fit=crop&w=1920&q=75',
    // Law library rotunda — tiered galleries under a skylight
    'https://images.unsplash.com/photo-1747515204290-6bf2511fb05b?auto=format&fit=crop&w=1920&q=75',
    // Wall of books, strong repeating grid
    'https://images.unsplash.com/photo-1771172193679-cce75ed26588?auto=format&fit=crop&w=1920&q=75',
  ];

  // Shipped premium library scenes (self-hosted SVG, CSP-safe, work offline).
  // Used when no real photo is present, so the slideshow always has something.
  const SCENE_SOURCES = [
    '/assets/img/login/scene-1.svg',
    '/assets/img/login/scene-2.svg',
    '/assets/img/login/scene-3.svg',
  ];

  const INTERVAL_MS = 10000;
  const slides = [];
  let index = 0;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function addSlide(cssImage) {
    const el = document.createElement('div');
    el.className = 'login-bg-slide';
    el.style.backgroundImage = cssImage;
    bg.insertBefore(el, gradeLayer);
    slides.push(el);
  }

  function activate(i) {
    slides.forEach((s, n) => s.classList.toggle('is-active', n === i));
  }

  function start() {
    if (slides.length === 0) return;
    activate(0);
    if (slides.length === 1 || reduceMotion) return;
    setInterval(() => {
      index = (index + 1) % slides.length;
      activate(index);
    }, INTERVAL_MS);
  }

  // Preload the photos first, then decide: if any real photo loaded, use only
  // those; otherwise fall back to the shipped library scenes. A missing photo
  // becomes a no-op instead of a blank black slide.
  const loaded = new Array(PHOTO_SOURCES.length).fill(false);
  let pending = PHOTO_SOURCES.length;

  const build = () => {
    PHOTO_SOURCES.forEach((src, i) => {
      if (loaded[i]) addSlide(`url("${src}")`);
    });
    if (slides.length === 0) {
      SCENE_SOURCES.forEach((src) => addSlide(`url("${src}")`));
    }
    start();
  };

  const settle = () => {
    if (--pending <= 0) build();
  };

  PHOTO_SOURCES.forEach((src, i) => {
    const img = new Image();
    img.onload = () => { loaded[i] = true; settle(); };
    img.onerror = settle;
    img.src = src;
  });
});
