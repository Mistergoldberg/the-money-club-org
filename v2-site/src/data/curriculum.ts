import type { HomeFeature } from "./homepage";

export interface WeekDay {
  day: string;
  topics: string[];
}

export const curriculumContent = {
  hero: {
    eyebrow: "Curriculum",
    headline: "Build judgment before confidence.",
    body: "The Money Club runs as a focused one-week sprint. Each day combines morning instruction and afternoon workshops. Students move from how money works to building and explaining something real.",
    primaryCta: { label: "Register for August", href: "/parent-approval/" },
    secondaryCta: { label: "See the schedule", href: "#week-schedule" },
    proofPoints: [
      "August 10–14, Toronto",
      "11 core modules",
      "Live Maker Market on Friday",
    ],
  },
  overview: {
    eyebrow: "How it works",
    title: "Morning instruction. Afternoon workshops.",
    paragraphs: [
      "In the morning, students learn the business mechanics behind real products: money, demand, sourcing, pricing, design, marketing, and go-to-market strategy.",
      "In the afternoon, they put the theory into action. They research markets, study what people already buy, identify problems, shape ideas, and build toward something they can explain and defend.",
      "By the end of the week, students have moved from idea to testable concept — with a clearer understanding of how value is created, priced, communicated, and improved.",
    ],
  },
  weekSchedule: {
    eyebrow: "Week overview",
    title: "What happens each day.",
    days: [
      {
        day: "Monday",
        topics: [
          "Financial Literacy as Survival Gear",
          "Sourcing 101: Where Value Is Actually Made",
          "Control Brand Playbook",
        ],
      },
      {
        day: "Tuesday",
        topics: [
          "Market Research: Follow the Money and Problems",
          "Design Thinking and Human Factor Research",
          "Business Model Canvas",
        ],
      },
      {
        day: "Wednesday",
        topics: [
          "Go-To-Market: Launch Without Burning Fuel",
          "Creative Fundamentals: Photography, Design, and Visual Clarity",
          "Marketing: Use Existing Channels First",
        ],
      },
      {
        day: "Thursday",
        topics: [
          "Market Research and Sourcing with AI",
          "Flash Photography Workshop",
          "Website Design with Vibe Coding",
        ],
      },
      {
        day: "Friday",
        topics: ["Guest Speaker", "The Maker Market"],
      },
    ] satisfies WeekDay[],
  },
  modules: [
    {
      title: "Financial Literacy as Survival Gear",
      description:
        "How money actually moves through a business: gross margin, profit, fixed and variable costs, break-even points, and cash flow. Students see why many businesses fail because the economics do not work.",
    },
    {
      title: "Sourcing 101",
      description:
        "How products are sourced, priced, bundled, and scaled. Students learn about suppliers, minimum order quantities, lead times, logistics, and pricing leverage.",
    },
    {
      title: "Market Research",
      description:
        "How to look for signals, not just opinions. Students study demand by examining existing spending, substitutes, complaints, inefficiencies, and what people already pay for.",
    },
    {
      title: "Control Brand Playbook",
      description:
        "How operators create value by studying what already works, improving the offer, and controlling margin. Introduces the logic behind positioning, private label, and pricing power.",
    },
    {
      title: "Design Thinking",
      description:
        "How to observe behavior, identify friction, and translate real problems into ideas people might actually use. Grounded in human factor research.",
    },
    {
      title: "Business Model Thinking",
      description:
        "How to connect the problem, customer, value proposition, revenue model, cost structure, and path to execution so an idea works as a system, not just a concept.",
    },
    {
      title: "Go-To-Market",
      description:
        "How to test quickly, learn early, and avoid overbuilding. The focus is on feedback, traction, and learning before polish.",
    },
    {
      title: "Creative Fundamentals",
      description:
        "How design, messaging, and visual clarity affect trust, demand, and perceived value. Students practice photography and layout to see how presentation shapes decisions.",
    },
    {
      title: "Marketing",
      description:
        "How to use existing marketplaces, retailers, social platforms, and partnerships to test ideas faster than building everything from scratch.",
    },
    {
      title: "AI Tools",
      description:
        "How to use AI to research markets, spot patterns, model business cases, create media assets, and prototype ideas. The goal is better thinking and faster iteration — not shortcuts.",
    },
    {
      title: "The Maker Market",
      description:
        "The program ends with a live market. Each team presents what they built. Participants receive virtual cash and choose where to spend it. Students must explain what they made, who it is for, and why someone would want it.",
    },
  ] satisfies HomeFeature[],
  makerMarket: {
    eyebrow: "How it ends",
    title: "The Maker Market.",
    paragraphs: [
      "On Friday, students present what they built. Each team describes their product, service, or idea to classmates who receive virtual cash and choose how to spend it.",
      "There are no grades in that moment. Just judgment, value, and decision-making. An idea is only as strong as the choices it can earn.",
    ],
    mustExplain: [
      "What they made and who it is for",
      "How it works and what it costs",
      "How it is priced and why",
      "Why someone would choose it over alternatives",
    ],
  },
};
