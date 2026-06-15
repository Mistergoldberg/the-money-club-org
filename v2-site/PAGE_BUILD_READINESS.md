# V2 Page Build Readiness

This document records the reusable V2 system state before building the remaining funnel pages. The homepage is the only built marketing route. No full Program, Curriculum, Parent Approval, Payment, Thank You, Mentor Fellowship, Chapter Vision, FAQ, or Contact routes have been produced yet.

## Current Status

- Built route: `/`
- Ready for content production: Program, Curriculum, Parent Approval, Mentor Fellowship, Chapter Vision, FAQ, Contact
- Planned pending workflow confirmation: Payment, Thank You / Referral
- Production site status: untouched; V2 remains isolated in `v2-site/`
- Unresolved reference: the exact "end decks" page/pattern has still not been identified

## Reusable Page Patterns

- `BaseLayout` owns document metadata, header, footer, skip link, and global styles.
- `PageHero` is the primary hero pattern for marketing pages.
- `Section` controls width, tone, and vertical rhythm.
- `SectionGrid` is the main section layout primitive. It supports split, stack, and narrow layouts with text/media/panel/action slots.
- `ContentPanel` supports reusable emphasis panels.
- `IntroActionRow` supports intro copy with a CTA tucked into the same row on desktop and stacked full-width on small mobile.
- `FaqSection` wraps the approved FAQ layout so future FAQ blocks do not duplicate `SectionGrid` composition.
- `FinalCtaSection` wraps the approved end-of-page conversion pattern.
- `FormSection` provides the route-level shell for parent approval and contact forms.

## Grid And Layout Rules

- Desktop sections should use the 12-column `SectionGrid` rather than one-off grids.
- Split sections should keep text at seven columns and media at five columns unless there is a clear content reason to use stack or narrow.
- Stack sections are for dense multi-card content where media or cards need full-width support.
- Narrow sections are for focused reading patterns such as FAQ.
- Mobile source order is preserved by `SectionGrid`: heading, media, body, panel, actions.
- Page-specific CSS should be avoided. Shared spacing, kicker, lead, and action-row styles live in utilities or reusable components.

## Media System Rules

- Media data uses `MediaItem` with `src`, `alt`, `width`, `height`, optional `caption`, optional `credit`, `aspectRatio`, `placement`, `priority`, `decorative`, and `objectPosition`.
- V2 images must live inside `v2-site/public/images/`.
- V2 must not import, symlink, or reference assets from `new site/`.
- Hero/above-fold media may use priority loading. Below-fold media should remain lazy-loaded.
- Meaningful images require useful alt text. Decorative media should use empty alt text and `decorative: true`.
- Captions should support comprehension and trust, not add dense copy.

## Mobile Hierarchy Rules

- Default mobile section order: eyebrow, heading, image/media, body copy, cards/details, CTA.
- Images should not be dumped after all body copy on mobile.
- CTAs should become full-width only where touch clarity benefits.
- Sticky CTA should appear after parent trust and suppress before the final CTA to avoid covering decisive content.
- Mobile navigation uses a native button, `aria-expanded`, `aria-controls`, `aria-current`, focus trap, Escape close, body scroll lock, trigger focus return, and reduced-motion-safe transitions.

## Conversion And Trust Components

- `ProgramDetailsBar` carries compact trust facts near the top of conversion pages.
- `ProgramDetailsGrid` handles structured program facts.
- `ParentConfidenceBox` remains available for pages where explicit trust proof is needed, but it has been removed from the homepage to reduce repetition.
- `ChildFitBox` remains available for Program or approval pages, but it has been removed from the homepage.
- `StickyCta` remains the persistent conversion CTA pattern.
- `CtaBlock` remains the primary feature/end-deck style CTA primitive.

## Form Readiness

- `FormField` supports text, email, phone, number, URL, date, textarea, select, checkbox, and radio inputs.
- Field labels, hints, errors, `aria-invalid`, and `aria-describedby` are handled in the primitive.
- `FormSection` provides the page shell for form routes.
- Not ready without backend decisions: submission endpoint, storage policy, confirmation behavior, spam protection, and payment handoff.

## Route Readiness

Route planning is centralized in `src/data/navigation.ts` and `src/data/page-build.ts`.

- Home: built
- Program: ready for content
- Curriculum: ready for content
- Parent Approval: ready for content, pending backend workflow
- Payment: planned, pending payment workflow
- Thank You / Referral: planned, pending form/payment confirmation flow
- Mentor Fellowship: ready for content with careful UofT wording
- Chapter Vision: ready for content
- FAQ: ready for content
- Contact: ready for content, pending backend workflow

## Recommended Build Order

1. Program
2. Curriculum
3. Parent Approval backend and form workflow
4. Payment workflow
5. Thank You / Referral
6. FAQ and Contact
7. Mentor Fellowship
8. Chapter Vision

Program and Curriculum can begin next because their layout, media, CTA, and content primitives are ready. Parent Approval should wait until form submission and data handling decisions are confirmed.
