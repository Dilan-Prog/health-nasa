---
slug: fallo_sensor
patron: fallo_sensor
titulo: Possible sensor failure
fuentes:
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 3 (efectos del ambiente espacial sobre los dispositivos electrónicos: 3.1, 3.3, 3.4 y 3.6)"
revision_medica: pendiente
---

## Signs to watch
- Readings flagged as suspect by the project's signal quality checks (abrupt jump, flat signal or value outside the possible range).
- The source indicates that monitoring can involve corrupted data, resets, lost measurements or false alarms caused by radiation, electromagnetic interference, temperature or batteries.

## What to check
- The device, not the health: the fit of the equipment and reliable contact between sensor and skin (section 3.3 of the source).
- Loose or disconnected cables and connectors and the battery level (sections 3.3 and 3.6).
- Possible interference from other equipment (section 3.4).
- Repeat the measurement once the device has been checked.

## When to escalate
- A suspect reading does not alert the ground by itself. If it also crosses a critical threshold, the original critical alert is kept (project rule, not a standard).
- Criteria for replacing or repairing the equipment: Pending verifiable source.

## Limits of what can be stated
- Only a possible device failure (hypothesis); it cannot be ruled out that the anomalous reading is real.
- The jump and flat-signal thresholds are project assumptions, not a standard.
