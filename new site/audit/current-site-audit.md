# The Money Club Current-Site Audit

Audit date: June 14, 2026

Scope: `/Users/jaredgoldberg/Desktop/The-Money-Club/new site`

Status: Audit complete for source structure, routes, CSS, components, forms, CTAs,
navigation, interaction scripts, and responsive rules. No V2 pages have been built.

## Audit Constraints

- The current site is a flat HTML/PHP site. There is no package manifest, frontend
  framework, templating system, component compiler, or automated test setup in this
  directory.
- Existing uncommitted work under `learn/`, `assets/learn-catalogue.css`, and
  `video-instruction/` was treated as user-owned work and was not changed.
- The in-app browser automation runtime was unavailable, so responsive findings are
  based on source inspection rather than live viewport screenshots. Desktop/mobile
  visual regression testing remains a required pre-build gate.
- No file, route, historical filename, text reference, sitemap entry, or indexed
  production result named "end decks" was found. The URL or file path must be supplied
  during review. The closest current reference patterns are the end-of-page interest
  forms, "Learn More" blocks, "Choose Your Adventure" links, info-session modal, and
  registration CTA deck.

## Executive Summary

The current site works as a collection of independently assembled pages, not as a
shared application. The visual language is recognizable and reusable, but most of it
is copied into page-local `<style>` and `<script>` blocks.

Key measurements:

- 41 HTML files in the production tree, excluding `video-instruction/index.html`
- 17 root PHP files
- 26 URLs in `sitemap.xml`
- 38 HTML files with inline style blocks
- 36 pages loading `assets/typography.css`
- 35 pages loading `assets/site-menu.css`
- 18 pages loading `assets/form-protection.js`
- Approximately 1.64 MB of inline CSS and 0.50 MB of inline JavaScript
- 33 copies of the same GTM loader
- 29 copies of the same CTA tracking block
- 28 menu-script copies split between at least two implementations
- Four navigation-content variants
- Four pairs of byte-identical image assets

The correct V2 strategy is a separate application and deployment artifact. Do not
refactor the production files in place as the first step.

## 1. Current-Site Structure Map

### Runtime and deployment

- Apache-style static/PHP hosting is implied by `.htaccess`.
- HTML pages are direct routes ending in `.html`, except directory-index learning
  routes.
- PHP handles form submission, email, registration state, and availability.
- Google Tag Manager is embedded directly in most pages.
- Google Fonts loads Raleway and Bebas Neue directly from Google.
- Redirects are handled with `.htaccess` and small redirect HTML/PHP files.

### Primary public marketing routes

| Route | Purpose |
| --- | --- |
| `/` (`index.html`) | Main program landing page |
| `/curriculum-details.html` | Curriculum and schedule |
| `/how-it-works.html` | Program process |
| `/open-book-hook.html` | Open-book financial model |
| `/who-runs-it.html` | Team and leadership |
| `/schedule-pricing.html` | Maker Market and program details |
| `/reserve-a-spot.html` | Registration entry flow |
| `/faq.html` | FAQ and interest form |
| `/contact-us.html` | Contact form |

### Instructor and mission routes

| Route | Purpose |
| --- | --- |
| `/instructors.html` | Instructor recruitment overview |
| `/instructors-learn-more.html` | Instructor details |
| `/instructor-apply.html` | Instructor application |
| `/support-our-mission.html` | Mission-support contact form |
| `/executive-director-letter.html` | Executive director letter |

### Learning catalogue

| Route | Purpose |
| --- | --- |
| `/learn/` | Catalogue containing all module sections |
| `/learn/financial-literacy-for-young-entrepreneurs/` | Financial literacy module |
| `/learn/financial-literacy-for-young-entrepreneurs/worksheet.html` | Interactive worksheet |
| `/learn/financial-literacy-for-young-entrepreneurs/video-lesson.html` | Audio/word lesson, currently untracked |

The learning hub is not yet a route-per-module system. Ten mobile-menu items point to
the same `/learn/index.html` destination rather than distinct module routes.

### SEO article routes

Eight near-template-identical guide pages exist:

- `article-best-summer-camps.html`
- `article-econ-summer-camps.html`
- `article-entrepreneurship-camps-toronto.html`
- `article-financial-literacy-camps.html`
- `article-older-kids-summer-camps.html`
- `article-stem-summer-camps.html`
- `article-teens-summer-camps.html`
- `article-uni-summer-camps.html`

These are strong candidates for one article template plus structured content.

### Legal, payment, and confirmation routes

| Route | Purpose |
| --- | --- |
| `/program-terms.html` | Program terms |
| `/privacy-policy.html` | Privacy policy |
| `/parent-approval.html` | Detailed consent form |
| `/etransfer.html` | Payment instructions |
| `/thank-you.html` | Main confirmation |
| `/thank-u-contact.html` | Contact confirmation variant that still contains a form |

### Redirects, alternates, and likely legacy files

- `.htaccess` redirects `curriculum.html`, `terms.html`, `privacy.html`, and
  `learn/index2.html`.
- Stripe routes currently redirect to e-transfer.
- `index-03-26.html` and `index-04-03.html` are dated homepage snapshots.
- `pricing.html` is an alternate landing/registration page but declares the homepage
  canonical URL.
- `who-runs-it2.html` declares `who-runs-it.html` as canonical.
- `thank-you-credit-card.html` and `.php` are redirect stubs.
- `video-instruction/` is a 315 MB working directory containing a local virtual
  environment, generated media/alignment files, and cache output. It should not be
  part of a deployable web root.

### Backend endpoints and contracts

| Endpoint | Responsibility |
| --- | --- |
| `apply-interest.php` | Parent interest form and confirmation email |
| `apply-contact.php` | Contact form |
| `apply-our-mission.php` | Mission-support form |
| `apply-instructor.php` | Instructor application and confirmation |
| `start-parent-approval.php` | Registration intake and parent-approval prefill |
| `apply-parent-approval.php` | Consent submission and email |
| `apply-camper.php` | Registration, availability update, and email |
| `availability.php` | JSON availability response/update |
| `parent-approval-prefill.php` | Prefill JSON |
| `form-security.php` | Return URL allowlists, CSRF, honeypot, rate limiting, logging |
| `smtp-send.php` | SMTP with native-mail fallback |

The PHP files could not be syntax-checked because PHP is not installed in the local
environment. Their contracts were inspected statically.

## 2. CSS and Design-System Audit

### Existing foundation

The site already has the beginnings of a design system:

- Body font: Raleway
- Display font: Bebas Neue
- Core colors: teal `#1e8a95`, purple `#8c62a7`, gold `#f0b43a`
- Supporting colors: rose, slate, ink, muted text, off-white borders/backgrounds
- Common max widths: 1200 px and 1320 px
- Spacing variables: 6, 10, 16, 24, 36, and 56 px
- Radius variables: 10, 16, and 22 px
- Standard breakpoints: 640, 768, 900, 980/981, 1024, 1100, 1180, and 1440 px
- Recurring primitives: `.wrap`, `.section`, `.section-banner`, `.split`, `.btn`,
  `.field`, `.form-grid`, cards, banners, and media containers

### Shared styles

- `assets/typography.css` owns typography plus unrelated form-loading, form-hiding,
  registration-card, and mobile topbar behavior.
- `assets/site-menu.css` only owns the Learning Catalogue branch inside the overlay
  menu; the rest of the menu remains duplicated inline.
- `assets/learn-catalogue.css` owns catalogue sidebar/layout overrides.
- `assets/worksheet.css` is the most self-contained feature stylesheet.

### Cascade conflicts

The external stylesheets load after each page's inline styles, so shared CSS silently
overrides page-local intent:

- Pages define `--lh-heading: 1.05` or `0.95`; `typography.css` later changes it to
  `0.8`.
- Pages set mobile topbar heights to 68 px and then 64 px at 640 px;
  `typography.css` later sets `--topbar-height: 51px` for all widths up to 980 px.
- Pages define heading sizes locally; `typography.css` later resets all `h1`, `h2`,
  and `h3` to 26.5 px on mobile, followed by special-case section-heading rules.
- `typography.css` contains layout and product-state rules, so a typography version
  change can alter forms, cards, registration visibility, and navigation geometry.

### Design-system quality

What should be retained:

- Teal/purple/gold identity
- Bebas display type with Raleway body type
- Large, high-contrast CTA treatment
- Colored full-width section banners
- Strong card, split-layout, and image-led storytelling patterns
- Explicit form errors, busy states, and confirmation states

What needs formal definition:

- Semantic color roles, not color-named component classes
- One type scale with desktop and mobile tokens
- One spacing scale
- One radius and shadow scale
- One container/grid system
- One focus-ring standard
- One motion policy with reduced-motion coverage
- Button size, hierarchy, and state tokens
- Form control, label, help, error, success, and loading tokens

## 3. Reusable Component Inventory

### Global shell

- Site header
- Desktop primary navigation
- Mobile menu trigger and fullscreen menu
- Learning Catalogue disclosure tree
- Persistent Reserve a Spot CTA
- Brand/hero lockup
- Site footer
- GTM/analytics loader

### UI primitives

- Button/link button
- Standard CTA, large CTA, registration CTA, secondary info CTA
- Section container and full-bleed section
- Colored section banner
- Card, media card, confirmation card, and status card
- Responsive split/grid
- Image frame
- Badge/eyebrow
- Icon/text fact row
- Table wrapper
- Accordion/details item

### Marketing sections

- Program hero
- Program summary
- Build/process section
- Outcomes list
- Curriculum/module list
- Open-book pie/legend/table
- Team/profile section
- Location photo/map section
- Quick facts
- Camp-guide cards
- Choose Your Adventure link deck
- End-of-page Learn More / interest deck

### Forms and interactions

- Form field wrapper
- Text, email, phone, number, URL, select, textarea, radio, and checkbox controls
- Inline error state
- Form loading overlay/status
- Interest form
- Contact/mission form
- Instructor application
- Registration intake
- Parent approval form
- Info-session modal
- Availability counter
- Copy/share controls

### Content templates

- SEO article intro/meta/facts template
- Legal document template
- Learning catalogue shell/sidebar
- Learning module page
- Worksheet page and calculator
- Audio/word lesson player
- Confirmation page

### Consolidation priority

Build global shell, buttons, fields, cards, section banners, and the interest deck
first. They have the widest reuse and the most current drift.

## 4. Desktop and Mobile Navigation Audit

### Desktop

The dominant desktop navigation has four links:

- Curriculum
- Build & Sell
- Open Book Financials
- Who Runs It

It also has a separate Reserve a Spot CTA.

Findings:

- The logo/brand is generally in the page hero, not the fixed header, and is usually
  not a home link.
- Camp Guides, Learning Catalogue, FAQ, contact, and instructor content are absent
  from the dominant desktop navigation.
- Root pages use relative links while learning pages use root-relative links. This
  works today but creates unnecessary route-context differences.
- Legacy `index-03-26.html` and `who-runs-it2.html` contain materially different
  desktop menus.
- The current page is not marked with `aria-current`.

### Mobile

At 980 px and below, desktop `<nav>` is hidden and a hamburger opens a fullscreen
overlay. The menu adds Camp Guides and a Learning Catalogue disclosure with eleven
module links, plus Reserve a Spot.

Positive behavior:

- Menu state uses `aria-expanded`, `aria-controls`, and `aria-hidden`.
- The trigger supports Enter and Space.
- Escape and the close button dismiss the menu.
- The menu is scrollable.

Problems:

- The trigger is a `<div role="button">` rather than a native `<button>`.
- Focus is not trapped in the open overlay.
- Focus is not explicitly returned to the trigger on close.
- The underlying page is not consistently scroll-locked while the menu is open.
- Mobile and desktop information architecture differ substantially.
- Ten catalogue links lead to the same hub URL with no module anchor.
- `site-menu.css` only partially owns the menu, while base menu CSS and JS are copied
  into pages.
- The breakpoint is effectively 980/981 px, but menu-specific shared CSS also uses
  768 px, producing two layers of mobile behavior.

### Recommended V2 navigation

- One data-driven route model shared by desktop, mobile, footer, and sitemap.
- A linked brand mark in the header.
- Native button controls.
- Visible current-route state with `aria-current="page"`.
- The same primary hierarchy on desktop and mobile.
- A real Learning Catalogue submenu whose items have distinct routes.
- Focus trap, focus return, body scroll lock, Escape handling, and reduced motion.

## 5. Duplicate, Messy, or Conflicting Styles

1. Base resets, variables, typography, header, buttons, hero, footer, forms, and
   responsive rules are repeated across about 35 pages.
2. There are approximately 1.64 MB of inline CSS despite four shared CSS files.
3. `:root` variables are declared repeatedly and re-declared inside multiple media
   queries.
4. `typography.css` mixes typography, responsive header geometry, registration
   visibility, cards, loaders, and form state.
5. `.btn`, `.btn.cta-standard`, `.cta-large`, `.register-btn`, `.submit-btn`,
   `.info-session-trigger-btn`, `.payment-option`, and page-specific CTA selectors
   form overlapping button systems.
6. Components are styled by color class (`.purple`, `.teal`, `.gold`, `.plum`,
   `.primary`) rather than by semantic intent.
7. Mobile CSS contains repeated `@media (max-width: 980px)` blocks, repeated `:root`
   overrides, `!important`, and selector-specific corrections.
8. There are 234 `!important` occurrences and 52 inline `style` attributes.
9. The codebase uses many near-identical neutral colors and one-off values rather
   than a controlled palette.
10. `#ffffff`/`#fff`, `#111111`/`#111`, and similar duplicate color forms obscure
    token usage.
11. Heading rules conflict between page CSS and `typography.css`.
12. Mobile rules both hide and restyle `.hero-title`; the hidden state makes several
    later declarations ineffective.
13. `overflow-x: hidden` globally masks horizontal overflow instead of fixing its
    source.
14. The registration form is globally hidden through a body class and shared CSS,
    leaving inactive form code and styles in pages.
15. Eight article pages carry effectively the same template CSS and JS.
16. `learn/index.html` and `learn/index2.html` are nearly identical even though one is
    redirected.
17. Four pairs of image files are byte-identical under different filenames.
18. `video-instruction/` contains a 315 MB development environment inside the site
    directory.
19. Dated homepage variants and alternate canonical pages make it unclear which
    implementation is authoritative.
20. The Google Fonts URL contains raw `&` characters in HTML. Static validation also
    found encoding warnings; use UTF-8 consistently and escape query separators.

## 6. Recommended V2 Folder Architecture

Keep V2 beside the production directory, not inside it:

```text
The-Money-Club/
├── new site/                  # Existing production site; freeze except urgent fixes
└── v2-site/                   # New application and independent deployment root
    ├── package.json
    ├── astro.config.mjs
    ├── tsconfig.json
    ├── public/
    │   ├── fonts/
    │   ├── images/
    │   ├── icons/
    │   └── robots.txt
    ├── src/
    │   ├── pages/
    │   ├── layouts/
    │   ├── components/
    │   │   ├── ui/
    │   │   ├── navigation/
    │   │   ├── forms/
    │   │   ├── sections/
    │   │   ├── learning/
    │   │   └── analytics/
    │   ├── content/
    │   │   ├── pages/
    │   │   ├── articles/
    │   │   ├── modules/
    │   │   └── legal/
    │   ├── data/
    │   │   ├── navigation.ts
    │   │   ├── program.ts
    │   │   └── seo.ts
    │   ├── styles/
    │   │   ├── tokens.css
    │   │   ├── reset.css
    │   │   ├── global.css
    │   │   └── utilities.css
    │   └── lib/
    │       ├── analytics.ts
    │       ├── forms.ts
    │       └── routes.ts
    ├── server/
    │   └── php/               # V2-owned form/API implementation if PHP is retained
    ├── tests/
    │   ├── unit/
    │   ├── integration/
    │   ├── accessibility/
    │   └── visual/
    └── scripts/
        ├── migrate-content/
        └── validate-routes/
```

Astro is the recommended default because this is a content-heavy, SEO-sensitive site
with mostly static pages and a small number of interactive islands. It provides
layouts, typed content collections, reusable components, static output, and selective
client JavaScript without forcing a full SPA.

V2 must have:

- Its own package/dependency lockfile
- Its own deployment configuration
- Its own assets and generated output
- Its own form/API ownership or an explicit temporary adapter
- A staging hostname such as `v2.the-money-club.org`
- No imports, symlinks, or runtime dependencies on files in `new site/`

## 7. Recommended Execution Plan

### Gate 0: Review and reference resolution

- Review this audit.
- Supply the exact "end decks" URL/file and confirm whether it means an end-of-page
  CTA deck.
- Confirm the authoritative homepage and registration route.
- Confirm which legacy/alternate routes must remain reachable.
- Approve the V2 stack and separate deployment location.

No V2 page implementation should begin before Gate 0 is approved.

### Phase 1: Baseline capture

- Run the current site locally in a PHP-capable environment.
- Capture desktop and mobile screenshots at 1440, 1024, 980, 768, 390, and 320 px.
- Exercise menu, modal, interest form, registration, consent, availability, copy,
  share, worksheet, and audio-player interactions.
- Record current analytics events and form payload contracts.
- Run accessibility and HTML validation with HTML5-aware tooling.

### Phase 2: Information architecture and content model

- Approve the route map and redirects.
- Define primary, secondary, catalogue, footer, legal, and conversion navigation.
- Convert articles, modules, legal content, and repeated program facts to structured
  content/data.
- Decide which dated and alternate files are archive-only.

### Phase 3: V2 design system

- Approve tokens for color, type, spacing, radius, shadow, container, breakpoint,
  focus, and motion.
- Build and review primitives in isolation.
- Establish semantic CTA variants: primary, secondary, text, destructive, loading,
  disabled.
- Establish one form-field contract and one error/success/loading pattern.

### Phase 4: Shared shell and interaction prototypes

- Build header, desktop navigation, mobile menu, footer, analytics wrapper, section
  primitives, and the reviewed end-deck pattern.
- Test keyboard behavior, focus management, reduced motion, and screen-reader labels.
- Obtain review before composing full pages.

### Phase 5: Page-template migration

Recommended order:

1. Homepage
2. Curriculum / How It Works / Open Book / Who Runs It
3. Reserve and parent-approval flow
4. Learning hub, module, worksheet, and video lesson
5. Article template and eight guides
6. Instructor, contact, mission, legal, payment, and confirmation pages

### Phase 6: Backend and analytics parity

- Port or isolate PHP endpoints under V2 ownership.
- Preserve CSRF, honeypot, rate limiting, return allowlists, validation, and mail
  behavior.
- Test success, validation failure, SMTP failure, retry, duplicate submission, and
  availability race cases.
- Define analytics events once and test them centrally.

### Phase 7: QA and launch preparation

- Automated route/link checks
- Unit and integration tests
- Visual regression at agreed breakpoints
- Keyboard and screen-reader pass
- Lighthouse/performance budgets
- SEO metadata, canonical, sitemap, robots, and structured-data validation
- Redirect matrix and rollback plan
- Content freeze, stakeholder sign-off, staging acceptance, then production cutover

## Review Decision Required

V2 work remains blocked by design review, not by code:

1. Identify the "end decks" reference.
2. Approve the authoritative route/content inventory.
3. Approve Astro and the sibling `v2-site/` architecture.
4. Approve the baseline visual/interaction capture before implementation.
