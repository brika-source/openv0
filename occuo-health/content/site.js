/**
 * Occuo Health — single source of truth for all site content.
 *
 * Every page, nav item, card and structured-data block on the site is
 * generated from this file by ../build.mjs. Edit content here, never in dist/.
 *
 * Items marked PLACEHOLDER must be replaced with verified business
 * information before the site goes live. See README.md § "Before you launch".
 */

export const site = {
  name: 'Occuo Health',
  legalName: 'Occuo Health Egypt',
  // PLACEHOLDER — set to the production origin, no trailing slash.
  url: 'https://occuohealtheg.com',
  tagline: 'Occupational health, built for Egyptian workplaces',
  description:
    'Occuo Health delivers occupational health services for employers in Egypt — medical fitness and health surveillance, ergonomics, mental health, emergency preparedness and crisis management.',
  locale: 'en',
  founded: '2019', // PLACEHOLDER
  contact: {
    phone: '+20 100 000 0000', // PLACEHOLDER
    phoneHref: '+201000000000', // PLACEHOLDER
    whatsapp: '+201000000000', // PLACEHOLDER
    email: 'info@occuohealtheg.com', // PLACEHOLDER — confirm the mailbox exists
    salesEmail: 'proposals@occuohealtheg.com', // PLACEHOLDER
    addressLines: ['New Cairo, Cairo Governorate', 'Egypt'], // PLACEHOLDER
    city: 'Cairo',
    country: 'EG',
    hours: 'Sunday – Thursday, 09:00 – 17:00 (EET)',
    emergencyNote: 'On-site emergency cover and standby medical teams are arranged per contract, 24/7.',
  },
  social: [
    // PLACEHOLDER — remove any channel the company does not actually run.
    { label: 'LinkedIn', href: 'https://www.linkedin.com/', icon: 'linkedin' },
    { label: 'Facebook', href: 'https://www.facebook.com/', icon: 'facebook' },
    { label: 'Instagram', href: 'https://www.instagram.com/', icon: 'instagram' },
  ],
};

/* ---------------------------------------------------------------------------
 * Services — each entry generates a card, a nav item, a /services/<slug>/ page
 * and a FAQPage structured-data block.
 * ------------------------------------------------------------------------ */

export const services = [
  {
    slug: 'health-surveillance',
    icon: 'stethoscope',
    title: 'Health Surveillance',
    short: 'Medical fitness and exposure monitoring',
    summary:
      'Pre-employment, pre-placement and periodic medical examinations, biological monitoring and investigation of suspected occupational disease — run as a programme, not a one-off clinic day.',
    intro:
      'Health surveillance is how you find out whether your controls are actually working. Occuo Health builds a surveillance programme around your site’s real exposures, runs the examinations, and gives you fitness decisions your managers can act on and records that stand up to inspection.',
    highlight:
      'Every examination is mapped to a specific hazard and a specific job role — so you are testing for the right thing, on the right people, at the right interval.',
    sections: [
      {
        title: 'What the programme covers',
        body: 'Surveillance is scoped from your hazard register and job-safety analyses, then delivered on a fixed calendar so nothing lapses.',
        bullets: [
          'Pre-employment and pre-placement medical examinations',
          'Periodic and exit examinations tied to exposure category',
          'Biological monitoring for chemical, metal and solvent exposure',
          'Health-effect monitoring: audiometry, spirometry, vision screening, dermatological review',
          'Investigation and reporting of suspected occupational disease',
          'Fitness-to-work determinations with clear, documented restrictions',
        ],
      },
      {
        title: 'Role-specific monitoring',
        body: 'Some roles carry a defined medical standard. We run dedicated protocols for each, including the periodic re-assessment that keeps the certification valid.',
        bullets: [
          'Confined-space entrants',
          'Work-at-height operators',
          'Emergency responders and firefighters',
          'Food handlers',
          'Forklift and mobile-plant operators',
          'Drivers and shift workers',
        ],
      },
      {
        title: 'What you receive',
        body: 'Individual medical detail stays confidential with the examining physician. What reaches the employer is what the employer can act on.',
        bullets: [
          'A fitness decision per employee: fit, fit with restrictions, or temporarily unfit',
          'An anonymised, aggregated trend report by department and exposure group',
          'A flagged list of employees due for re-examination in the next quarter',
          'Audit-ready records retained to the schedule your sector requires',
        ],
      },
    ],
    faq: [
      {
        q: 'Can examinations run on our site instead of a clinic?',
        a: 'Yes. Most programmes run on-site with a mobile team and portable audiometry and spirometry equipment, which removes travel time from the cost. Specialist referrals are handled through our clinic network.',
      },
      {
        q: 'Who sees an individual employee’s medical results?',
        a: 'Only the examining physician and the employee. The employer receives a fitness-to-work decision and any work restrictions — never the underlying clinical detail.',
      },
      {
        q: 'How quickly can a pre-employment programme start?',
        a: 'For a standard scope, mobilisation is typically two to three weeks from signature: hazard review, protocol sign-off, scheduling, then first examination day.',
      },
    ],
  },
  {
    slug: 'corporate-health-programmes',
    icon: 'shield-plus',
    title: 'Corporate Health Programmes',
    short: 'Site risk assessment and emergency readiness',
    summary:
      'Site health risk assessment, ERC-qualified first aid training, site emergency planning, and BLS & ALS equipment and ambulance provision.',
    intro:
      'A corporate health programme is the layer between a written HSE policy and what actually happens when someone is injured at 02:00 on a night shift. We assess the health risk on your site, train the people who will respond first, and make sure the equipment and the evacuation route exist before they are needed.',
    highlight:
      'Assessment, training, equipment and drill — delivered as one programme, so there are no gaps between the plan and the capability.',
    sections: [
      {
        title: 'Site health risk assessment',
        body: 'We walk the site, review the process, and produce a health risk assessment that ranks exposures by severity and likelihood — the document every other health decision should be built on.',
        bullets: [
          'Chemical, physical, biological and ergonomic exposure inventory',
          'Exposure groups defined by task, not by job title',
          'Control-effectiveness review against the hierarchy of controls',
          'A prioritised action register with owners and target dates',
        ],
      },
      {
        title: 'First aid and emergency response training',
        body: 'First aid capability that is certified, refreshed on schedule, and rehearsed under conditions that resemble your actual site.',
        bullets: [
          'First aid and CPR training to European Resuscitation Council (ERC) qualification',
          'Basic Life Support (BLS) and Advanced Life Support (ALS) courses',
          'Emergency response team formation, roles and call-out structure',
          'Scenario drills and post-drill corrective actions',
        ],
      },
      {
        title: 'Equipment and medical cover',
        body: 'The physical readiness layer: what is on the wall, what is in the vehicle, and who is on standby.',
        bullets: [
          'BLS and ALS equipment specification, supply and periodic inspection',
          'AED placement mapping and maintenance schedule',
          'Ambulance sponsorship and standby cover for high-risk operations',
          'Site clinic set-up, staffing and supply management',
        ],
      },
      {
        title: 'Site emergency planning',
        body: 'A written plan that names people, routes and thresholds — reviewed after every drill and every real incident.',
        bullets: [
          'Medical emergency response plan and casualty evacuation routes',
          'Escalation thresholds and receiving-hospital agreements',
          'Remote and offshore site medevac planning',
          'Annual plan review and drill calendar',
        ],
      },
    ],
    faq: [
      {
        q: 'Is the first aid certification recognised internationally?',
        a: 'Training is delivered to European Resuscitation Council (ERC) guidelines, which is the standard most multinational operators in Egypt require of their contractors.',
      },
      {
        q: 'Do you supply the equipment or only specify it?',
        a: 'Both. We can specify to your risk assessment and let you procure, or supply and maintain BLS/ALS kit and AEDs on a serviced contract with scheduled inspection.',
      },
      {
        q: 'Can you cover a short-term shutdown or turnaround?',
        a: 'Yes. Standby medical cover, a temporary site clinic and ambulance provision for turnarounds, shutdowns and large construction phases are a common scope for us.',
      },
    ],
  },
  {
    slug: 'ergonomics',
    icon: 'activity',
    title: 'Ergonomics & MSD Prevention',
    short: 'Reduce musculoskeletal injury and claims',
    summary:
      'Applying ergonomic principles to the workplace to reduce musculoskeletal disorders and the compensation claims that follow — while improving productivity, quality and efficiency.',
    intro:
      'Musculoskeletal disorders are the injuries that never make the incident report until they are already expensive. Our ergonomics programme finds the tasks that are loading your workforce, redesigns them where redesign is cheap, and trains supervisors to spot the rest.',
    highlight:
      'Ergonomics pays twice: fewer MSD claims and lost days, and a measurable improvement in cycle time and quality on the tasks you redesign.',
    sections: [
      {
        title: 'Assessment',
        body: 'We measure the task rather than the complaint — using recognised assessment tools so results are comparable across sites and over time.',
        bullets: [
          'Task-level risk assessment using REBA, RULA and NIOSH lifting equation',
          'Workstation and office display-screen equipment assessment',
          'Manual handling and repetitive-task analysis on production lines',
          'Discomfort surveys and early-symptom reporting review',
        ],
      },
      {
        title: 'Redesign and control',
        body: 'The output is a change list ranked by cost and effect, not a report that sits in a drawer.',
        bullets: [
          'Workstation layout, height and reach-envelope corrections',
          'Lifting aid, jig and fixture recommendations with specification',
          'Job rotation and work-rest scheduling for high-load tasks',
          'Procurement guidance so new equipment does not reintroduce the risk',
        ],
      },
      {
        title: 'Capability and follow-up',
        body: 'Sustained results need in-house capability — we build it and then measure whether it held.',
        bullets: [
          'Ergonomics awareness training for employees',
          'Supervisor training in early MSD symptom recognition',
          'In-house ergonomics champion development',
          'Re-assessment at agreed intervals with before-and-after metrics',
        ],
      },
    ],
    faq: [
      {
        q: 'How do you show the programme worked?',
        a: 'We baseline before the intervention — MSD-related absence days, discomfort survey scores, claim counts and assessment scores per task — and re-measure at the agreed review point. The comparison is the report.',
      },
      {
        q: 'Does this apply to office environments?',
        a: 'Yes. Display-screen equipment assessment, seating and desk set-up, and hybrid-work guidance are a distinct and commonly requested scope.',
      },
      {
        q: 'What if the fix requires capital investment?',
        a: 'Recommendations are ranked by cost band. Most programmes deliver a large share of the benefit through no-cost and low-cost changes — layout, sequence, rotation — with capital items presented separately for your own business case.',
      },
    ],
  },
  {
    slug: 'mental-health',
    icon: 'brain',
    title: 'Mental Health & Employee Support',
    short: 'Workshops, EAP and manager capability',
    summary:
      'Promoting positive mental health and reducing the stigma around workplace stress, through workshops, individual sessions and a three-gear method of awareness, response and collaboration.',
    intro:
      'Most workplace mental health spending goes on awareness alone, which raises demand without building the capacity to meet it. Our programme runs on three gears — awareness, response, collaboration — so that when someone does come forward, there is a manager who knows what to say and a route to real support.',
    highlight:
      'Three gears: awareness so people recognise it, response so managers can act, collaboration so the support route actually closes the loop.',
    sections: [
      {
        title: 'Awareness',
        body: 'Reduce the stigma that stops people raising a problem while it is still small.',
        bullets: [
          'Mental health awareness workshops, customisable by audience and shift pattern',
          'Stress, burnout and workload-pressure sessions',
          'Campaign material adapted to your workforce and language',
        ],
      },
      {
        title: 'Response',
        body: 'Give line managers a defined, rehearsed set of actions — and confidence about where their role ends.',
        bullets: [
          'Manager training: recognising distress and holding the first conversation',
          'Psychological first aid for peer supporters and responders',
          'Escalation pathways and clear boundaries on manager responsibility',
        ],
      },
      {
        title: 'Collaboration and individual support',
        body: 'Confidential individual sessions with qualified professionals, plus the coordination that keeps HR, the manager and the clinician aligned without breaching confidentiality.',
        bullets: [
          'Individual counselling sessions, in person or remote',
          'Critical-incident and post-incident support for teams',
          'Return-to-work planning after mental-health-related absence',
          'Aggregated, anonymised reporting on utilisation and themes',
        ],
      },
      {
        title: 'Issues the programme addresses',
        body: 'Support is scoped broadly, because the causes of workplace distress usually are not confined to work.',
        bullets: [
          'Workload, role pressure and shift-work strain',
          'Family stress and changes to home and family life',
          'Financial concerns',
          'Sleep problems',
          'Expatriate and outpatient assignments, relocation, culture shock and adaptation',
          'Drug and alcohol-related concerns',
        ],
      },
    ],
    faq: [
      {
        q: 'Is anything an employee says reported back to us?',
        a: 'No. Individual sessions are confidential. Employers receive anonymised, aggregated data — utilisation rates and broad themes — with group sizes large enough that no individual can be identified.',
      },
      {
        q: 'Can workshops be delivered in Arabic?',
        a: 'Yes. Workshops and individual sessions are delivered in Arabic or English, and material is adapted for shift-based and field workforces.',
      },
      {
        q: 'What size of organisation does this suit?',
        a: 'Packages are customisable. Smaller employers usually start with manager training plus a session allocation; larger sites run the full three-gear programme with a fixed workshop calendar.',
      },
    ],
  },
  {
    slug: 'sleep-management',
    icon: 'moon',
    title: 'Sleep Health Management',
    short: 'Fatigue risk in shift and rotational work',
    summary:
      'A specialised sleep management programme delivered by qualified professionals, built on a set of pillars for a comprehensive approach to sleep and fatigue.',
    intro:
      'On a rotating-shift site, fatigue is a safety-critical hazard with a measurable accident curve behind it. Our sleep programme treats it that way — screening for the disorders that shift work hides, and fixing the roster and environment factors that no amount of employee willpower will overcome.',
    highlight:
      'Fatigue is a control problem before it is a personal one. We work on the roster and the environment as well as the individual.',
    sections: [
      {
        title: 'Screen',
        body: 'Find the clinical sleep disorders that shift patterns mask, and the fatigue hotspots in your operation.',
        bullets: [
          'Validated sleep and fatigue screening questionnaires',
          'Obstructive sleep apnoea risk screening, with referral for diagnostics',
          'Fatigue risk mapping by roster, route and role',
          'Safety-critical role review — drivers, crane and mobile-plant operators, control-room staff',
        ],
      },
      {
        title: 'Correct',
        body: 'Address the causes that sit with the employer before asking employees to change habits.',
        bullets: [
          'Shift-roster review against fatigue-risk principles',
          'Rest facility, lighting and accommodation assessment for camp and remote sites',
          'Journey-management and commuting-risk guidance',
          'Fatigue reporting route that does not penalise the reporter',
        ],
      },
      {
        title: 'Sustain',
        body: 'Individual capability and clinical follow-through, so gains hold after the campaign ends.',
        bullets: [
          'Sleep hygiene education tailored to the actual shift pattern',
          'Individual consultations with qualified sleep professionals',
          'Treatment follow-up and fitness-for-duty review',
          'Re-screening at agreed intervals',
        ],
      },
    ],
    faq: [
      {
        q: 'Why screen for sleep apnoea specifically?',
        a: 'Untreated obstructive sleep apnoea is strongly associated with elevated crash and incident risk, and it is systematically under-diagnosed in shift-working populations. Screening the safety-critical roles first gives the largest risk reduction per pound spent.',
      },
      {
        q: 'Will you tell us to change our roster?',
        a: 'If the roster is the dominant fatigue driver, yes — and we will show the analysis behind it. Recommendations are presented with operational alternatives, not as a single take-it-or-leave-it option.',
      },
      {
        q: 'Does this connect to our health surveillance programme?',
        a: 'It should. Where both run together, sleep screening is folded into the periodic medical so employees are seen once, and fatigue findings feed the fitness-to-work decision.',
      },
    ],
  },
  {
    slug: 'smoking-cessation',
    icon: 'no-smoking',
    title: 'Smoking Cessation',
    short: 'A structured quit-smoking programme',
    summary:
      'A specialised quit-smoking programme combining clinical assessment, behavioural support and structured follow-up — with a smoke-free workplace policy behind it.',
    intro:
      'Smoking prevalence among working-age men in Egypt is among the highest in the region, and it drives a large share of the cardiovascular and respiratory findings that show up in periodic medicals. A structured cessation programme is one of the few workplace health interventions with an unambiguous return.',
    highlight:
      'Willpower campaigns fail. Assessment, a quit plan, real behavioural support and follow-up at fixed intervals do not.',
    sections: [
      {
        title: 'How the programme runs',
        body: 'Cohorts run on a fixed calendar, so participants go through it alongside colleagues rather than alone.',
        bullets: [
          'Individual assessment: dependence level, quit history, readiness',
          'A personal quit plan with a target date and identified triggers',
          'Behavioural support sessions, individual or in small groups',
          'Pharmacological support guidance where clinically indicated, via referral',
          'Structured follow-up and relapse-prevention contact points',
        ],
      },
      {
        title: 'The workplace layer',
        body: 'Individual quit attempts survive far better when the environment stops working against them.',
        bullets: [
          'Smoke-free workplace policy drafting and rollout support',
          'Smoking-area review and signage',
          'Awareness campaigns timed to the cohort calendar',
          'Line-manager briefing on supporting participants',
        ],
      },
      {
        title: 'Measurement',
        body: 'Reported against declared quit rates at fixed intervals — not against enrolment numbers.',
        bullets: [
          'Quit rates at 4 weeks, 12 weeks and 12 months',
          'Participation and retention by department',
          'Linked findings from periodic medical examinations, anonymised',
        ],
      },
    ],
    faq: [
      {
        q: 'Can this run in Arabic and across shifts?',
        a: 'Yes. Sessions are delivered in Arabic or English and scheduled around shift patterns, including night-shift cohorts.',
      },
      {
        q: 'What quit rate should we expect?',
        a: 'We report actual measured rates for your cohorts at 4, 12 and 52 weeks rather than quoting an industry average up front. Structured programmes with behavioural support and follow-up consistently outperform advice-only approaches.',
      },
      {
        q: 'Is participation confidential?',
        a: 'Yes. Employers receive participation and outcome data in aggregate only.',
      },
    ],
  },
  {
    slug: 'corporate-life-coaching',
    icon: 'compass',
    title: 'Corporate Life Coaching',
    short: 'Goal setting, mindset and career progression',
    summary:
      'Coaching that promotes workplace wellness by setting specific goals, improving mindset, and empowering employees to advance their careers and contribute to the business.',
    intro:
      'Coaching sits where wellbeing and performance overlap. Where a mental health programme responds to distress, coaching works with people who are functioning and want to do more — which makes it the intervention with the clearest line to retention.',
    highlight:
      'Coaching is not counselling. It is structured, goal-directed, time-bound, and measured against outcomes the employee sets themselves.',
    sections: [
      {
        title: 'Individual coaching',
        body: 'A defined engagement with a qualified coach, structured around goals the employee owns.',
        bullets: [
          'Goal-setting and personal development planning',
          'Mindset, resilience and confidence work',
          'Career progression planning and skills-gap review',
          'Work-life boundary and workload management',
        ],
      },
      {
        title: 'Team and leadership coaching',
        body: 'The same discipline applied to groups, where the constraint is usually communication rather than capability.',
        bullets: [
          'New and first-time manager transition coaching',
          'Team communication and collaboration sessions',
          'Change-transition support during restructure or relocation',
          'High-potential and succession-pipeline development',
        ],
      },
      {
        title: 'Programme design',
        body: 'Coaching is bought by the block and scoped against a business outcome, so it stays accountable.',
        bullets: [
          'Session blocks by cohort, in person or remote',
          'Coach matching to the employee’s role and language',
          'Progress review against the goals set at engagement start',
          'Anonymised programme reporting for HR',
        ],
      },
    ],
    faq: [
      {
        q: 'How is coaching different from the mental health programme?',
        a: 'Coaching is forward-looking and goal-directed for employees who are functioning well. The mental health programme is clinical and responsive. If a coaching conversation surfaces a clinical concern, the coach refers into that pathway with the employee’s consent.',
      },
      {
        q: 'How many sessions does an engagement take?',
        a: 'A typical individual engagement is six to eight sessions over three to four months. Leadership transition coaching often runs longer.',
      },
      {
        q: 'Who chooses the participants?',
        a: 'You do — commonly high-potential employees, newly promoted managers, or a whole team going through change. Participation should be voluntary; coaching that is imposed rarely produces results.',
      },
    ],
  },
  {
    slug: 'crisis-management',
    icon: 'siren',
    title: 'Crisis Management & Continuity',
    short: 'Pandemic response and business continuity',
    summary:
      'Crisis management plans including pandemic response and business continuity planning, tailored to the specific needs of each site.',
    intro:
      'Every organisation discovered in 2020 whether its continuity plan was real. We write plans that name people rather than departments, set thresholds that trigger action without a meeting, and get tested before the event that needs them.',
    highlight:
      'A plan that has never been exercised is a document, not a capability. Every plan we write comes with a drill.',
    sections: [
      {
        title: 'Plan',
        body: 'Site-specific documents built from your operation, not a template with your logo on it.',
        bullets: [
          'Health crisis and outbreak response plans',
          'Pandemic and communicable disease preparedness',
          'Business continuity planning for critical operations',
          'Named roles, deputies and a working call-out tree',
          'Defined escalation thresholds and decision authority',
        ],
      },
      {
        title: 'Prepare',
        body: 'Build the capability and the stock before you need either.',
        bullets: [
          'Crisis management team formation and role training',
          'Isolation, screening and case-management protocols',
          'PPE and medical consumable stock planning',
          'Internal and external communication templates',
        ],
      },
      {
        title: 'Exercise and review',
        body: 'Test the plan against a realistic scenario, then fix what the test broke.',
        bullets: [
          'Tabletop exercises and live drills',
          'After-action review with a corrective action register',
          'Annual plan review and version control',
          'Post-incident support for affected teams',
        ],
      },
    ],
    faq: [
      {
        q: 'We already have a continuity plan. Can you just review it?',
        a: 'Yes — a gap review against your current plan, followed by a tabletop exercise to test it, is a common and much cheaper starting scope than a rewrite.',
      },
      {
        q: 'Does this cover multi-site operations?',
        a: 'Yes. Multi-site plans define what is held centrally and what each site owns, which is usually where single-site plans fail when they are scaled up.',
      },
      {
        q: 'How often should a plan be exercised?',
        a: 'At least annually, and after any significant change to the operation, the site layout or the crisis management team.',
      },
    ],
  },
];

/* ------------------------------------------------------------------------ */

export const industries = [
  { icon: 'factory', title: 'Manufacturing & FMCG', body: 'Production-line ergonomics, noise and chemical surveillance, shift-fatigue management and on-site clinic cover.' },
  { icon: 'hard-hat', title: 'Construction & Infrastructure', body: 'Work-at-height and confined-space medicals, site first aid capability, standby cover for peak phases.' },
  { icon: 'flame', title: 'Oil, Gas & Petrochemicals', body: 'Remote-site medical cover, medevac planning, biological monitoring and emergency responder fitness.' },
  { icon: 'pill', title: 'Pharmaceutical & Chemical', body: 'Exposure-specific biological monitoring, cleanroom health protocols and occupational disease investigation.' },
  { icon: 'truck', title: 'Logistics & Transport', body: 'Driver medical fitness, sleep apnoea screening, fatigue and journey-management risk programmes.' },
  { icon: 'building', title: 'Corporate & Services', body: 'Display-screen ergonomics, mental health and coaching programmes, executive health checks.' },
  { icon: 'utensils', title: 'Hospitality & Food', body: 'Food-handler medical certification, dermatological screening and periodic re-examination.' },
  { icon: 'zap', title: 'Energy & Utilities', body: 'Electrical-work medical standards, emergency response training and site crisis planning.' },
];

export const process = [
  { step: '01', title: 'Assess', body: 'We walk the site, review your hazard register and incident history, and establish what your exposures actually are — before proposing anything.' },
  { step: '02', title: 'Design', body: 'A written programme scoped to those exposures: protocols, intervals, roles covered, deliverables and a cost you can budget against.' },
  { step: '03', title: 'Deliver', body: 'Examinations, training and interventions run on a fixed calendar, mostly on-site, scheduled around your shift pattern.' },
  { step: '04', title: 'Report', body: 'Fitness decisions to managers, anonymised trends to HSE and HR, and an audit-ready record set to your retention schedule.' },
  { step: '05', title: 'Review', body: 'Scheduled programme review against the baseline metrics, with the scope adjusted as your operation and its risks change.' },
];

export const differentiators = [
  { icon: 'map-pin', title: 'On your site, not in our waiting room', body: 'Mobile teams and portable diagnostics mean examinations happen where your people already are. No travel time, no lost shifts, far higher attendance.' },
  { icon: 'scale', title: 'Built around compliance obligations', body: 'Programmes are scoped against Egyptian Labour Law No. 12 of 2003 and its executive regulations, and mapped to ISO 45001 and client HSE requirements where those apply.' },
  { icon: 'lock', title: 'Confidentiality that is actually enforced', body: 'Clinical detail stays with the physician. Employers receive fitness decisions and anonymised aggregates — a boundary we do not negotiate.' },
  { icon: 'chart', title: 'Reported against a baseline', body: 'We measure before we intervene and re-measure after. If a programme is not moving its metric, that shows up in the review rather than being quietly absorbed.' },
  { icon: 'users', title: 'One provider, one record', body: 'Surveillance, ergonomics, mental health and emergency readiness under one contract, with one employee record — instead of four vendors and four spreadsheets.' },
  { icon: 'globe', title: 'Arabic and English throughout', body: 'Examinations, workshops, coaching and written material delivered in both languages, adapted for field and shift-based workforces.' },
];

// PLACEHOLDER — every figure below must be replaced with a verified number
// or the whole band removed before launch. Unverifiable claims are a
// regulatory and reputational risk in occupational health.
export const stats = [
  { value: 12000, suffix: '+', label: 'Medical examinations delivered', note: 'PLACEHOLDER' },
  { value: 60, suffix: '+', label: 'Employer programmes supported', note: 'PLACEHOLDER' },
  { value: 8, suffix: '', label: 'Governorates covered', note: 'PLACEHOLDER' },
  { value: 24, suffix: '/7', label: 'On-site emergency cover available', note: 'PLACEHOLDER' },
];

export const values = [
  { title: 'Clinical independence', body: 'A fitness decision is a medical judgement. It is not adjusted to suit a staffing plan, a deadline or a commercial relationship.' },
  { title: 'Prevention over paperwork', body: 'Compliance records are a by-product of a programme that works. They are not the point of it, and we do not sell them as one.' },
  { title: 'Evidence, not enthusiasm', body: 'Interventions are chosen because there is evidence behind them and measured against a baseline we set in advance.' },
  { title: 'Confidentiality without exception', body: 'The trust of the workforce is the asset the entire programme runs on. It survives exactly one breach.' },
];

// PLACEHOLDER — replace with real, attributable client quotes and written
// permission to publish, or delete the section from the homepage template.
export const testimonials = [
  { quote: 'The surveillance programme found a hearing-conservation gap on two lines that our previous provider had been signing off for three years. The difference was that they assessed the exposure before they designed the protocol.', name: 'HSE Manager', role: 'Manufacturing, 10th of Ramadan City', note: 'PLACEHOLDER' },
  { quote: 'Mobilised a full site clinic and standby ambulance cover for a six-week turnaround in under three weeks, and the reporting was clean enough to hand straight to our parent company audit.', name: 'Operations Director', role: 'Petrochemicals, Suez', note: 'PLACEHOLDER' },
];

export const faqs = [
  {
    q: 'What does Egyptian law require an employer to provide?',
    a: 'Labour Law No. 12 of 2003 and its executive regulations place duties on employers covering occupational health provision, medical examination of workers exposed to hazards, record keeping and the reporting of occupational disease. The specific obligations depend on your sector, headcount and hazard profile — we scope every programme against the obligations that actually apply to you rather than a generic checklist.',
  },
  {
    q: 'Do you work on-site or do employees come to a clinic?',
    a: 'Predominantly on-site. Mobile teams with portable audiometry, spirometry and phlebotomy handle the large majority of examinations at your location, which removes travel time and lifts attendance rates substantially. Specialist referrals and diagnostics run through our clinic network.',
  },
  {
    q: 'How is employee medical confidentiality handled?',
    a: 'Clinical findings stay between the examining physician and the employee. The employer receives a fitness-to-work decision — fit, fit with restrictions, or temporarily unfit — together with anonymised aggregate reporting at group sizes that prevent re-identification.',
  },
  {
    q: 'Can you cover multiple sites across Egypt?',
    a: 'Yes. Multi-site programmes run on a single protocol set and a single employee record system, with a shared reporting calendar so central HSE sees consistent data from every location.',
  },
  {
    q: 'What is the minimum engagement size?',
    a: 'There is no fixed minimum. Smaller employers typically start with a health risk assessment plus pre-employment medicals, then extend the scope once the risk picture is clear. Larger sites usually contract an annual programme.',
  },
  {
    q: 'How long does mobilisation take?',
    a: 'For a standard scope, two to three weeks from signature to the first delivery day: site visit and hazard review, protocol sign-off, scheduling, then delivery. Emergency and standby cover can be mobilised faster.',
  },
  {
    q: 'Are services delivered in Arabic?',
    a: 'Yes — examinations, workshops, coaching and all written employee-facing material are available in Arabic and English.',
  },
  {
    q: 'Can you take over an existing programme from another provider?',
    a: 'Yes. Transition work normally starts with a gap review of the inherited records and protocols, so you know what is compliant, what has lapsed and what needs re-examination before we take responsibility for it.',
  },
];

export const about = {
  vision:
    'To become the premier provider of top-tier occupational health services in Egypt, setting the standard for excellence in the industry.',
  mission:
    'To give Egyptian employers occupational health programmes that are clinically rigorous, practical to run inside a working operation, and measured against outcomes rather than activity.',
  story: [
    'Occuo Health was built around a simple observation: most occupational health in Egypt is bought as a compliance transaction. A clinic day is booked, forms are signed, a file is stored, and nothing about the workplace changes. The exposures that caused the problem are still there the following week.',
    'We structure the work the other way round. Every programme starts with what your people are actually exposed to — the chemicals, the noise, the loads, the roster, the pressure — and the medical activity follows from that. The records that satisfy an inspector come out of the process rather than being the product.',
    'That means we will sometimes tell you a test you were planning to buy is the wrong test for your hazard. It also means that when a programme is not moving the number it was designed to move, it shows up in the scheduled review instead of being quietly repeated for another year.',
  ],
  commitments: [
    'Programmes scoped from a documented site health risk assessment',
    'Qualified occupational physicians and specialist practitioners',
    'Clinical confidentiality maintained as an absolute boundary',
    'Delivery on-site and around your shift pattern',
    'Baseline measurement and scheduled programme review',
    'Arabic and English across every service',
  ],
};
