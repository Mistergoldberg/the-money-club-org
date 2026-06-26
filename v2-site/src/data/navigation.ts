export type RouteStatus = "planned" | "foundation" | "ready";
export type NavigationGroup = "primary" | "support" | "funnel";

export interface SiteRoute {
  id:
    | "home"
    | "program"
    | "curriculum"
    | "build-sell"
    | "open-book-financials"
    | "who-runs-it"
    | "camp-guides"
    | "learning-catalogue"
    | "reserve-a-spot"
    | "parent-approval"
    | "payment"
    | "thank-you"
    | "mentor-fellowship"
    | "chapter-vision"
    | "faq"
    | "contact"
    | "instructors";
  label: string;
  shortLabel?: string;
  href: string;
  description: string;
  group: NavigationGroup;
  status: RouteStatus;
  showInHeader?: boolean;
  showInFooter?: boolean;
}

/**
 * Mirrors the live `new site/` primary + mobile menu (see
 * `new site/audit/current-site-audit.md`, section 4) rather than inventing a
 * new information architecture. `program`, `mentor-fellowship`, and
 * `chapter-vision` are kept for the page-build roadmap but are not real V1
 * menu items, so they stay out of header/footer until that content is
 * scoped.
 */
export const siteRoutes: SiteRoute[] = [
  {
    id: "home",
    label: "Home",
    href: "/",
    description:
      "Hands-on financial literacy and entrepreneurship for young builders.",
    group: "primary",
    status: "ready",
    showInHeader: true,
    showInFooter: true,
  },
  {
    id: "program",
    label: "Program",
    href: "/program",
    description: "Program positioning, outcomes, structure, and fit.",
    group: "primary",
    status: "planned",
  },
  {
    id: "curriculum",
    label: "Curriculum",
    href: "/curriculum",
    description: "Curriculum sequence, learning model, and student outputs.",
    group: "primary",
    status: "planned",
    showInHeader: true,
    showInFooter: true,
  },
  {
    id: "build-sell",
    label: "Build & Sell",
    href: "/build-sell",
    description: "How the $50 build budget becomes a real, sellable product.",
    group: "primary",
    status: "planned",
    showInHeader: true,
    showInFooter: true,
  },
  {
    id: "open-book-financials",
    label: "Open Book Financials",
    shortLabel: "Open Book",
    href: "/open-book-financials",
    description: "The transparent cost, price, and margin model kids see.",
    group: "primary",
    status: "planned",
    showInHeader: true,
    showInFooter: true,
  },
  {
    id: "who-runs-it",
    label: "Who Runs It",
    href: "/who-runs-it",
    description: "The team and mentors behind the program.",
    group: "primary",
    status: "planned",
    showInHeader: true,
    showInFooter: true,
  },
  {
    id: "camp-guides",
    label: "Camp Guides",
    href: "/camp-guides",
    description: "SEO guide hub consolidating the eight summer-camp articles.",
    group: "primary",
    status: "planned",
    showInHeader: true,
  },
  {
    id: "learning-catalogue",
    label: "Learning Catalogue",
    href: "/learn",
    description: "All learning modules in one catalogue.",
    group: "primary",
    status: "planned",
    showInHeader: true,
  },
  {
    id: "reserve-a-spot",
    label: "Reserve a Spot",
    href: "/reserve-a-spot",
    description: "Primary registration CTA destination.",
    group: "funnel",
    status: "planned",
  },
  {
    id: "parent-approval",
    label: "Parent Approval",
    href: "/parent-approval",
    description: "Parent consent and student information workflow.",
    group: "funnel",
    status: "planned",
  },
  {
    id: "payment",
    label: "Payment",
    href: "/payment",
    description: "Payment instructions and confirmation workflow.",
    group: "funnel",
    status: "planned",
  },
  {
    id: "thank-you",
    label: "Thank You / Referral",
    shortLabel: "Thank You",
    href: "/thank-you",
    description: "Submission confirmation and referral invitation.",
    group: "funnel",
    status: "planned",
  },
  {
    id: "mentor-fellowship",
    label: "Mentor Fellowship",
    href: "/mentor-fellowship",
    description: "Mentor opportunity, expectations, and application path.",
    group: "primary",
    status: "planned",
  },
  {
    id: "chapter-vision",
    label: "Chapter Vision",
    href: "/chapter-vision",
    description: "The long-term chapter and community model.",
    group: "primary",
    status: "planned",
  },
  {
    id: "faq",
    label: "FAQ",
    href: "/faq",
    description: "Answers to common parent and student questions.",
    group: "support",
    status: "planned",
    showInFooter: true,
  },
  {
    id: "contact",
    label: "Contact",
    href: "/contact",
    description: "General questions and contact pathway.",
    group: "support",
    status: "planned",
    showInFooter: true,
  },
  {
    id: "instructors",
    label: "Instructors",
    href: "/instructors",
    description: "Mentor/instructor recruitment overview.",
    group: "support",
    status: "planned",
    showInFooter: true,
  },
];

export interface LearningModule {
  id: string;
  label: string;
  href: string;
  status: RouteStatus;
}

/**
 * Literal port of the V1 "Learning Catalogue" disclosure (11 items). Only
 * Financial Literacy has a real route today; the rest point at the catalogue
 * hub until their module pages exist, matching current V1 behavior.
 */
export const learningModules: LearningModule[] = [
  {
    id: "financial-literacy",
    label: "Financial Literacy",
    href: "/learn/financial-literacy-for-young-entrepreneurs",
    status: "ready",
  },
  { id: "design-thinking", label: "Design Thinking", href: "/learn", status: "planned" },
  { id: "market-research", label: "Market Research", href: "/learn", status: "planned" },
  { id: "sourcing", label: "Sourcing", href: "/learn", status: "planned" },
  { id: "control-brand", label: "Control Brand", href: "/learn", status: "planned" },
  { id: "business-model", label: "Business Model", href: "/learn", status: "planned" },
  { id: "go-to-market", label: "Go-To-Market", href: "/learn", status: "planned" },
  {
    id: "creative-fundamentals",
    label: "Creative Fundamentals",
    href: "/learn",
    status: "planned",
  },
  { id: "marketing", label: "Marketing", href: "/learn", status: "planned" },
  { id: "market-watch", label: "Market Watch", href: "/learn", status: "planned" },
  { id: "ai-tools", label: "AI Tools", href: "/learn", status: "planned" },
];

export const headerRoutes = siteRoutes.filter((route) => route.showInHeader);
export const footerRoutes = siteRoutes.filter((route) => route.showInFooter);
export const supportRoutes = siteRoutes.filter((route) => route.group === "support");
export const funnelRoutes = siteRoutes.filter((route) => route.group === "funnel");

export const primaryCtaRoute = siteRoutes.find(
  (route) => route.id === "reserve-a-spot",
)!;

export function isCurrentRoute(currentPath: string, href: string): boolean {
  if (href === "/") {
    return currentPath === "/";
  }

  return currentPath === href || currentPath.startsWith(`${href}/`);
}
