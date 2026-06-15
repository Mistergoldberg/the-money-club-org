import type { SiteRoute } from "./navigation";
import { siteRoutes } from "./navigation";

export type PageBuildStatus = "built" | "ready-for-content" | "planned";
export type PageTemplate =
  | "marketing"
  | "curriculum"
  | "approval-form"
  | "payment"
  | "confirmation"
  | "support";

export interface PageBuildPlan {
  routeId: SiteRoute["id"];
  status: PageBuildStatus;
  template: PageTemplate;
  primaryGoal: string;
  coreComponents: string[];
  requiredContent: string[];
  formRequired?: boolean;
  notes?: string;
}

export const pageBuildPlans: PageBuildPlan[] = [
  {
    routeId: "home",
    status: "built",
    template: "marketing",
    primaryGoal: "Introduce the program and move qualified families toward parent approval.",
    coreComponents: [
      "BaseLayout",
      "PageHero",
      "ProgramDetailsBar",
      "SectionGrid",
      "MediaFrame",
      "CtaBlock",
      "FaqSection",
      "FinalCtaSection",
      "StickyCta",
    ],
    requiredContent: ["homepageContent", "homepageMedia"],
  },
  {
    routeId: "program",
    status: "ready-for-content",
    template: "marketing",
    primaryGoal: "Explain program model, schedule, fit, trust, and conversion next step.",
    coreComponents: [
      "BaseLayout",
      "PageHero",
      "SectionGrid",
      "ProgramDetailsGrid",
      "LocationMedia",
      "ParentConfidenceBox",
      "FinalCtaSection",
    ],
    requiredContent: ["program positioning", "daily schedule", "parent trust proof"],
  },
  {
    routeId: "curriculum",
    status: "ready-for-content",
    template: "curriculum",
    primaryGoal: "Show the build cycle, learning outcomes, weekly flow, and student outputs.",
    coreComponents: [
      "BaseLayout",
      "PageHero",
      "BuildCycle",
      "FeatureGrid",
      "MediaGrid",
      "FaqSection",
      "FinalCtaSection",
    ],
    requiredContent: ["curriculum sequence", "learning outcomes", "sample activities"],
  },
  {
    routeId: "parent-approval",
    status: "ready-for-content",
    template: "approval-form",
    primaryGoal: "Collect parent consent and required student information.",
    coreComponents: [
      "BaseLayout",
      "FormSection",
      "FormField",
      "ParentConfidenceBox",
      "ProgramDetailsBar",
    ],
    requiredContent: ["consent language", "student details", "parent details", "emergency contact"],
    formRequired: true,
    notes: "Backend submission target and storage policy still need confirmation before implementation.",
  },
  {
    routeId: "payment",
    status: "planned",
    template: "payment",
    primaryGoal: "Explain payment instructions after approval is complete.",
    coreComponents: ["BaseLayout", "CtaBlock", "ProgramDetailsBar"],
    requiredContent: ["payment instructions", "confirmation language"],
    notes: "Do not expose payment route as a primary CTA until approval workflow is confirmed.",
  },
  {
    routeId: "thank-you",
    status: "planned",
    template: "confirmation",
    primaryGoal: "Confirm submission and introduce referral/share next step.",
    coreComponents: ["BaseLayout", "CtaBlock", "FeatureGrid"],
    requiredContent: ["confirmation copy", "referral prompt", "next-step timeline"],
  },
  {
    routeId: "mentor-fellowship",
    status: "ready-for-content",
    template: "marketing",
    primaryGoal: "Explain the future UofT mentorship pathway without implying official affiliation.",
    coreComponents: ["BaseLayout", "PageHero", "SectionGrid", "MediaGrid", "FinalCtaSection"],
    requiredContent: ["mentor role", "future pathway", "careful UofT wording"],
  },
  {
    routeId: "chapter-vision",
    status: "ready-for-content",
    template: "marketing",
    primaryGoal: "Describe chapter growth and community model.",
    coreComponents: ["BaseLayout", "PageHero", "SectionGrid", "FeatureGrid", "FinalCtaSection"],
    requiredContent: ["chapter model", "growth narrative", "operating principles"],
  },
  {
    routeId: "faq",
    status: "ready-for-content",
    template: "support",
    primaryGoal: "Answer parent questions in a standalone support page.",
    coreComponents: ["BaseLayout", "FaqSection", "FinalCtaSection"],
    requiredContent: ["complete FAQ set"],
  },
  {
    routeId: "contact",
    status: "ready-for-content",
    template: "support",
    primaryGoal: "Give families a clear way to ask questions before approval.",
    coreComponents: ["BaseLayout", "FormSection", "FormField"],
    requiredContent: ["contact instructions", "contact form fields"],
    formRequired: true,
    notes: "Backend submission target still needs confirmation.",
  },
];

export const pageBuildPlanByRoute = Object.fromEntries(
  pageBuildPlans.map((plan) => [plan.routeId, plan]),
) as Record<SiteRoute["id"], PageBuildPlan>;

export const pageBuildRouteOrder = pageBuildPlans.map((plan) => {
  const route = siteRoutes.find((candidate) => candidate.id === plan.routeId);

  if (!route) {
    throw new Error(`Missing route for page build plan: ${plan.routeId}`);
  }

  return {
    ...plan,
    route,
  };
});
