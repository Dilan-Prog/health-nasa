# English glossary and style guide (single source of truth for the UI)

Audience: NASA reviewers. Everything a user can see or hear must be technical, correct, consistent US English.
Everything is simulated; the system gives guidance only and never diagnoses.

## Style
- US English, clear technical register, short sentences, no colloquialisms.
- Sentence case for titles ("What to do now"). Small uppercase labels are done by CSS: write normal text.
- Severity labels everywhere: NORMAL / CAUTION / CRITICAL (green / amber / red). Dashboard used BIEN/REVISAR/CRÍTICO and Body systems NORMAL/PRECAUCIÓN/CRÍTICO: unify to those three. "Preventiva" -> Preventive.
- Hypothesis language, never diagnosis: "possible hypoxia", "hypothesis to rule out".
- Fixed notices: "Guidance only, not a diagnosis." and for the simulated AI: "This is a simulated version of a local AI. It can make mistakes. Everything is simulated, based on the simulated crew data. It does not provide real medical advice."
- Times in UTC, 24 h ("03:29 UTC"). Dates "15 Nov 2026". Decimal point, thousands comma ("1,000"). Keep the typographic minus "−". Numeric input must still accept comma and point.
- Units: lpm->bpm; %/mes->%/month; % misión->% of mission; mSv/día->mSv/day; L/día->L/day; kg/m², mmHg, mg/dL, °C, mSv, h, D unchanged.
- NASA terms in official form: CMO (Crew Medical Officer), Flight Surgeon, EVA, SAS, SANS, GCR, SPE, BMD, VO₂max, ARED, T2, CEVIS, LBNP, PVT, LEO, Care Level 3–5.
- HRP risks use NASA's official English names (e.g. Risk of Altered Immune System Responses; Risk of Altered Sensorimotor and Vestibular Function; Risk of Cardiovascular Adaptations; Risk of Decompression Sickness; Risk of Host-Microorganism Interactions; Risk of Hypoxia; Risk of Inadequate Food and Nutrition; Risk of Injury and Compromised Performance from Spacewalks (EVA); Risk of Reduced Cardiorespiratory and Musculoskeletal Fitness; Risk of Spaceflight Associated Neuro-ocular Syndrome (SANS); Risk of Spaceflight-Induced Bone Changes; Risk of Behavioral Changes and Psychiatric Disorders; Risk of Inadequate Teamwork; Risk of Inadequate Sleep and Irregular Schedules; Risk of Inadequate Systems Operations Support; Risk of Injury from Dynamic Loads; Risk of Radiation-Induced Cancers). The 5 hazards: Space Radiation; Isolation and Confinement; Distance from Earth; Gravity Fields; Hostile/Closed Environments.
- Requirement texts: faithful translation of the current Spanish; do NOT present them as verbatim quotes of the standard; keep the official source link. Mark the section "paraphrased from the project's reference document".
- No NASA logo/emblem or anything implying sponsorship. Legal notice: educational prototype with simulated data, not medical advice, not affiliated with or endorsed by NASA. Citing NASA-STD-3001 and the HRP as public sources is fine.
- Never add, remove or "improve" medical content. If unsure of a clinical/aerospace term, add it to docs/TRANSLATION_REVIEW.md (term, context, options).
- Do NOT translate: config keys, columns, tables, stored values (normal|precaucion|critico, ok|warn|crit, activas|graves|resueltas), scenario keys (fiebre, hipoxia…), class/method names, named routes, CSS classes, HTML ids, URLs, icon names, application-code comments, README, docs/**, user-written text (symptoms, notes, crew names).

## Navigation and screens
Panel -> Dashboard · Registrar -> Log · Indicador -> Indicators · Sistemas -> Body systems · Historial -> History · Alertas -> Alerts · Guía -> Guide · Rutinas -> Routines · Misión -> Mission · Estación y tierra -> Station & ground · Tripulación -> Crew · Simulación -> Simulation · Cola a tierra -> Ground queue · Médico de vuelo -> Flight Surgeon · Botiquín -> Medical kit · Aviso legal -> Legal notice · Acceso -> Sign in · Cerrar sesión -> Sign out · Auditoría -> Audit log · Apoyo -> Support · Tema Oscuro/Claro/Alto -> Dark / Light / High contrast.

## Dashboard
Tripulante -> Crew member · Día de misión -> Mission day · Órbita -> Orbit · Última lectura -> Last reading · Altura/Peso/Sangre -> Height/Weight/Blood type · Qué hacer ahora -> What to do now · Enlace activo -> Link active · Respuesta de tierra en ~1 min -> Ground response in ~1 min · Repetir medición -> Repeat measurement · Ver guía -> View guide · Avisar a tierra -> Alert ground · Tendencia 7 días -> 7-day trend · Tu base -> Your baseline · Hoy -> Today · Mín/Máx -> Min/Max · "Esta herramienta orienta, no diagnostica. Ante cualquier duda, contacta a tierra." -> "This tool provides guidance only; it does not diagnose. If in doubt, contact the ground."

## States and marks
Normal -> Normal · Precaución -> Caution · Crítico -> Critical · Sospechoso -> Suspect data · Simulado -> Simulated · Próximamente -> Coming soon · Sin cobertura todavía -> No coverage yet · Falta medir -> Not yet measured · Valor de ejemplo -> Example value (unvalidated) · Pendiente de revisión médica -> Pending medical review · Pendiente de verificación médica -> Pending medical verification · Fuentes -> Sources · Requisito -> Requirement · Revisión -> Revision · Norma/Guía/HRP/Referencia -> Standard / Guide / HRP / Clinical reference.

## Indicators
Presión arterial -> Blood pressure · Frecuencia cardíaca -> Heart rate · Saturación O₂ -> SpO₂ · Glucosa -> Glucose · Temperatura -> Temperature · IMC -> BMI · Pérdida ósea (cadera) -> Bone loss (hip) · Masa muscular -> Muscle mass · Radiación diaria -> Daily radiation · Dosis acumulada -> Cumulative dose · Hidratación -> Hydration · Sueño -> Sleep · Estrés / cognitivo -> Stress / cognitive · Visión (SANS) -> Vision (SANS) · Ánimo -> Mood.

## Body systems (11)
Cardiovascular · Respiratory · Musculoskeletal · Sensory & vestibular · Ocular (SANS) · Immune · Hematologic · Mental health · Sleep & circadian rhythm · Metabolism · Radiation.

## Roles and mission
Tripulante / CMO / Flight Surgeon -> Crew member / Crew Medical Officer (CMO) / Flight Surgeon · Nivel de atención -> Care level · Fases prevuelo/vuelo/posvuelo -> Pre-flight / In-flight / Post-flight · Entornos: estación -> Space station (LEO), nave -> Spacecraft (transit), base lunar -> Lunar base, base marciana -> Mars base · "Especialista de misión" -> Mission Specialist.

## Medical kit
prometazina -> promethazine · meclizina -> meclizine · ondansetrón -> ondansetron · melatonina -> melatonin · zolpidem · zaleplón -> zaleplon · modafinilo -> modafinil · cafeína -> caffeine · ibuprofeno -> ibuprofen · acetaminofén -> acetaminophen · aspirina -> aspirin · ketorolaco -> ketorolac · amoxicilina/clavulanato -> amoxicillin/clavulanate · azitromicina -> azithromycin · ciprofloxacino -> ciprofloxacin · valaciclovir -> valacyclovir · epinefrina -> epinephrine · oxígeno al 100 % -> 100 % oxygen · enoxaparina -> enoxaparin. Groups: Mareo por adaptación -> Space adaptation (motion sickness) · Sueño y fatiga -> Sleep & fatigue · Analgesia e inflamación -> Pain & inflammation · Antimicrobianos y antivirales -> Antimicrobials & antivirals · Emergencias -> Emergency.

## Floating Flight Surgeon
Médico de vuelo (simulado) -> Flight Surgeon (simulated) · Entendí -> What I understood · Hipótesis a descartar -> Hypotheses to rule out · Del botiquín -> From the medical kit · Seguimiento -> Follow-up · Cuándo escalar -> When to escalate · Datos que usé -> Data used · Avisar a tierra ahora -> Alert ground now · Me ayudó / No me ayudó -> It helped / It did not help · Entendido -> Got it.

## Additions found during translation
(append new terms here: Spanish -> English)
