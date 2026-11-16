# Sources and references

This project is an **educational prototype with simulated data**. It is **not affiliated with or endorsed by NASA** and uses no NASA logos or emblems. Citing a source does not mean a value has been medically validated: **every threshold and clinical value is an example value pending review by a medical professional.**

## How sources are used

- **NASA-STD-3001 Vol. 1 Rev. C** (Space Flight Human-System Standard) defines the requirement layer: radiation limits, bone density and similar requirements cited by alerts.
- **NASA Human Research Program (HRP)** provides the observation and projection layer: rates, risks and context shown as "Projection (HRP)", never as a requirement.
- **Clinical references and guides** (blood pressure bands, temperature, sleep, hydration, mood scales) provide reference ranges and guidance, marked as reference or guide.
- Each alert shows the requirement, its revision and its type (Standard / HRP / Guide / Clinical reference). The requirement texts in the application are **paraphrased from the project's reference document**, not verbatim quotes of the standard.

## NASA and agency sources

- NASA-STD-3001, Volume 1, Revision C — https://www.nasa.gov/wp-content/uploads/2023/11/nasa-std-3001-vol-1-rev-c-with-signature.pdf
- NASA HRP, Five Hazards of Human Spaceflight — https://www.nasa.gov/hrp/hazards/
- NASA HRP, Risks of Human Spaceflight — https://www.nasa.gov/hrp/risks/
- NASA HRP, Ongoing Research — https://www.nasa.gov/hrp/ongoing-hrp-research/
- NASA Human Research Roadmap — https://humanresearchroadmap.nasa.gov/
- NASA HRP, Space Radiation — https://www.nasa.gov/hrp/radiation/
- NASA Human Performance reference (GCR effective dose) — https://www.nasa.gov/reference/4-0-human-performance/
- NASA OCHMO-STD-100.1A (astronaut candidate medical exam) — https://www.nasa.gov/wp-content/uploads/2023/04/ochmo-std-100.1a.pdf
- NASA NTRS 20200001709 (Apollo crew heart rate data) — https://ntrs.nasa.gov/api/citations/20200001709/downloads/20200001709.pdf
- NASA Human System Risk Board — https://www.nasa.gov/hhp/human-system-risks/
- NASA NEPP (space electronics and radiation effects) — https://nepp.nasa.gov/
- Canadian Space Agency, monitoring radiation and astronaut health — https://www.asc-csa.gc.ca/eng/news/articles/2026/2026-02-26-monitoring-radiation-astronaut-health.asp
- Canadian Space Agency, science aboard the ISS — https://www.asc-csa.gc.ca/eng/iss/science/why-do-we-conduct-science-experiments-in-space.asp

## Other references

- Body temperature in orbit (SciELO) — https://www.scielo.org.mx/scielo.php?script=sci_arttext&pid=S0026-17422014000400020
- Mood scales in spaceflight (PMC) — https://pmc.ncbi.nlm.nih.gov/articles/PMC6218582/
- Sleep in space (ANMDE Colombia) — https://anmdecolombia.org.co/dormir-en-el-espacio-exterior
- Vital signs reference (MedlinePlus) — https://medlineplus.gov/ency/article/002341.htm

The Word version of the standard-versus-HRP comparison is kept as [`NASA_STD_3001_limites_operativos_vs_HRP.docx`](NASA_STD_3001_limites_operativos_vs_HRP.docx) (Spanish).

## Project reference documents (`docs/fuentes/`)

Plain-text extracts of the reference documents the team used. The Spanish originals are kept here because the application and the knowledge sheets cite them; **English translations are in [`docs/fuentes/en/`](fuentes/en/README.md)**. They are development material, not application content.

| File | Content |
|---|---|
| `nasa_std_3001_limites_vs_hrp.txt` | NASA-STD-3001 Vol. 1 Rev. C versus HRP figures (standard versus observations) |
| `datos_medicos.txt` | Internal guide: architecture, biomarkers, thresholds, countermeasures, care levels |
| `salud_astronautas_y_monitoreo.txt` | Hazards, body systems, radiation and effects on electronics |
| `variacion_dosis_efectiva.txt` | GCR dose, blood pressure bands, ASCAN exam, heart rate, temperature, sleep, mood scales |

The project requirements and technical plan are also in `docs/` (Spanish PDFs). The NASA HRP and CSA web pages were consulted online and were not saved as files.

## Open-source software and assets

| Component | License |
|---|---|
| Laravel, Livewire, Laravel Reverb and Echo, Sanctum | MIT |
| Alpine.js, Tailwind CSS, Vite, Chart.js, PostCSS | MIT |
| MySQL | GPL / commercial (used as a local server) |
| IBM Plex Sans and Mono | SIL Open Font License 1.1 |
| Material Symbols Rounded | Apache License 2.0 |

## AI use disclosure

Generative AI tools (**Anthropic Claude: Claude Code and Claude Design**) helped write application code, automated tests, seed data, documentation and interface design mockups. The team directed the work, reviewed the results and chose the requirements and data sources. The "Flight Surgeon" assistant inside the application is **not** an AI model: it is a deterministic, rule-based simulation labeled as simulated in the interface.
