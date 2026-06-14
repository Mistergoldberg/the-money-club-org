export type RouteStatus = "planned" | "foundation" | "ready";
export type NavigationGroup = "primary" | "support" | "funnel";

export interface SiteRoute {
  id:
    | "home"
    | "program"
    | "curriculum"
    | "parent-approval"
    | "payment"
    | "thank-you"
    | "mentor-fellowship"
    | "chapter-vision"
    | "faq"
    | "contact";
  label: string;
  shortLabel?: string;
  href: string;
  description: string;
  group: NavigationGroup;
  status: RouteStatus;
  showInHeader?: boolean;
  showInFooter?: boolean;
}

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
    showInHeader: true,
    showInFooter: true,
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
    showInHeader: true,
    showInFooter: true,
  },
  {
    id: "chapter-vision",
    label: "Chapter Vision",
    href: "/chapter-vision",
    description: "The long-term chapter and community model.",
    group: "primary",
    status: "planned",
    showInHeader: true,
    showInFooter: true,
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
];

export const headerRoutes = siteRoutes.filter((route) => route.showInHeader);
export const footerRoutes = siteRoutes.filter((route) => route.showInFooter);
export const supportRoutes = siteRoutes.filter((route) => route.group === "support");
export const funnelRoutes = siteRoutes.filter((route) => route.group === "funnel");

export function isCurrentRoute(currentPath: string, href: string): boolean {
  if (href === "/") {
    return currentPath === "/";
  }

  return currentPath === href || currentPath.startsWith(`${href}/`);
}
