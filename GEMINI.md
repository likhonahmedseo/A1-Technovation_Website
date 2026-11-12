# A1 Technovation - AI Assistant Workspace Instructions

## 1. Global Brand & Color Theme Rules (STRICT MANDATE)
- **Always keep the color theme consistent across all blog posts, technical guides, landing pages, and service pages to match the official A1 Technovation Home Page brand theme.**
- **NEVER** switch to custom, novelty, or niche-specific color themes for individual pages (e.g. DO NOT use green/emerald for gaming/Minecraft, orange for HVAC, red for auto, etc.).
- All pages must strictly use the unified A1 Technovation brand palette:
  - **Primary & Accent Colors**: Royal Indigo (`#4f46e5`), Electric Blue (`#2563eb`, `text-blue-600`), Vibrant Cyan (`#06b6d4`, `text-cyan-400`), and Midnight Slate (`#060b18`, `#0f172a`, `#1e293b`).
  - **Hero & Dark Sections**: `linear-gradient(145deg, #060b18 0%, #0d1526 55%, #160826 100%)` or `linear-gradient(160deg, #090d16 0%, #0f172a 45%, #172554 100%)`.
  - **Hero Glows**: `radial-gradient(circle, rgba(37, 99, 235, 0.2) 0%, rgba(6, 182, 212, 0.05) 50%, transparent 70%)`.
  - **Text Selection**: `selection:bg-blue-200 selection:text-slate-900`.
  - **Buttons & CTAs**: `linear-gradient(135deg, #2563eb, #0ea5e9)` or `linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%)`.
  - **Reading Progress Bar**: `linear-gradient(90deg, #2563eb, #06b6d4)`.
  - **Badges**: `bg-blue-900/60 text-cyan-300 border-cyan-500/30` or `bg-blue-50 text-blue-600 border-blue-200`.
  - **Infographics / SVGs**: Use dark slate cards (`#0f172a`), borders (`#1e293b`), primary nodes in blue (`#3b82f6`) and cyan (`#06b6d4`), and secondary accents in indigo/purple (`#818cf8`, `#c084fc`). Semantic red (`#ef4444`) is reserved exclusively for errors/hazards, and emerald green (`#10b981`) exclusively for success/healthy checks.

## 2. Global Navigation, Header & Footer Standards (PERMANENT / LIFETIME MANDATE)
- **STRICT MANDATE: Always use the EXACT global header, mobile menu, and global footer directly from the official Home Page (`index.html`) on every single page, blog post, technical guide, and landing page.**
- **Header (`<nav class="navbar" id="navbar">`)**:
  - NEVER use `.article-navbar` or custom inline overrides that hardcode static white backgrounds or break scroll transitions.
  - The navbar MUST start with class `navbar` (and `id="navbar"`), allowing native dynamic behavior: transparent over dark hero sections with the dark logo (`.logo-brand-dark`), and automatically transitioning to frosted glass with the light logo (`.logo-brand-light`) on scroll (`.navbar.scrolled`).
  - Always use root-relative image paths (`/assets/images/A1Technovation-Dark-Background.png` and `/assets/images/A1Technovation-Light-Background.png`).
  - Must include the exact `.navbar-inner`, desktop Services megamenu (`.megamenu`), and `.navbar-right` container with the Free Consultation button and mobile toggle.
- **Mobile Menu (`<div class="mobile-menu" id="mobileMenu">`)**:
  - Must match the exact structure, links, submenus, and consultation actions from `index.html`.
- **Footer (`<footer class="footer">`)**:
  - Must match `index.html` 1:1:
    - `.footer-brand` with logo, description, and `.footer-socials` containing `.f-social` buttons (Facebook, Instagram, LinkedIn, WhatsApp, Telegram).
    - `.footer-col` for Services with `.footer-links` and `.f-link`.
    - `.footer-col` for Company with `.footer-links` and `.f-link`.
    - `.footer-col` for Get in Touch with `.f-contact-item` (address, phone, email, hours).
    - Full interactive `.footer-newsletter` section with the official newsletter subscription form (`/api/newsletter_subscribe`).
    - `.footer-bottom` with `.f-copy` and `.f-bottom-links` (`.f-bl`).
- **Core Stylesheets**:
  - Always link the primary global stylesheets:
    - `<link rel="stylesheet" href="/css/style.css">`
    - `<link rel="stylesheet" href="/css/responsive.css">`
    - `<link rel="stylesheet" href="/css/whatsapp-chat.css">`
  - Do NOT load stale or outdated `.min.css` files that lack the latest global header/footer CSS rules.
- **Core Scripts**:
  - Always include `/js/main.min.js` and `/js/whatsapp-chat.js` with `defer` at the bottom of the page.

## 3. High-Contrast Dark CTA Cards
- All `.article-cta-card` elements on dark backgrounds must explicitly enforce high-contrast text (`color: #f8fafc !important;`) on all headings, paragraph copies, and links. Never leave paragraph text inheriting dark `.prose` cascade colors.

## 4. Image Optimization Rule
- **Always optimize image sizes.**
- Never leave heavy uncompressed images (>150KB) on pages.
- Always convert and compress blog hero covers and diagrams to WebP format (`quality: 80-85`, width: ~1200px) targeting 50KB–90KB.

