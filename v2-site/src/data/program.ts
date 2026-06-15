import type { SiteRoute } from "./navigation";
import { siteRoutes } from "./navigation";

export interface ProgramPrinciple {
  title: string;
  description: string;
}

export interface FunnelStage {
  route: SiteRoute;
  purpose: string;
  implementation: "built" | "planned";
}

export const programIdentity = {
  organizationName: "The Money Club",
  organizationDisplayName: "The Money Club.Org",
  organizationType: "Nonprofit",
  brandStatement:
    "A practical learning program connecting product building, financial literacy, and real-world responsibility.",
} as const;

export const programPrinciples: ProgramPrinciple[] = [
  {
    title: "Build from shared foundations",
    description:
      "Routes, content, design tokens, and interaction contracts are defined centrally before page production.",
  },
  {
    title: "Make parent confidence explicit",
    description:
      "Program fit, safety, expectations, and next steps should be clear at each decision point.",
  },
  {
    title: "Keep conversion patterns consistent",
    description:
      "Buttons, forms, CTA blocks, loading states, and confirmations should use one shared system.",
  },
];

const routeById = new Map(siteRoutes.map((route) => [route.id, route]));

function requiredRoute(id: SiteRoute["id"]): SiteRoute {
  const route = routeById.get(id);

  if (!route) {
    throw new Error(`Missing V2 route definition: ${id}`);
  }

  return route;
}

export const programFunnel: FunnelStage[] = [
  {
    route: requiredRoute("home"),
    purpose: "Introduce the organization and direct visitors to the right next step.",
    implementation: "built",
  },
  {
    route: requiredRoute("program"),
    purpose: "Explain the program model, outcomes, and audience fit.",
    implementation: "planned",
  },
  {
    route: requiredRoute("curriculum"),
    purpose: "Provide curriculum detail and evidence of the learning experience.",
    implementation: "planned",
  },
  {
    route: requiredRoute("parent-approval"),
    purpose: "Collect parent consent and required student information.",
    implementation: "planned",
  },
  {
    route: requiredRoute("payment"),
    purpose: "Complete the approved payment workflow.",
    implementation: "planned",
  },
  {
    route: requiredRoute("thank-you"),
    purpose: "Confirm completion and provide the referral pathway.",
    implementation: "planned",
  },
];
