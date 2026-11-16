# Astronaut Health Monitor

**Offline crew health guidance for deep-space missions** — NASA Space Apps Challenge 2026 (Artemis Team)

An offline-first web tool that lets a crew member assess their own health during a mission and know what to do next, even when contact with Earth is delayed or unavailable. It runs on an **on-board local server with no internet connection**. The ground is optional and asynchronous: it receives alerts and summaries when a link is available.

> **All data in this project is simulated.** The tool provides **guidance only and does not diagnose**. Thresholds are reference values, **not clinically validated**, and every one is labeled as an example value until a medical professional reviews it. This is an educational prototype and is **not affiliated with or endorsed by NASA**.

> **Language note:** the interface and this README are in English. Two parts still accept Spanish input only: the symptom analyzer and the floating Flight Surgeon assistant (its English version is on the roadmap). A Spanish copy of this README is in [README_ES.md](README_ES.md).

## What it does

- **Crew dashboard** with 14 health indicators (blood pressure, heart rate, SpO₂, glucose, temperature, BMI, hydration, bone loss, muscle mass, daily radiation, cumulative dose, sleep, stress, vision/SANS) against each crew member's personal baseline, shown as Normal / Caution / Critical, with a 7-day trend and a body figure.
- **Traceability to NASA requirements.** Each alert cites the requirement behind it (NASA-STD-3001 Vol. 1 Rev. C, NASA HRP observation, guide, or clinical reference), with its revision and an "example value" label. Standard and HRP data are kept in separate layers: the standard defines alert thresholds, HRP provides context and projections.
- **11 body systems** (the 9 sections of the reference document plus metabolism and radiation). Systems without any indicator are shown as "no coverage yet" rather than "normal".
- **Mission-aware escalation.** Care levels 3–5 and communication delay by environment (space station 0 min, spacecraft 1, lunar base 2, Mars base 20) decide whether the ground is reached synchronously or asynchronously. A **ground queue** holds outgoing alerts until a link is available.
- **Flight Surgeon assistant (simulated).** A floating assistant detects patterns (possible hypoxia, fever, dehydration, acute stress, irregular pulse, sleep loss, sensor failure), opens automatically, explains what to do using the guide, the crew member's 7-day history and record, and offers to alert the ground. It is **rule-based and simulated** (no AI model is called), uses hypothesis language, and has a deterministic safety filter. A real local model (Ollama) orchestrated with n8n is on the roadmap as "Coming soon".
- **Simulation engine and control panel** with labeled scenarios (fever, hypoxia, dehydration, stress, irregular pulse, sleep loss, sensor failure) that produce realistic live measurements and record the ground truth for evaluation.
- **Data quality.** Sudden jumps and flat signals are flagged as "suspect data" instead of rejected; multi-day rules (for example short sleep over consecutive days) can raise a state.
- **Wearable ingestion API.** A token-authenticated endpoint with token expiry, per-token rate limiting, optional HMAC signature and battery reporting, designed for a wearable bridge. Tested with simulated payloads.
- **Security and privacy.** Role-based access (crew member, Crew Medical Officer, Flight Surgeon) behind a feature flag, an access audit log without medical values, encrypted medical-record fields, and a 15-day retention command for live measurements.
- **Offline-first.** Local deployment, installable PWA shell, no external services at runtime.

## Technology stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+ (8.2 recommended), Laravel 10 |
| Database | Local MySQL |
| UI | Livewire 3, Alpine.js, Tailwind CSS 3.4, Vite 5, Chart.js 4 |
| Real time | Laravel Reverb + Laravel Echo (self-hosted, no external service) |
| Installable app | PWA (web manifest and service worker) |
| Tests | PHPUnit 10 (more than 900 tests) and the Node test runner for the Indicators model |
| Design | IBM Plex Sans and Mono, Material Symbols Rounded, light / dark / high-contrast themes |

Full architecture, modules, screens, commands and data model: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Operational limits versus HRP data

The project keeps two separate data layers, because the two NASA sources have different purposes: **NASA-STD-3001 Vol. 1 Rev. C** turns risks into requirements that flight programs must meet (operational thresholds), while the **NASA Human Research Program (HRP)** publishes observed data and risks (reference baselines and projections, not limits). The comparison below summarizes the project's reference document, *Operational limits and current clinical thresholds* (dated 14 Nov 2026; Word file in [docs/](docs/NASA_STD_3001_limites_operativos_vs_HRP.docx), English text in [docs/fuentes/en/](docs/fuentes/en/README.md)). It is **paraphrased from the project's reference document, not a verbatim quote of the standard**; check the current revision of the standard before using any value in a real system.

| Domain | Operational limit (NASA-STD-3001) | HRP figure | Use in the software |
|---|---|---|---|
| Radiation | 600 mSv career; per-organ limits; 250 mSv per solar particle event | No limits published | Alert threshold |
| Bone | At least 95% of pre-mission BMD at hip and spine; at least 90% at femoral neck | 1–1.5% loss per month | Threshold versus projection model |
| Aerobic fitness | At least 80% of pre-mission capacity | Mean loss 10–12% | Threshold versus reference |
| Muscle strength | At least 80% of baseline values | No threshold | Threshold |
| Nutrition | 100% of calculated requirements | 200+ foods on board | Requirement versus context |
| Sleep | Limits must be defined | 6–6.5 h average | Software defines and audits |
| Behavior | Clinically accepted values | Qualitative description | Clinical validation requirement |
| Sensorimotor | Normal clinical values; task-specific limits | Qualitative description | Software defines and audits |

Radiation has several simultaneous limits: career (under 600 mSv effective dose), galactic cosmic rays for habitable systems of more than 60 days (under 1.3 mSv/day in free space and under 0.9 mSv/day on a planetary surface), solar particle events (under 250 mSv), nuclear technologies (under 20 mSv per mission year, prorated), and limits per organ or tissue at 30 days, 1 year and career.

**How this is applied in the project**

- **Two layers, not one.** A requirements table (standard) and observations (HRP). Alert thresholds come from the first; projections and context from the second. Bone-loss rates are shown as "Projection (HRP)", never as a requirement.
- **Every alert cites its requirement,** with its revision and type, and an "example value" label until a medical professional validates it.
- **Standard revision is stored with each requirement,** so a standard update does not invalidate history.
- **Implemented:** career dose and the galactic cosmic ray daily limit by environment (free space for station and spacecraft, planetary surface for lunar and Mars bases), blood pressure bands, temperature jump over a personal baseline, and multi-day sleep rules.
- **Prepared but not fed with data yet:** the solar particle event rule (250 mSv) and the baseline rules for bone density, aerobic fitness and retina, which need biomarkers that are marked "Coming soon". Per-organ and per-year radiation limits need organ-level dose data that the system does not have, so they are not implemented.
- **Non-numeric requirements** (sleep, sensorimotor, behavior) are tracked as requirements that the software can help define, document and audit.

## NASA data and resources used

- NASA-STD-3001, Volume 1, Revision C — https://www.nasa.gov/wp-content/uploads/2023/11/nasa-std-3001-vol-1-rev-c-with-signature.pdf
- NASA HRP, Five Hazards of Human Spaceflight — https://www.nasa.gov/hrp/hazards/
- NASA HRP, Risks of Human Spaceflight — https://www.nasa.gov/hrp/risks/
- NASA HRP, Ongoing Research — https://www.nasa.gov/hrp/ongoing-hrp-research/
- Human Research Roadmap — https://humanresearchroadmap.nasa.gov/
- NASA HRP, Space Radiation — https://www.nasa.gov/hrp/radiation/
- NASA Human Performance reference (GCR effective dose) — https://www.nasa.gov/reference/4-0-human-performance/
- NASA OCHMO-STD-100.1A (astronaut candidate medical exam) — https://www.nasa.gov/wp-content/uploads/2023/04/ochmo-std-100.1a.pdf
- NASA NTRS 20200001709 (Apollo crew heart rate data) — https://ntrs.nasa.gov/api/citations/20200001709/downloads/20200001709.pdf
- NASA Human System Risk Board — https://www.nasa.gov/hhp/human-system-risks/
- NASA NEPP (space electronics and radiation effects) — https://nepp.nasa.gov/

Other sources: Canadian Space Agency articles on radiation monitoring and on science aboard the ISS (https://www.asc-csa.gc.ca/eng/news/articles/2026/2026-02-26-monitoring-radiation-astronaut-health.asp, https://www.asc-csa.gc.ca/eng/iss/science/why-do-we-conduct-science-experiments-in-space.asp); SciELO article on body temperature in orbit (https://www.scielo.org.mx/scielo.php?script=sci_arttext&pid=S0026-17422014000400020); mood scales in spaceflight (https://pmc.ncbi.nlm.nih.gov/articles/PMC6218582/); sleep in space (https://anmdecolombia.org.co/dormir-en-el-espacio-exterior); vital signs reference (https://medlineplus.gov/ency/article/002341.htm).

Open-source software and assets: Laravel (MIT), Livewire, Laravel Reverb and Echo, Chart.js, Tailwind CSS, Vite, MySQL, IBM Plex Sans and Mono (SIL Open Font License 1.1), Material Symbols Rounded (Apache License 2.0).

The full list of sources, how each one is used, the project's reference documents (`docs/fuentes/`) and licenses is in [docs/SOURCES.md](docs/SOURCES.md).

## AI use disclosure

Generative AI tools were used in this project: **Anthropic Claude (Claude Code and Claude Design)** helped write application code, automated tests, seed data, documentation and user-interface design mockups. The team directed the work, reviewed the results and chose the requirements and data sources. The "Flight Surgeon" assistant inside the application is **not** an AI model: it is a deterministic, rule-based simulation and is labeled as simulated in the interface. No NASA logos, flags or mission identifiers are used.

## Run it locally

Requirements: PHP 8.1+, Composer, Node.js, MySQL.

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate   # set your MySQL credentials in .env
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start                            # live updates
php artisan salud:simulador-vivo                    # live simulation (switch it on from /simulacion)
```

Tests (they use a separate `healts_nasa_test` database, never your data):

```bash
# create an empty database named healts_nasa_test, then:
DB_DATABASE=healts_nasa_test php artisan migrate
php artisan test
```

## Tests and quality

```bash
php artisan test              # against healts_nasa_test, inside transactions
php artisan test --testdox    # readable test names
node --test resources/js/indicador/modelo.test.mjs
php artisan i18n:audit        # language audit of the interface
```

## Limitations and next steps

- All health data is simulated; thresholds are unvalidated example values pending medical review.
- The interface is in English. The symptom analyzer and the floating Flight Surgeon assistant still accept Spanish input only; an English version is planned.
- The Flight Surgeon assistant is rule-based and simulated. A local AI model (Ollama), n8n orchestration, additional biomarkers (VO₂max, bone density, retina, cabin sensors) and real wearable integration are "Coming soon".
- Deployment items outside the application: MySQL disk encryption and TLS for the web server and the database connection.

## Documentation

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): architecture, technology, modules, screens, commands.
- [docs/SOURCES.md](docs/SOURCES.md): sources, references, licenses and AI disclosure.
- [docs/GLOSSARY_EN.md](docs/GLOSSARY_EN.md): English terminology and style guide.
- [docs/fuentes/en/](docs/fuentes/en/README.md): English translations of the reference source documents (Spanish originals in `docs/fuentes/`).
- `docs/` also contains the original requirements and technical plan (Spanish PDFs) and the Space Apps presentation material.

Spanish version of this README: [README_ES.md](README_ES.md). Project based on Laravel, MIT license.
