<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';
/* (pertahankan logic PHP seperti di file asli Anda) */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CD 133 Production — Atelier Konveksi Custom</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,300;1,9..144,400;1,9..144,500&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ============================================================
   CD 133 — "ATELIER NOIR" EDITION (PERFORMANCE-OPTIMIZED)
   Editorial couture x modern tech
   ============================================================ */
:root {
    --canvas: #F4EFE6;
    --canvas-warm: #EBE4D5;
    --canvas-deep: #DFD6C2;
    --ink: #18181B;
    --ink-soft: #4A4A4F;
    --ink-muted: #86868B;
    --ink-faint: #B4B4B9;
    --copper: #B5472A;
    --copper-glow: #D9593A;
    --copper-deep: #7A2E1A;
    --gold: #C89B5E;
    --gold-soft: #E4C084;
    --line: rgba(24,24,27,.08);
    --line-strong: rgba(24,24,27,.18);
    --line-ink: rgba(24,24,27,.4);
    --shadow-sm: 0 2px 8px rgba(24,24,27,.05);
    --shadow-md: 0 12px 32px rgba(24,24,27,.08);
    --shadow-lg: 0 28px 72px rgba(24,24,27,.14);
    --shadow-copper: 0 16px 40px rgba(181,71,42,.18);
    --ease-smooth: cubic-bezier(.16,1,.3,1);
    --ease-bounce: cubic-bezier(.34,1.56,.64,1);
    --ease-in-out: cubic-bezier(.7,0,.3,1);
    --ease-out: cubic-bezier(.4,0,.2,1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }

body {
    background: var(--canvas);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    font-weight: 400;
    color: var(--ink);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    overflow-x: hidden;
}

::selection { background: var(--copper); color: var(--canvas); }
::-moz-selection { background: var(--copper); color: var(--canvas); }

/* ---------- Scrollbar ---------- */
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: var(--canvas-warm); }
::-webkit-scrollbar-thumb {
    background: var(--ink);
    border-radius: 8px;
    border: 2px solid var(--canvas-warm);
}
::-webkit-scrollbar-thumb:hover { background: var(--copper); }

/* ---------- Animations ---------- */
@keyframes fadeUp {
    from { opacity: 0; transform: translate3d(0,32px,0); }
    to { opacity: 1; transform: translate3d(0,0,0); }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes marquee {
    from { transform: translate3d(0,0,0); }
    to { transform: translate3d(-50%,0,0); }
}
@keyframes breathe {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: .5; }
}
@keyframes kenBurns {
    0% { transform: scale(1.02); }
    100% { transform: scale(1.06); }
}
@keyframes lineDraw {
    from { transform: scaleX(0); }
    to { transform: scaleX(1); }
}

/* ---------- Scroll Progress ---------- */
.scroll-progress {
    position: fixed;
    top: 0; left: 0;
    height: 2px;
    background: var(--copper);
    z-index: 1000;
    width: 100%;
    transform: scaleX(0);
    transform-origin: left;
    will-change: transform;
}

/* ---------- Navigation ---------- */
.site-nav {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 22px 48px;
    background: rgba(244,239,230,.92);
    border-bottom: 1px solid transparent;
    transition: padding .3s var(--ease-smooth),
                border-bottom-color .3s var(--ease-smooth);
}
.site-nav.is-scrolled {
    padding: 14px 48px;
    border-bottom-color: var(--line);
}
.site-nav__brand {
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    color: var(--ink);
}
.site-nav__mark {
    width: 40px; height: 40px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    flex-shrink: 0;
}
.site-nav__label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10.5px;
    font-weight: 500;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: var(--ink-soft);
}
.site-nav__label strong {
    display: block;
    font-family: 'Fraunces', serif;
    font-weight: 400;
    font-style: italic;
    font-size: 15px;
    letter-spacing: -.01em;
    text-transform: none;
    color: var(--ink);
    margin-bottom: 2px;
}
.site-nav__right {
    display: flex;
    align-items: center;
    gap: 40px;
}
.site-nav__links {
    display: flex;
    gap: 36px;
    list-style: none;
}
.site-nav__links a {
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: var(--ink-soft);
    text-decoration: none;
    position: relative;
    padding: 6px 0;
    transition: color .2s ease;
}
.site-nav__links a::after {
    content: "";
    position: absolute;
    left: 0; bottom: 0;
    width: 100%; height: 1px;
    background: var(--copper);
    transform: scaleX(0);
    transform-origin: right;
    transition: transform .3s var(--ease-smooth);
}
.site-nav__links a:hover { color: var(--ink); }
.site-nav__links a:hover::after {
    transform: scaleX(1);
    transform-origin: left;
}
.site-nav__links a.is-active { color: var(--ink); font-weight: 600; }
.site-nav__links a.is-active::after { transform: scaleX(1); transform-origin: left; }

.site-nav__cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    background: var(--ink);
    color: var(--canvas);
    text-decoration: none;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10.5px;
    font-weight: 500;
    letter-spacing: .18em;
    text-transform: uppercase;
    border-radius: 999px;
    transition: background .3s ease;
}
.site-nav__cta:hover { background: var(--copper); }

.site-nav__menu-btn {
    display: none;
    background: none;
    border: none;
    width: 36px; height: 36px;
    cursor: pointer;
    position: relative;
}
.site-nav__menu-btn span {
    position: absolute;
    left: 6px; right: 6px;
    height: 1.5px;
    background: var(--ink);
}
.site-nav__menu-btn span:nth-child(1) { top: 12px; }
.site-nav__menu-btn span:nth-child(2) { top: 18px; }
.site-nav__menu-btn span:nth-child(3) { top: 24px; }

@media (max-width: 1024px) {
    .site-nav { padding: 18px 24px; }
    .site-nav.is-scrolled { padding: 12px 24px; }
    .site-nav__links, .site-nav__cta { display: none; }
    .site-nav__menu-btn { display: block; }
}

/* ---------- Hero ---------- */
.hero-wrap {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    background: var(--ink);
}
.hero-slideshow {
    position: absolute;
    inset: 0;
    z-index: 1;
}
.hero-slideshow img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    opacity: 0;
    background: var(--ink);
    transform: scale(1.02);
    transition: opacity .8s var(--ease-out);
}
.hero-slideshow img.is-active {
    opacity: 1;
    animation: kenBurns 8s var(--ease-out) forwards;
}
.hero-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    background:
        radial-gradient(ellipse at 30% 70%, rgba(24,24,27,.3) 0%, transparent 60%),
        linear-gradient(180deg, rgba(24,24,27,.65) 0%, rgba(24,24,27,.2) 40%, rgba(24,24,27,.85) 100%);
}

.hero {
    position: relative;
    z-index: 4;
    min-height: 100vh;
    display: grid;
    grid-template-rows: auto 1fr auto;
    padding: 140px 48px 48px;
    color: var(--canvas);
}

.hero__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 80px;
    animation: fadeIn 1s ease .2s both;
}
.hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10.5px;
    font-weight: 500;
    letter-spacing: .3em;
    text-transform: uppercase;
    color: rgba(244,239,230,.9);
    padding: 10px 18px;
    border: 1px solid rgba(244,239,230,.25);
    border-radius: 999px;
}
.hero__eyebrow-dot {
    width: 6px; height: 6px;
    background: var(--copper-glow);
    border-radius: 50%;
    animation: breathe 2.2s ease-in-out infinite;
}
.hero__meta {
    text-align: right;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10.5px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: rgba(244,239,230,.7);
    line-height: 1.8;
}
.hero__meta strong {
    display: block;
    color: var(--canvas);
    font-weight: 500;
    font-family: 'Fraunces', serif;
    font-style: italic;
    font-size: 14px;
    letter-spacing: -.01em;
    text-transform: none;
    margin-bottom: 4px;
}

.hero__center {
    display: flex;
    flex-direction: column;
    justify-content: center;
    max-width: 1100px;
}
.hero__label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10.5px;
    font-weight: 500;
    letter-spacing: .3em;
    text-transform: uppercase;
    color: var(--gold-soft);
    margin-bottom: 32px;
    display: flex;
    align-items: center;
    gap: 14px;
    animation: fadeIn 1s ease .3s both;
}
.hero__label::before {
    content: "";
    width: 40px; height: 1px;
    background: var(--gold-soft);
}

.hero__title {
    font-family: 'Fraunces', serif;
    font-weight: 300;
    font-size: clamp(56px, 10vw, 152px);
    line-height: .92;
    letter-spacing: -.04em;
    color: var(--canvas);
    margin: 0;
}
.hero__title-line {
    display: block;
    overflow: hidden;
}
.hero__title-line > span {
    display: inline-block;
    animation: fadeUp .8s var(--ease-smooth) both;
}
.hero__title-line:nth-child(1) > span { animation-delay: .4s; }
.hero__title-line:nth-child(2) > span { animation-delay: .5s; }
.hero__title-line:nth-child(3) > span { animation-delay: .6s; }
.hero__title em {
    font-style: italic;
    font-weight: 400;
    color: var(--copper-glow);
    background: linear-gradient(135deg, var(--gold-soft) 0%, var(--copper-glow) 50%, var(--copper) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.hero__desc {
    font-family: 'Inter', sans-serif;
    font-size: 17px;
    font-weight: 300;
    line-height: 1.7;
    color: rgba(244,239,230,.85);
    max-width: 540px;
    margin: 44px 0 0;
    animation: fadeIn 1s ease .8s both;
}

.hero__actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 48px;
    animation: fadeIn 1s ease .9s both;
}

.btn {
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: .04em;
    padding: 18px 32px;
    border-radius: 999px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: transform .25s var(--ease-smooth), box-shadow .25s var(--ease-smooth), background .25s ease;
    border: none;
}
.btn--primary {
    background: var(--copper);
    color: var(--canvas);
    box-shadow: 0 8px 24px rgba(181,71,42,.35);
}
.btn--primary:hover {
    background: var(--copper-deep);
    box-shadow: 0 16px 40px rgba(181,71,42,.5);
    transform: translateY(-2px);
}

.btn--ghost {
    background: transparent;
    color: var(--canvas);
    border: 1px solid rgba(244,239,230,.3);
}
.btn--ghost:hover {
    background: rgba(244,239,230,.12);
    border-color: rgba(244,239,230,.6);
}
.btn svg {
    width: 14px; height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    transition: transform .3s var(--ease-smooth);
}
.btn:hover svg { transform: translateX(4px); }

.hero__bottom {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: flex-end;
    gap: 40px;
    margin-top: 80px;
    animation: fadeIn 1s ease 1s both;
}
.hero__stats {
    display: flex;
    gap: 48px;
}
.hero__stat {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.hero__stat-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: rgba(244,239,230,.6);
}
.hero__stat-value {
    font-family: 'Fraunces', serif;
    font-weight: 400;
    font-size: 36px;
    letter-spacing: -.02em;
    color: var(--canvas);
    font-feature-settings: "tnum";
}
.hero__stat-value em {
    font-style: italic;
    color: var(--copper-glow);
    font-weight: 300;
}
.hero__scroll {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: rgba(244,239,230,.6);
}
.hero__scroll-line {
    width: 1px; height: 60px;
    background: linear-gradient(180deg, transparent, var(--canvas));
    position: relative;
    overflow: hidden;
}
.hero__scroll-line::after {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 30%;
    background: var(--copper-glow);
    animation: scrollLine 2.4s ease-in-out infinite;
}
@keyframes scrollLine {
    0% { top: -30%; }
    100% { top: 100%; }
}

.hero__dots {
    display: flex;
    gap: 10px;
    align-items: center;
}
.hero__dots button {
    width: 8px; height: 8px;
    border-radius: 50%;
    border: 1px solid rgba(244,239,230,.5);
    background: transparent;
    cursor: pointer;
    transition: all .3s var(--ease-smooth);
    padding: 0;
}
.hero__dots button:hover { border-color: var(--canvas); }
.hero__dots button.is-active {
    background: var(--copper-glow);
    border-color: var(--copper-glow);
    width: 28px;
    border-radius: 4px;
}

@media (max-width: 900px) {
    .hero { padding: 120px 24px 32px; }
    .hero__top { flex-direction: column; gap: 24px; margin-bottom: 40px; }
    .hero__meta { text-align: left; }
    .hero__bottom { grid-template-columns: 1fr; gap: 24px; margin-top: 40px; }
    .hero__stats { gap: 32px; }
    .hero__stat-value { font-size: 28px; }
    .hero__scroll { display: none; }
    .hero__title { font-size: clamp(44px, 12vw, 90px); }
}

/* ---------- Marquee ---------- */
.marquee {
    background: var(--ink);
    color: var(--canvas);
    padding: 28px 0;
    overflow: hidden;
    position: relative;
    border-top: 1px solid rgba(244,239,230,.08);
    border-bottom: 1px solid rgba(244,239,230,.08);
}
.marquee::before,
.marquee::after {
    content: "";
    position: absolute;
    top: 0; bottom: 0;
    width: 160px;
    z-index: 2;
    pointer-events: none;
}
.marquee::before { left: 0; background: linear-gradient(90deg, var(--ink), transparent); }
.marquee::after { right: 0; background: linear-gradient(270deg, var(--ink), transparent); }
.marquee__track {
    display: flex;
    gap: 80px;
    width: max-content;
    animation: marquee 45s linear infinite;
    font-family: 'Fraunces', serif;
    font-size: 28px;
    font-weight: 300;
    font-style: italic;
    white-space: nowrap;
    align-items: center;
}
.marquee__track span {
    display: inline-flex;
    align-items: center;
    gap: 80px;
    color: rgba(244,239,230,.9);
}
.marquee__track span::before {
    content: "✦";
    color: var(--copper-glow);
    font-size: 14px;
    font-style: normal;
    letter-spacing: 0;
}
.marquee__track em {
    font-style: normal;
    color: var(--copper-glow);
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: .22em;
    text-transform: uppercase;
    font-weight: 500;
}

/* ---------- Section Title ---------- */
.public-wrap { max-width: 1400px; margin: 0 auto; padding: 0 48px; }

.section-title {
    padding: 140px 0 80px;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 80px;
    align-items: end;
    position: relative;
}
.section-title__num {
    font-family: 'Fraunces', serif;
    font-style: italic;
    font-weight: 300;
    font-size: 120px;
    color: var(--copper);
    line-height: .8;
    letter-spacing: -.04em;
    opacity: .3;
}
.section-title__content h2 {
    font-family: 'Fraunces', serif;
    font-weight: 300;
    font-size: clamp(40px, 6vw, 76px);
    line-height: 1;
    letter-spacing: -.03em;
    color: var(--ink);
    margin: 0 0 20px;
}
.section-title__content h2 em {
    font-style: italic;
    color: var(--copper);
    font-weight: 400;
}
.section-title__content p {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--ink-muted);
    line-height: 1.7;
    max-width: 50ch;
}

@media (max-width: 900px) {
    .public-wrap { padding: 0 24px; }
    .section-title { grid-template-columns: 1fr; gap: 24px; padding: 80px 0 48px; }
    .section-title__num { font-size: 80px; }
}

/* ---------- Menu Cards ---------- */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
    border-top: 1px solid var(--line-strong);
    margin-bottom: 140px;
}
.menu-card {
    position: relative;
    padding: 48px 40px;
    text-decoration: none;
    color: var(--ink);
    border-bottom: 1px solid var(--line-strong);
    border-right: 1px solid var(--line-strong);
    overflow: hidden;
    transition: background .3s ease;
    min-height: 320px;
    display: grid;
    grid-template-rows: auto 1fr auto;
    gap: 24px;
    background-color: var(--canvas-warm);
}
.menu-card:nth-child(2n) { border-right: none; }
.menu-card:hover { background: var(--ink); }
.menu-card > * { position: relative; z-index: 1; transition: color .3s ease; }
.menu-card:hover > * { color: var(--canvas); }

.menu-card__bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    background-color: var(--canvas-deep);
    opacity: 0;
    transition: opacity .4s ease;
    z-index: 0;
}
.menu-card:hover .menu-card__bg {
    opacity: .55;
}

.menu-card__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.menu-card__num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: .22em;
    color: var(--copper);
    font-weight: 500;
    transition: color .3s ease;
}
.menu-card:hover .menu-card__num { color: var(--gold-soft); }
.menu-card__icon {
    width: 48px; height: 48px;
    border: 1px solid var(--line-strong);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color .3s ease, background .3s ease;
}
.menu-card:hover .menu-card__icon {
    border-color: var(--canvas);
    background: var(--copper);
}
.menu-card__icon svg {
    width: 20px; height: 20px;
    stroke: var(--ink);
    fill: none;
    stroke-width: 1.5;
    transition: stroke .3s ease;
}
.menu-card:hover .menu-card__icon svg { stroke: var(--canvas); }

.menu-card__body h3 {
    font-family: 'Fraunces', serif;
    font-weight: 400;
    font-style: italic;
    font-size: 34px;
    letter-spacing: -.02em;
    line-height: 1.1;
    margin: 0 0 14px;
}
.menu-card__body p {
    font-family: 'Inter', sans-serif;
    font-size: 14.5px;
    line-height: 1.6;
    color: var(--ink-soft);
    font-weight: 300;
    max-width: 38ch;
    transition: color .3s ease;
}
.menu-card:hover .menu-card__body p { color: rgba(244,239,230,.75); }

.menu-card__cta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--ink);
    font-weight: 500;
    padding-top: 20px;
    border-top: 1px solid var(--line);
    transition: color .3s ease, border-color .3s ease;
}
.menu-card:hover .menu-card__cta { color: var(--gold-soft); border-top-color: rgba(244,239,230,.2); }
.menu-card__cta-arrow {
    display: inline-flex;
    transition: transform .3s var(--ease-smooth);
    color: var(--copper);
}
.menu-card:hover .menu-card__cta-arrow {
    transform: translateX(8px);
    color: var(--gold-soft);
}

@media (max-width: 900px) {
    .menu-grid { grid-template-columns: 1fr; }
    .menu-card { border-right: none !important; min-height: 280px; padding: 36px 28px; }
    .menu-card__body h3 { font-size: 26px; }
}

/* ---------- Showcase ---------- */
.showcase { margin: 0 0 140px; }
.showcase__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-auto-rows: 320px;
    gap: 12px;
}
.showcase__item {
    position: relative;
    overflow: hidden;
    border-radius: 4px;
    background: var(--canvas-deep);
}
.showcase__item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
    transition: transform .5s var(--ease-smooth);
}
.showcase__item:hover img { transform: scale(1.05); }
.showcase__item::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 50%, rgba(24,24,27,.8) 100%);
    opacity: 0;
    transition: opacity .3s ease;
}
.showcase__item:hover::after { opacity: 1; }
.showcase__caption {
    position: absolute;
    left: 20px; bottom: 20px;
    color: var(--canvas);
    z-index: 2;
    transform: translateY(10px);
    opacity: 0;
    transition: all .3s var(--ease-smooth);
}
.showcase__item:hover .showcase__caption {
    transform: translateY(0);
    opacity: 1;
}
.showcase__caption-num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: .2em;
    color: var(--copper-glow);
    margin-bottom: 6px;
    display: block;
}
.showcase__caption-title {
    font-family: 'Fraunces', serif;
    font-style: italic;
    font-size: 18px;
    font-weight: 400;
}



@media (max-width: 900px) {
    .showcase__grid { grid-template-columns: repeat(2, 1fr); grid-auto-rows: 220px; }
}

/* ---------- Philosophy ---------- */
.philosophy {
    padding: 100px 0 140px;
    border-top: 1px solid var(--line);
    border-bottom: 1px solid var(--line);
    position: relative;
}
.philosophy__inner {
    max-width: 1000px;
    margin: 0 auto;
    text-align: center;
    padding: 0 48px;
}
.philosophy__eyebrow {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: .3em;
    text-transform: uppercase;
    color: var(--copper);
    margin: 0 0 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
}
.philosophy__eyebrow::before,
.philosophy__eyebrow::after {
    content: "";
    width: 60px; height: 1px;
    background: var(--copper);
    opacity: .4;
}
.philosophy__quote {
    font-family: 'Fraunces', serif;
    font-weight: 300;
    font-style: italic;
    font-size: clamp(32px, 4.5vw, 64px);
    line-height: 1.15;
    letter-spacing: -.02em;
    color: var(--ink);
    margin: 0 0 40px;
}
.philosophy__quote em {
    color: var(--copper);
    font-weight: 400;
    position: relative;
}
.philosophy__quote em::after {
    content: "";
    position: absolute;
    left: 0; right: 0; bottom: 4px;
    height: 2px;
    background: linear-gradient(90deg, var(--copper-glow), transparent);
}
.philosophy__text {
    font-family: 'Inter', sans-serif;
    font-size: 16px;
    line-height: 1.8;
    color: var(--ink-soft);
    font-weight: 300;
    max-width: 60ch;
    margin: 0 auto 48px;
}
.philosophy__signature {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 24px;
    padding-top: 40px;
    border-top: 1px solid var(--line);
    max-width: 420px;
    margin: 0 auto;
}
.philosophy__signature-line {
    flex: 1;
    height: 1px;
    background: var(--line-strong);
}
.philosophy__signature-text {
    font-family: 'Fraunces', serif;
    font-style: italic;
    font-size: 20px;
    color: var(--ink);
    letter-spacing: -.01em;
}
.philosophy__signature-text em { color: var(--copper); }
.philosophy__signature-role {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9.5px;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: var(--ink-muted);
    margin-top: 6px;
    display: block;
    text-align: center;
}

/* ---------- CTA Strip ---------- */
.cta-strip {
    padding: 120px 48px;
    background: var(--ink);
    color: var(--canvas);
    text-align: center;
    position: relative;
    overflow: hidden;
}
.cta-strip::before {
    content: "";
    position: absolute;
    top: 50%; left: 50%;
    width: 800px; height: 800px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(217,89,58,.18) 0%, transparent 60%);
    transform: translate(-50%, -50%);
    pointer-events: none;
}
.cta-strip__inner {
    position: relative;
    z-index: 1;
    max-width: 800px;
    margin: 0 auto;
}
.cta-strip__label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: .3em;
    text-transform: uppercase;
    color: var(--gold-soft);
    margin: 0 0 24px;
}
.cta-strip h2 {
    font-family: 'Fraunces', serif;
    font-weight: 300;
    font-style: italic;
    font-size: clamp(40px, 6vw, 80px);
    line-height: 1.05;
    letter-spacing: -.03em;
    margin: 0 0 32px;
}
.cta-strip h2 em {
    color: var(--copper-glow);
    font-weight: 400;
}
.cta-strip p {
    font-size: 16px;
    color: rgba(244,239,230,.7);
    line-height: 1.7;
    margin: 0 0 40px;
    font-weight: 300;
}
.cta-strip .btn--primary {
    background: var(--canvas);
    color: var(--ink);
}
.cta-strip .btn--primary:hover { background: var(--copper); color: var(--canvas); }

/* ---------- Footer ---------- */
.public-footer {
    padding: 80px 48px 40px;
    background: var(--canvas);
    border-top: 1px solid var(--line);
}
.footer-inner {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr 1fr;
    gap: 60px;
    padding-bottom: 60px;
    border-bottom: 1px solid var(--line);
}
.footer-brand h4 {
    font-family: 'Fraunces', serif;
    font-weight: 300;
    font-style: italic;
    font-size: 48px;
    letter-spacing: -.03em;
    color: var(--ink);
    margin: 0 0 20px;
    line-height: 1;
}
.footer-brand h4 em { color: var(--copper); font-weight: 400; }
.footer-brand p {
    font-size: 14px;
    color: var(--ink-soft);
    line-height: 1.7;
    max-width: 36ch;
    font-weight: 300;
    margin: 0 0 24px;
}
.footer-brand__social {
    display: flex;
    gap: 12px;
}
.footer-brand__social a {
    width: 38px; height: 38px;
    border: 1px solid var(--line-strong);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ink-soft);
    text-decoration: none;
    transition: background .3s ease, color .3s ease, border-color .3s ease;
}
.footer-brand__social a:hover {
    background: var(--ink);
    color: var(--canvas);
    border-color: var(--ink);
}
.footer-brand__social svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.8; }

.footer-col h5 {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10.5px;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: var(--ink);
    font-weight: 600;
    margin: 0 0 24px;
}
.footer-col a {
    display: block;
    font-size: 14px;
    color: var(--ink-soft);
    text-decoration: none;
    padding: 8px 0;
    transition: color .3s ease, padding-left .3s ease;
    font-weight: 400;
}
.footer-col a:hover { color: var(--copper); padding-left: 6px; }

.footer-col__contact {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 16px;
}
.footer-col__contact svg {
    width: 16px; height: 16px;
    stroke: var(--copper);
    fill: none;
    stroke-width: 1.8;
    flex-shrink: 0;
    margin-top: 3px;
}
.footer-col__contact span {
    font-size: 13.5px;
    color: var(--ink-soft);
    line-height: 1.6;
}
.footer-col__contact strong {
    display: block;
    color: var(--ink);
    font-weight: 500;
    margin-top: 2px;
}

.footer-bottom {
    max-width: 1400px;
    margin: 0 auto;
    padding-top: 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10.5px;
    letter-spacing: .14em;
    color: var(--ink-muted);
    text-transform: uppercase;
}
.footer-bottom__mark {
    display: flex;
    align-items: center;
    gap: 10px;
}
.footer-bottom__mark::before {
    content: "";
    width: 6px; height: 6px;
    background: var(--copper);
    border-radius: 50%;
    animation: breathe 2.4s ease-in-out infinite;
}

@media (max-width: 900px) {
    .public-footer { padding: 60px 24px 32px; }
    .footer-inner { grid-template-columns: 1fr 1fr; gap: 40px; }
    .footer-brand { grid-column: 1 / -1; }
    .cta-strip { padding: 80px 24px; }
}
@media (max-width: 540px) {
    .footer-inner { grid-template-columns: 1fr; }
}

/* ---------- Scroll Reveal ---------- */
.reveal {
    opacity: 0;
    transform: translate3d(0,30px,0);
    transition: opacity .6s var(--ease-smooth), transform .6s var(--ease-smooth);
}
.reveal.is-visible {
    opacity: 1;
    transform: translate3d(0,0,0);
}
.reveal-delay-1 { transition-delay: .05s; }
.reveal-delay-2 { transition-delay: .1s; }
.reveal-delay-3 { transition-delay: .15s; }
.reveal-delay-4 { transition-delay: .2s; }

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: .01ms !important;
        transition-duration: .01ms !important;
    }
    .reveal { opacity: 1; transform: none; }
}
</style>
</head>
<body>

<div class="scroll-progress" id="scrollProgress"></div>

<!-- ========== NAV ========== -->
<nav class="site-nav" id="siteNav">
    <a href="dashboard.php" class="site-nav__brand">
        <img src="../assets/img/logo.svg" alt="CD 133 Production" class="site-nav__mark">
        <span class="site-nav__label">
            <strong>CD 133 Production</strong>
            Atelier · Est. 2018
        </span>
    </a>
    <div class="site-nav__right">
        <ul class="site-nav__links">
            <li><a href="dashboard.php" class="is-active">Beranda</a></li>
            <li><a href="katalog.php">Katalog</a></li>
            <li><a href="pesan.php">Pesan</a></li>
            <li><a href="lacak_pesanan.php">Lacak</a></li>
        </ul>
        <a href="pesan.php" class="site-nav__cta"><span>Pesan Sekarang →</span></a>
        <button class="site-nav__menu-btn" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- ========== HERO ========== -->
<div class="hero-wrap">
    <div class="hero-slideshow" id="heroSlideshow">
        <img src="../assets/img/gambar1.jpg" alt="Kaos custom" class="is-active">
        <img src="../assets/img/gambar2.png" alt="Jaket hoodie" loading="lazy">
        <img src="../assets/img/gambar3.png" alt="Kemeja kerja" loading="lazy">
        <img src="../assets/img/gambar4.png" alt="Seragam" loading="lazy">
        <img src="../assets/img/gambar5.png" alt="Kaos custom" loading="lazy">
        <img src="../assets/img/gambar6.png" alt="Jaket" loading="lazy">
    </div>
    <div class="hero-overlay"></div>

    <div class="hero">
        <div class="hero__top">
            <div class="hero__eyebrow">
                <span class="hero__eyebrow-dot"></span>
                <span>Atelier Konveksi Custom</span>
            </div>
            <div class="hero__meta">
                <strong>CD 133 Production</strong>
                Bandung · Indonesia<br>
                Since 2018
            </div>
        </div>

        <div class="hero__center">
            <div class="hero__label">
                Koleksi Custom · Jahitan Presisi
            </div>
            <h1 class="hero__title">
                <span class="hero__title-line"><span>Setiap</span></span>
                <span class="hero__title-line"><span><em>jahitan</em></span></span>
                <span class="hero__title-line"><span>menceritakan kisah.</span></span>
            </h1>
            <p class="hero__desc">
                Dari kaos hingga seragam — setiap pesanan dibuat sesuai desain Anda,
                dipantau secara real-time, dan dijahit dengan ketelitian seorang penjahit ahli.
            </p>
            <div class="hero__actions">
                <a href="pesan.php" class="btn btn--primary">
                    <span>Mulai Pesanan</span>
                    <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <a href="katalog.php" class="btn btn--ghost">Lihat Katalog</a>
            </div>
        </div>

        <div class="hero__bottom">
            <div class="hero__stats">
                <div class="hero__stat">
                    <span class="hero__stat-label">Since</span>
                    <span class="hero__stat-value"><em>'</em>18</span>
                </div>
                <div class="hero__stat">
                    <span class="hero__stat-label">Crafted</span>
                    <span class="hero__stat-value">13,300<em>+</em></span>
                </div>
                <div class="hero__stat">
                    <span class="hero__stat-label">Rating</span>
                    <span class="hero__stat-value">4.9<em>★</em></span>
                </div>
            </div>

            <div class="hero__scroll">
                <span>Scroll</span>
                <div class="hero__scroll-line"></div>
            </div>

            <div class="hero__dots" id="heroDots"></div>
        </div>
    </div>
</div>

<!-- ========== MARQUEE ========== -->
<div class="marquee" aria-hidden="true">
    <div class="marquee__track">
        <span>Kaos Custom <em>◆ 01</em></span>
        <span>Jaket Hoodie <em>◆ 02</em></span>
        <span>Kemeja Kerja <em>◆ 03</em></span>
        <span>Seragam <em>◆ 04</em></span>
        <span>Kaos Custom <em>◆ 01</em></span>
        <span>Jaket Hoodie <em>◆ 02</em></span>
        <span>Kemeja Kerja <em>◆ 03</em></span>
        <span>Seragam <em>◆ 04</em></span>
    </div>
</div>

<div class="public-wrap">

    <!-- ========== MENU ========== -->
    <div class="section-title reveal">
        <div class="section-title__num">01</div>
        <div class="section-title__content">
            <h2>Pilih perjalanan <em>Anda.</em></h2>
            <p>// Empat pintu menuju koleksi custom impian Anda.</p>
        </div>
    </div>

    <div class="menu-grid">
        <a href="pesan.php" class="menu-card reveal reveal-delay-1">
            <div class="menu-card__bg" style="background-image:url('../assets/img/produk.png')"></div>
            <div class="menu-card__top">
                <span class="menu-card__num">— 01</span>
                <span class="menu-card__icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                </span>
            </div>
            <div class="menu-card__body">
                <h3>Pesan Produk</h3>
                <p>Rancang pesanan custom Anda — pilih bahan, warna, ukuran, dan desain sesuai imajinasi.</p>
            </div>
            <span class="menu-card__cta">
                Mulai Pesanan
                <span class="menu-card__cta-arrow">→</span>
            </span>
        </a>

        <a href="lacak_pesanan.php" class="menu-card reveal reveal-delay-2">
            <div class="menu-card__bg" style="background-image:url('../assets/img/lacak.png')"></div>
            <div class="menu-card__top">
                <span class="menu-card__num">— 02</span>
                <span class="menu-card__icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                </span>
            </div>
            <div class="menu-card__body">
                <h3>Lacak Pesanan</h3>
                <p>Pantau progress produksi Anda secara real-time — dari pola, jahit, hingga siap kirim.</p>
            </div>
            <span class="menu-card__cta">
                Lacak Sekarang
                <span class="menu-card__cta-arrow">→</span>
            </span>
        </a>

        <a href="katalog.php" class="menu-card reveal reveal-delay-3">
            <div class="menu-card__bg" style="background-image:url('../assets/img/katalog.png')"></div>
            <div class="menu-card__top">
                <span class="menu-card__num">— 03</span>
                <span class="menu-card__icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                </span>
            </div>
            <div class="menu-card__body">
                <h3>Katalog Produk</h3>
                <p>Jelajahi koleksi dan hasil produksi sebelumnya — temukan inspirasi untuk pesanan Anda.</p>
            </div>
            <span class="menu-card__cta">
                Jelajahi Katalog
                <span class="menu-card__cta-arrow">→</span>
            </span>
        </a>

        <a href="https://wa.me/6281234567890" target="_blank" rel="noopener" class="menu-card reveal reveal-delay-4">
            <div class="menu-card__bg" style="background-image:url('../assets/img/whatsapp.png')"></div>
            <div class="menu-card__top">
                <span class="menu-card__num">— 04</span>
                <span class="menu-card__icon">
                    <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </span>
            </div>
            <div class="menu-card__body">
                <h3>Hubungi Kami</h3>
                <p>Konsultasi desain atau tanyakan detail — tim kami siap membantu lewat WhatsApp.</p>
            </div>
            <span class="menu-card__cta">
                Chat WhatsApp
                <span class="menu-card__cta-arrow">→</span>
            </span>
        </a>
    </div>

    <!-- ========== SHOWCASE ========== -->
    <div class="section-title reveal">
        <div class="section-title__num">02</div>
        <div class="section-title__content">
            <h2>Dari <em>atelier</em> kami.</h2>
            <p>// Potongan terbaru dari tangan-tangan ahli kami.</p>
        </div>
    </div>

    <div class="showcase reveal">
        <div class="showcase__grid">
            <div class="showcase__item">
                <img src="../assets/img/gambar_hasil1.jpg" alt="Hasil produksi 1" loading="lazy">
                <div class="showcase__caption">
                    <span class="showcase__caption-num">N°01</span>
                    <span class="showcase__caption-title">Kaos Oversize</span>
                </div>
            </div>
            <div class="showcase__item">
                <img src="../assets/img/gambar_hasil2.jpg" alt="Hasil produksi 2" loading="lazy">
                <div class="showcase__caption">
                    <span class="showcase__caption-num">N°02</span>
                    <span class="showcase__caption-title">Hoodie Fleece</span>
                </div>
            </div>
            <div class="showcase__item">
                <img src="../assets/img/gambar_hasil3.jpg" alt="Hasil produksi 3" loading="lazy">
                <div class="showcase__caption">
                    <span class="showcase__caption-num">N°03</span>
                    <span class="showcase__caption-title">Kemeja Kerja</span>
                </div>
            </div>
            <div class="showcase__item">
                <img src="../assets/img/gambar_hasil4.jpg" alt="Hasil produksi 4" loading="lazy">
                <div class="showcase__caption">
                    <span class="showcase__caption-num">N°04</span>
                    <span class="showcase__caption-title">Seragam Kantor</span>
                </div>
            </div>
            <div class="showcase__item">
                <img src="../assets/img/gambar_hasil5.jpg" alt="Hasil produksi 5" loading="lazy">
                <div class="showcase__caption">
                    <span class="showcase__caption-num">N°05</span>
                    <span class="showcase__caption-title">Polo Premium</span>
                </div>
            </div>
            <div class="showcase__item">
                <img src="../assets/img/gambar_hasil6.jpg" alt="Hasil produksi 6" loading="lazy">
                <div class="showcase__caption">
                    <span class="showcase__caption-num">N°06</span>
                    <span class="showcase__caption-title">Varsity Jacket</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== PHILOSOPHY ========== -->
<div class="philosophy reveal">
    <div class="philosophy__inner">
        <p class="philosophy__eyebrow">Filosofi Kami</p>
        <h2 class="philosophy__quote">
            Bukan sekadar pakaian —<br>
            tapi <em>identitas</em> yang dijahit<br>
            dengan ketelitian dan rasa.
        </h2>
        <p class="philosophy__text">
            CD 133 Production adalah atelier konveksi yang mengurus seluruh proses garment custom,
            dari konsultasi desain hingga pengiriman. Sistem kami dibuat agar Anda bisa memesan
            dan memantau setiap tahap produksi tanpa repot — cukup pantau dari layar Anda.
        </p>
        <div class="philosophy__signature">
            <div class="philosophy__signature-line"></div>
            <div>
                <div class="philosophy__signature-text"><em>CD 133</em> Production</div>
                <span class="philosophy__signature-role">Atelier · Est. 2018</span>
            </div>
            <div class="philosophy__signature-line"></div>
        </div>
    </div>
</div>

<!-- ========== CTA ========== -->
<div class="cta-strip reveal">
    <div class="cta-strip__inner">
        <p class="cta-strip__label">Siap Memulai?</p>
        <h2>Wujudkan desain <em>impian</em> Anda hari ini.</h2>
        <p>Konsultasi gratis. Proses transparan. Hasil memuaskan.</p>
        <a href="pesan.php" class="btn btn--primary">
            <span>Mulai Pesanan</span>
            <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    </div>
</div>

<!-- ========== FOOTER ========== -->
<footer class="public-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <h4>CD <em>133</em></h4>
            <p>Atelier konveksi custom — di mana setiap jahitan membawa cerita, setiap pesanan dipantau dari awal hingga akhir.</p>
            <div class="footer-brand__social">
                <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".5" fill="currentColor"/></svg></a>
                <a href="#" aria-label="TikTok"><svg viewBox="0 0 24 24"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg></a>
                <a href="#" aria-label="WhatsApp"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></a>
            </div>
        </div>

        <div class="footer-col">
            <h5>Navigasi</h5>
            <a href="dashboard.php">Beranda</a>
            <a href="katalog.php">Katalog</a>
            <a href="pesan.php">Pesan</a>
            <a href="lacak_pesanan.php">Lacak Pesanan</a>
        </div>

        <div class="footer-col">
            <h5>Layanan</h5>
            <a href="katalog.php">Kaos Custom</a>
            <a href="katalog.php">Jaket Hoodie</a>
            <a href="katalog.php">Kemeja Kerja</a>
            <a href="katalog.php">Seragam</a>
        </div>

        <div class="footer-col">
            <h5>Kontak</h5>
            <div class="footer-col__contact">
                <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <span>WhatsApp<br><strong>+62 812-3456-7890</strong></span>
            </div>
            <div class="footer-col__contact">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span>Jam Operasional<br><strong>Sen—Sab · 09:00—18:00 WIB</strong></span>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <span>© 2026 CD 133 Production · All Rights Reserved</span>
        <span class="footer-bottom__mark">Handcrafted in Bandung</span>
    </div>
</footer>

<script>
(function() {
    'use strict';

    /* ========== Scroll Progress ========== */
    var progressBar = document.getElementById('scrollProgress');
    var ticking = false;

    function updateProgress() {
        var scrollTop = window.pageYOffset;
        var docHeight = document.documentElement.scrollHeight - window.innerHeight;
        var progress = docHeight > 0 ? scrollTop / docHeight : 0;
        progressBar.style.transform = 'scaleX(' + progress + ')';
    }

    /* ========== Nav Scroll State ========== */
    var nav = document.getElementById('siteNav');

    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                updateProgress();
                if (window.pageYOffset > 40) nav.classList.add('is-scrolled');
                else nav.classList.remove('is-scrolled');
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    /* ========== Hero Slideshow ========== */
    var slides = document.querySelectorAll('#heroSlideshow img');
    var dotsWrap = document.getElementById('heroDots');
    var current = 0;

    function goToSlide(idx) {
        slides[current].classList.remove('is-active');
        if (dotsWrap && dotsWrap.children[current]) dotsWrap.children[current].classList.remove('is-active');
        current = (idx + slides.length) % slides.length;
        slides[current].classList.add('is-active');
        if (dotsWrap && dotsWrap.children[current]) dotsWrap.children[current].classList.add('is-active');
    }

    if (dotsWrap && slides.length > 1) {
        slides.forEach(function(_, i) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.setAttribute('aria-label', 'Slide ' + (i + 1));
            if (i === 0) dot.classList.add('is-active');
            dot.addEventListener('click', function() {
                goToSlide(i);
                clearInterval(timer);
                timer = setInterval(nextSlide, 6000);
            });
            dotsWrap.appendChild(dot);
        });
    }

    function nextSlide() { goToSlide(current + 1); }
    var timer = slides.length > 1 ? setInterval(nextSlide, 6000) : null;

    document.addEventListener('visibilitychange', function() {
        if (timer !== null || slides.length > 1) {
            if (document.hidden) {
                clearInterval(timer);
                timer = null;
            } else if (!timer) {
                timer = setInterval(nextSlide, 6000);
            }
        }
    });

    /* ========== Scroll Reveal ========== */
    var reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
        reveals.forEach(function(el) { io.observe(el); });
    } else {
        reveals.forEach(function(el) { el.classList.add('is-visible'); });
    }

})();
</script>
</body>
</html>
