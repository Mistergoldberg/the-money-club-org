import { siteRoutes, type SiteRoute } from "./navigation";

export interface SeoEntry {
  title: string;
  description: string;
  pathname: string;
  noIndex?: boolean;
}

export const siteSeo = {
  siteName: "The Money Club.Org",
  titleTemplate: "%s | The Money Club.Org",
  defaultDescription:
    "The Money Club is a nonprofit practical learning program focused on product building, financial literacy, and real-world responsibility.",
  defaultImage: "/images/social-card.jpg",
  locale: "en_CA",
} as const;

export const routeSeo: Record<SiteRoute["id"], SeoEntry> = Object.fromEntries(
  siteRoutes.map((route) => [
    route.id,
    {
      title: route.label,
      description: route.description,
      pathname: route.href,
    },
  ]),
) as Record<SiteRoute["id"], SeoEntry>;

export function formatPageTitle(title: string): string {
  return siteSeo.titleTemplate.replace("%s", title);
}
