# Architecture and technology

Astronaut Health Monitor is an **offline-first** web application that runs on an on-board local server. Crew members use it from any device on the local network; the ground is optional and asynchronous. **All data is simulated** and the system provides **guidance only: it does not diagnose or prescribe.**

## Overview

```
[Each crew member's device] <-- local network --> [On-board Laravel server]
       browser or PWA                              |- Rules engine (PHP)
                                                   |- Local MySQL (history)
                                                   |- Reverb (real-time updates)
                                                   |- Ground alert queue
                                                            |  (only when a link exists)
                                                            v
                                                   [Flight Surgeon on the ground]
```

## Technology stack

| Layer | Technology | Notes |
|---|---|---|
| Backend | PHP 8.1+ (8.2 recommended), Laravel 10 | Rules engine, services, API |
| Database | MySQL (local) | Separate `healts_nasa_test` database for tests |
| UI | Livewire 3, Alpine.js (bundled with Livewire), Tailwind CSS 3.4, Vite 5 | No SPA framework |
| Charts | Chart.js 4 and hand-written SVG | Indicators screen and mini charts |
| Real time | Laravel Reverb + Laravel Echo (pusher-js protocol client) | Self-hosted, no external service |
| Installable app | PWA (web manifest + hand-written service worker) | Offline page included |
| API auth | Per-device token, optional HMAC signature | Laravel Sanctum is installed for the framework user endpoint |
| Tests | PHPUnit 10, Node test runner (`node --test`) for the Indicators JavaScript model | Database tests run inside transactions |
| Design | IBM Plex Sans and Mono (SIL OFL 1.1), Material Symbols Rounded (Apache 2.0), OKLCH colour tokens | Light, dark and high-contrast themes |

No external service is required at runtime. The only external connection is the optional link to the ground (`config/tierra.php`).

## Main modules

- **Rules engine** (`app/Services/Salud`): validates measurements (`ValidadorMedicion`), evaluates them against thresholds (`EvaluadorEstado`), applies multi-day window rules (`EvaluadorVentanas`), flags suspect data (`CalidadSenal`), and decides escalation by care level and communication delay (`ReglasEscalamiento`). `ProcesadorMediciones` orchestrates validation, evaluation, storage, ground alerts and the real-time event.
- **Traceability** (`ResolvedorRequisito`, `config/trazabilidad.php`, `requisitos` table): every evaluation and every alert cites the requirement behind it (NASA-STD-3001 standard, NASA HRP observation, guide, or clinical reference) with its revision and an "example value" label. Requirements are resolved by a stable key, not by their text.
- **Personal baselines** (`LineaBasePersonal`, `DesviacionBase`): a moving baseline for trends and an optional pre-flight baseline (`linea_base` table) used for deviation percentages and temperature jumps.
- **Mission profile**: environment (space station, spacecraft, lunar base, Mars base) and care level 3–5 decide whether the ground is reached synchronously or the crew triages autonomously.
- **Simulation** (`SimuladorVivo`, `ControlSimulacion`, `/simulacion`): circadian rhythm, correlated sensor noise, missing data, exercise and labeled scenarios (fever, hypoxia, dehydration, stress, irregular pulse, sleep loss, sensor failure). Scenarios are recorded as ground truth. Everything is configured in `config/simulacion.php`.
- **Pattern detector** (`app/Services/Ia/DetectorPatrones`, `config/patrones.php`): combines indicator states into clinical-pattern cases (hypotheses). It never produces a diagnosis.
- **Flight Surgeon assistant (simulated)** (`OrientadorSimulado`, `FiltroSeguridad`, `MedicoFlotante`): a deterministic, rule-based advisor. It explains what to do from the guide, the 7-day history and the crew member's record, using hypothesis language only. A deterministic safety filter blocks doses, drug recommendations and definitive-diagnosis wording. No language model is called. The advisor sits behind an interface so a real local model can replace it later.
- **Security and privacy**: roles (crew member, Crew Medical Officer, Flight Surgeon) behind a feature flag, encrypted medical-record fields (AES-256 via `APP_KEY`), an access audit log without medical values, and retention commands.
- **Ground queue** (`EnvioATierra`, `tierra:enviar`): outgoing alerts wait until a link exists.
- **Wearable API** (`POST /api/mediciones`): token-authenticated, per-token rate limit, optional HMAC signature, battery reporting.

## Screens

URLs are kept as they are in the code (Spanish path names); the screens are in English.

| Screen | Path | Purpose |
|---|---|---|
| Dashboard | `/` | Indicators, body figure, what to do now |
| Crew | `/tripulacion` | Crew cards; new crew member wizard at `/tripulacion/nuevo` and record at `/tripulacion/{id}/expediente` |
| Indicators | `/indicador` | Analysis of 14 markers: bars, lines, forecast, heat map, compare |
| Log | `/registrar` | Record measurements, mood and pain on the body figure |
| Routines | `/rutinas` | Weekly plan, agenda and activity log |
| Alerts | `/alertas` | Inbox, board and escalation ladder |
| Guide | `/guia` | Educational guide per indicator, with sources |
| Mission | `/mision` | Mission context, care level and phase |
| Flight Surgeon | `/medico-de-vuelo` | Guidance cards, consultations, emergency rule |
| History | `/historial` | Measurement history |
| Body systems | `/sistemas` | 11 body systems and coverage |
| Medical kit | `/botiquin` | Informational inventory |
| Ground queue | `/cola-a-tierra` | Outgoing alerts and link state |
| Simulation | `/simulacion` | Engine control panel (non-production only) |
| Audit log | `/auditoria` | Access log (Flight Surgeon) |
| Legal notice | `/aviso-legal` | Educational prototype, simulated data, no NASA affiliation |
| Sign in | `/acceso` | Login (when the feature flag is on) |

## Commands

| Command | Purpose |
|---|---|
| `php artisan serve --host=0.0.0.0 --port=8000` | Web application |
| `php artisan reverb:start` | Real-time server |
| `php artisan salud:simulador-vivo` | Live simulation (enable it from `/simulacion`) |
| `php artisan salud:simular-mision` | Create a mission with simulated history |
| `php artisan tierra:enviar` | Send pending alerts when a link exists |
| `php artisan mediciones:podar` | Delete live measurements older than N days (dry run by default; `--confirmar` to delete) |
| `php artisan auditoria:podar` | Delete old audit records (dry run by default) |
| `php artisan expediente:cifrar` | Encrypt record data still in plain text (dry run by default) |
| `php artisan reloj:token {id}` | Issue a watch token and signing secret (shown once) |
| `php artisan i18n:audit` | Fail if Spanish text is still visible in the interface, tests or data |
| `php artisan i18n:datos` | Translate stored system-generated text in place (dry run by default) |

## Data model (main tables)

`astronautas`, `misiones`, `mediciones`, `evaluaciones`, `requisitos`, `alertas_salientes`, `casos_clinicos`, `consultas_tierra`, `consultas_sintomas`, `linea_base`, `examenes_ascan`, `rutinas` and executions, `registros_salud`, `zonas_dolor`, `botiquin_items`, `simulacion_motor`, `simulacion_tripulantes`, `simulacion_eventos`, `auditoria_accesos`, `users`. Stored enumerations keep internal values (for example `normal|precaucion|critico`); the interface shows English labels.

## Testing

```bash
php artisan test                         # more than 900 tests, against healts_nasa_test
php artisan test --testdox               # readable test names
node --test resources/js/indicador/modelo.test.mjs
php artisan i18n:audit                   # language audit of the interface
```

Database tests use transactions and never touch your data.
