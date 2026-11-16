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

## Limitations and next steps

- All health data is simulated; thresholds are unvalidated examples pending medical review.
- The interface is in Spanish today; English localization is planned.
- Local AI (Ollama) and n8n orchestration, additional biomarkers (VO₂max, bone density, retina, cabin sensors) and real wearable integration are "Coming soon".

Spanish documentation: see `README.md`.
