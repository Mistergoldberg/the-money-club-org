export type MediaAspectRatio =
  | "wide"
  | "landscape"
  | "square"
  | "portrait"
  | "natural";

export type MediaPlacement =
  | "hero"
  | "inline"
  | "split-start"
  | "split-end"
  | "card"
  | "grid"
  | "location"
  | "profile";

export interface MediaItem {
  src: string;
  alt: string;
  width: number;
  height: number;
  caption?: string;
  credit?: string;
  aspectRatio?: MediaAspectRatio;
  placement?: MediaPlacement;
  priority?: boolean;
  decorative?: boolean;
  objectPosition?: string;
}

export const homepageMedia = {
  hero: {
    src: "/images/home/hero-build-workshop.jpg",
    alt: "Students collaborating around a table while an instructor maps money, ideas, and markets on a whiteboard.",
    width: 1536,
    height: 1024,
    caption: "A practical workshop environment built around questions, decisions, and visible work.",
    aspectRatio: "landscape",
    placement: "hero",
    priority: true,
    objectPosition: "center",
  },
  problem: {
    src: "/images/home/prototype-in-hand.jpg",
    alt: "Students holding a cardboard phone prototype built to make an idea concrete.",
    width: 1720,
    height: 1920,
    caption: "Abstract ideas become easier to understand when students can build, test, and explain them.",
    aspectRatio: "portrait",
    placement: "split-end",
    objectPosition: "center",
  },
  studentWork: [
    {
      src: "/images/home/students-building-products.jpg",
      alt: "Students assembling and labelling a set of sample products together.",
      width: 1536,
      height: 1024,
      caption: "Build something concrete",
      aspectRatio: "landscape",
      placement: "grid",
      objectPosition: "center",
    },
    {
      src: "/images/home/students-testing-ideas.jpg",
      alt: "Three students reviewing customer feedback and navigation ideas on a whiteboard.",
      width: 1536,
      height: 1024,
      caption: "Test assumptions and use evidence",
      aspectRatio: "landscape",
      placement: "grid",
      objectPosition: "center",
    },
    {
      src: "/images/home/student-presenting-work.jpg",
      alt: "A student presenting a project and charts to classmates.",
      width: 1536,
      height: 1024,
      caption: "Explain the thinking clearly",
      aspectRatio: "landscape",
      placement: "grid",
      objectPosition: "center",
    },
  ],
  location: {
    src: "/images/home/students-on-campus.jpg",
    alt: "Students walking together outside a downtown campus building.",
    width: 1536,
    height: 1024,
    caption: "The August program is located at the UTSU Student Commons in downtown Toronto.",
    aspectRatio: "landscape",
    placement: "location",
    objectPosition: "center",
  },
  fit: {
    src: "/images/home/student-customer-research.jpg",
    alt: "A student speaking with two adults while taking notes during customer research.",
    width: 1536,
    height: 1024,
    caption: "Curiosity and readiness matter more than prior business experience.",
    aspectRatio: "landscape",
    placement: "split-start",
    objectPosition: "center",
  },
  parentTrust: {
    src: "/images/home/mentor-working-with-students.jpg",
    alt: "A mentor working closely with a small group of students around laptops and a financial planning whiteboard.",
    width: 1536,
    height: 1024,
    caption: "A deliberately small cohort supports active discussion and individual attention.",
    aspectRatio: "landscape",
    placement: "split-end",
    objectPosition: "center",
  },
  curriculum: {
    src: "/images/home/student-explaining-system.jpg",
    alt: "A student explaining a customer, service, and payment system to classmates at a whiteboard.",
    width: 1536,
    height: 1024,
    caption: "Students move from observation to a system they can explain and defend.",
    aspectRatio: "landscape",
    placement: "inline",
    objectPosition: "center",
  },
  founder: {
    src: "/images/home/jared-goldberg.png",
    alt: "Jared Goldberg, founder of The Money Club.",
    width: 500,
    height: 599,
    aspectRatio: "portrait",
    placement: "profile",
    objectPosition: "center top",
  },
  mentor: {
    src: "/images/home/mentor-led-project-work.jpg",
    alt: "A mentor supporting students as they work through a digital product project.",
    width: 1024,
    height: 1536,
    caption: "The future pathway pairs younger builders with trained student mentors.",
    aspectRatio: "portrait",
    placement: "card",
    objectPosition: "center",
  },
} as const satisfies Record<string, MediaItem | readonly MediaItem[]>;

export const openBookMedia = {
  hero: {
    src: "/images/home/students-testing-ideas.jpg",
    alt: "Students testing and reviewing ideas together at a workshop table.",
    width: 1536,
    height: 1024,
    caption: "A program designed around learning, not upselling.",
    aspectRatio: "landscape",
    placement: "hero",
    priority: true,
    objectPosition: "center",
  },
  mission: {
    src: "/images/home/students-on-campus.jpg",
    alt: "Students working together on campus during the Money Club program.",
    width: 1536,
    height: 1024,
    caption: "The goal is not to maximize profit. The goal is to run a thoughtful, well-supported program.",
    aspectRatio: "landscape",
    placement: "split-end",
    objectPosition: "center",
  },
} as const satisfies Record<string, MediaItem>;

export const buildSellMedia = {
  hero: {
    src: "/images/home/students-building-products.jpg",
    alt: "Students collaborating at a workshop table while building and testing product ideas.",
    width: 1536,
    height: 1024,
    caption: "Students build something real, then figure out if anyone would pay for it.",
    aspectRatio: "landscape",
    placement: "hero",
    priority: true,
    objectPosition: "center",
  },
  ai: {
    src: "/images/home/mentor-working-with-students.jpg",
    alt: "A mentor working with students on laptops, using AI tools to research and prototype ideas.",
    width: 1536,
    height: 1024,
    caption: "AI is used as a build tool — for research, prototyping, and faster iteration.",
    aspectRatio: "landscape",
    placement: "split-end",
    objectPosition: "center",
  },
  financialLiteracy: {
    src: "/images/home/student-customer-research.jpg",
    alt: "A student interviewing a potential customer to understand what they would pay for.",
    width: 1536,
    height: 1024,
    caption: "Money is not explained here. It is used.",
    aspectRatio: "landscape",
    placement: "split-start",
    objectPosition: "center",
  },
  makerMarket: {
    src: "/images/home/student-presenting-work.jpg",
    alt: "A student presenting their product and pricing decisions at the Maker Market.",
    width: 1024,
    height: 1536,
    caption: "One real question at the end: why would someone choose this?",
    aspectRatio: "portrait",
    placement: "split-end",
    objectPosition: "center top",
  },
} as const satisfies Record<string, MediaItem>;

export const curriculumMedia = {
  hero: {
    src: "/images/home/students-building-products.jpg",
    alt: "Students working on products and ideas at workshop tables.",
    width: 1536,
    height: 1024,
    caption: "One week. Morning instruction. Afternoon workshops.",
    aspectRatio: "landscape",
    placement: "hero",
    priority: true,
    objectPosition: "center",
  },
  overview: {
    src: "/images/home/student-customer-research.jpg",
    alt: "A student doing customer research on a laptop during an afternoon workshop session.",
    width: 1536,
    height: 1024,
    caption: "Students research real markets and test real assumptions.",
    aspectRatio: "landscape",
    placement: "split-end",
    objectPosition: "center",
  },
  makerMarket: {
    src: "/images/home/student-presenting-work.jpg",
    alt: "A student presenting their product and pricing decisions to classmates at the Maker Market.",
    width: 1024,
    height: 1536,
    caption: "The Maker Market turns the week's work into a real test of value and judgment.",
    aspectRatio: "portrait",
    placement: "split-start",
    objectPosition: "center top",
  },
} as const satisfies Record<string, MediaItem>;
