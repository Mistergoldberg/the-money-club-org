# V2 foundation: isolated folder, design tokens, shell, navigation, and primitives

## What was created

- A standalone Astro application in `v2-site/`
- Semantic brand, typography, spacing, radius, layout, focus, and state tokens
- Reset, global, and utility stylesheets
- Central navigation, funnel, program, and SEO data models
- Base layout, header, desktop navigation, accessible mobile navigation, and footer
- Button, section, card, CTA block, form field, parent confidence, child-fit, and
  sticky CTA primitives
- A no-index foundation preview route for build and interaction validation
- V2-owned placeholders for content, PHP, tests, scripts, images, and icons

## What was intentionally not changed

- No existing file under `new site/` was modified by this work
- No production routes, forms, PHP handlers, assets, redirects, or analytics were
  migrated
- No full homepage or funnel page was built
- No final content, final SEO copy, or final end-deck implementation was selected

## How V2 is isolated

- V2 has its own manifest, dependency lockfile, Astro configuration, TypeScript
  configuration, source tree, public assets, server area, tests, and scripts
- V2 contains no imports, symlinks, or runtime references to `new site/`
- The build output is generated entirely from `v2-site/`
- The configured site target is the staging-oriented `v2.the-money-club.org`

## Audit findings addressed

- Replaces duplicated page-local CSS with semantic tokens and shared styles
- Replaces copied navigation markup/scripts with one data-driven accessible shell
- Replaces overlapping button and CTA systems with explicit semantic variants
- Establishes reusable card, section, form, confidence, fit, and sticky CTA patterns
- Creates a route model before page production to reduce navigation and canonical drift
- Creates a clean boundary for later backend and analytics parity work

## Unresolved before full page production

- Exact "end decks" page or pattern remains unresolved
- Final route slugs and legacy redirect matrix need approval
- Final primary navigation hierarchy needs content-owner approval
- Production form/API migration strategy needs a separate decision
- Final analytics provider and event contract need approval
- Current-site desktop/mobile visual baselines still need capture
- Final font delivery choice, self-hosted or provider-hosted, needs approval
