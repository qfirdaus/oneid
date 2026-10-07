# OneID 2.18.2 — Coloured Performance Gauge

**Release date:** 7 October 2026
**Scope:** User dashboard connection and display status indicator

The status-panel trigger uses an automotive-style gauge with green, amber and red arcs and a high-level needle. This directly represents the panel's connection and page-performance context.

The icon is a self-contained accessible SVG carrying the `fa-gauge-high` class, so its appearance does not depend on Font Awesome 6 being present in the legacy interface. It retains the countdown height and appears consistently in the header and panel heading on desktop and mobile. No session, SSO, mobile-production or database behavior changes.
