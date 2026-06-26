export interface HomeFeature {
  title: string;
  description: string;
}

export interface ProgramDetail {
  label: string;
  value: string;
}

export interface BuildStep {
  title: string;
  description: string;
}

export interface FaqItem {
  question: string;
  answer: string;
}

export const homepageContent = {
  hero: {
    eyebrow: "Financial literacy for young builders",
    headline:
      "Kids don’t need more money slogans. They need to see how money moves.",
    body:
      "The Money Club is a hands-on financial literacy and entrepreneurship program for students who are ready to build, test, price, explain, and present real ideas. This August, we are running a one-week program at the UTSU Student Commons in downtown Toronto.",
    primaryCta: {
      label: "Register for August",
      href: "/parent-approval/",
    },
    secondaryCta: {
      label: "See the Curriculum",
      href: "/curriculum/",
    },
    proofPoints: [
      "Build real ideas",
      "Make real trade-offs",
      "Present clear thinking",
    ],
  },
  trustLine:
    "August 10–14 · 9:00 AM–5:00 PM · UTSU Student Commons · 230 College Street · $200 · Limited to 30 students",
  trustItems: [
    "August 10–14",
    "9:00 AM–5:00 PM",
    "UTSU Student Commons",
    "230 College Street",
    "$200",
    "Limited to 30 students",
  ],
  problem: {
    eyebrow: "The problem",
    title: "Most financial literacy starts too late and stays too abstract.",
    paragraphs: [
      "Students are told to save money, budget carefully, and make good choices. That is useful. But it is not enough.",
      "They also need to understand pricing, cost, margin, risk, incentives, labour, technology, ownership, and trade-offs.",
      "They need to learn why a lemonade stand is simple, but a coffee shop becomes a system.",
    ],
    statement:
      "Money is a language. A signal. A tool. A system of choices with consequences.",
  },
  studentWork: {
    eyebrow: "What students do",
    title: "Students learn by building.",
    lead:
      "Over one week, students move from ideas to real-world thinking. This is not worksheet learning. It is project-based financial literacy for the actual world.",
    intro:
      "They investigate businesses, use AI as a thinking partner, work through customers, cost, pricing, and value, then explain and present what they built.",
  },
  outcomes: [
    {
      title: "Money",
      description: "Cost, price, margin, risk, and trade-offs.",
    },
    {
      title: "Business",
      description: "Products, customers, markets, and value.",
    },
    {
      title: "AI",
      description: "Research, write, design, and test ideas.",
    },
    {
      title: "Communication",
      description: "Explain their thinking clearly.",
    },
    {
      title: "Judgment",
      description: "Make decisions with incomplete information.",
    },
    {
      title: "Confidence",
      description: "Present something they built and can explain.",
    },
  ] satisfies HomeFeature[],
  whyItMatters: {
    eyebrow: "Why this matters",
    title: "Financial literacy is judgment.",
    paragraphs: [
      "Students already live inside a world of prices, products, platforms, subscriptions, rewards, ads, brands, markets, and choices.",
      "The Money Club helps them understand the systems underneath that world, so they can make better decisions, ask better questions, and explain the trade-offs behind their ideas.",
      "The goal is not to memorize slogans. It is to see the machine.",
    ],
  },
  programDetails: [
    { label: "Dates", value: "August 10–14" },
    { label: "Daily schedule", value: "9:00 AM–5:00 PM" },
    { label: "Instruction begins", value: "9:30 AM" },
    { label: "Lunch", value: "12:00–1:00 PM" },
    { label: "Pickup window", value: "3:30–5:00 PM" },
    { label: "Venue", value: "UTSU Student Commons" },
    { label: "Address", value: "230 College Street, Toronto" },
    { label: "Tuition", value: "$200" },
    { label: "Cohort", value: "Limited to 30 students" },
  ] satisfies ProgramDetail[],
  fitSignals: [
    "Likes asking how things work",
    "Is interested in business, design, technology, or ideas",
    "Is ready to work independently on a laptop",
    "Enjoys active, project-based learning",
    "Wants something more practical than a typical camp",
    "Is curious about money, AI, or entrepreneurship",
  ],
  parentConfidence: [
    "Program confirmed for August 10–14",
    "Venue confirmed at UTSU Student Commons",
    "Fully insured",
    "Limited to 30 students",
    "Tuition is $200",
    "Parent approval required",
    "Clear drop-off and pickup schedule",
  ],
  curriculum: {
    eyebrow: "Curriculum preview",
    title: "We start with the world students already live inside.",
    lead:
      "Students follow a practical build cycle that turns financial ideas into decisions they can test, defend, and present.",
  },
  buildSteps: [
    {
      title: "Observe",
      description: "Notice how a real product, service, or business works.",
    },
    {
      title: "Understand",
      description: "Identify the customer, problem, incentives, and system.",
    },
    {
      title: "Price",
      description: "Work through cost, value, margin, and trade-offs.",
    },
    {
      title: "Build",
      description: "Turn an idea into something concrete.",
    },
    {
      title: "Test",
      description: "Use evidence, feedback, and AI to improve it.",
    },
    {
      title: "Explain",
      description: "Make the reasoning clear to someone else.",
    },
    {
      title: "Reflect",
      description: "Review the choices, consequences, and next move.",
    },
  ] satisfies BuildStep[],
  founderNote: {
    title: "A note from Jared",
    paragraphs: [
      "I started The Money Club because I believe young people need a better way to understand the world they are growing into.",
      "Financial literacy cannot just be about saving money. Students need to understand systems. They need to understand how ideas become products, how prices are set, how customers make decisions, how technology changes work, and how money moves through the choices people make every day.",
      "The first Money Club program is intentionally small. I will be teaching the August session myself, supported by a small team. The goal is to create a focused, practical, high-energy learning experience for students — and to build the foundation for a future mentorship pathway that creates meaningful opportunities for UofT students.",
      "Thank you to every family helping bring this first session to life.",
    ],
    signature: "Jared Goldberg",
    role: "Founder, The Money Club",
  },
  mentor: {
    eyebrow: "The bigger mission",
    title: "The Money Club is also building a student employment engine.",
    body:
      "The long-term vision combines financial literacy for younger students with mentorship and employment opportunities for UofT students. As The Money Club grows, we plan to train student mentors, create paid roles, and support future chapters.",
    statement:
      "Students teach students. Mentors become leaders. Chapters create opportunity.",
  },
  faqs: [
    {
      question: "Is this a financial literacy camp?",
      answer:
        "It is broader than a traditional financial literacy camp. Students learn about money through business, entrepreneurship, AI, communication, and project-building so they can understand how financial systems work in the real world.",
    },
    {
      question: "What age is the program for?",
      answer:
        "The program is designed for students roughly ages 10–16, with the strongest interest so far from ages 13–15. Readiness, curiosity, and the ability to work independently on a laptop matter more than age alone.",
    },
    {
      question: "Does my child need business experience?",
      answer:
        "No. Students need curiosity and a willingness to participate. The program is designed to help them build the vocabulary, tools, and confidence as they go.",
    },
    {
      question: "Will students use AI?",
      answer:
        "Yes. Students will use AI as a thinking and building tool to research, write, develop ideas, and improve their work. The focus is judgment and better thinking, not shortcuts.",
    },
    {
      question: "Where is the program?",
      answer:
        "The August program is located at the UTSU Student Commons, 230 College Street in downtown Toronto.",
    },
    {
      question: "Is The Money Club affiliated with the University of Toronto?",
      answer:
        "The program is located at the UTSU Student Commons, and The Money Club is building a future mentorship pathway for UofT students. It is not an official University of Toronto program.",
    },
    {
      question: "What does a typical day look like?",
      answer:
        "Drop-off begins at 9:00 AM, instruction starts at 9:30 AM, and lunch runs from 12:00–1:00 PM. Afternoons include workshops and project work, with pickup available from 3:30–5:00 PM.",
    },
    {
      question: "How much does it cost?",
      answer: "Tuition for the one-week August program is $200.",
    },
    {
      question: "How is a spot confirmed?",
      answer:
        "A spot is confirmed after parent approval is completed and the $200 e-transfer is received at info@the-money-club.org.",
    },
    {
      question: "Can I speak with someone before registering?",
      answer:
        "Yes. Use the contact page to reach The Money Club with questions before completing parent approval.",
    },
  ] satisfies FaqItem[],
  finalCta: {
    eyebrow: "August 10–14 · Toronto",
    title: "Help your child see the machine.",
    paragraphs: [
      "The world is full of prices, products, platforms, subscriptions, rewards, ads, brands, markets, and choices. Students are already inside that world. The Money Club helps them understand it.",
      "Not through slogans, lectures, or pretend games. Through real questions, useful tools, practical projects, and clear thinking.",
    ],
  },
} as const;
