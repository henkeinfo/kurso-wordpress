# KURSO for WordPress — CLAUDE.md

## Project

WordPress plugin that displays course data from the KURSO course management system (https://www.kurso.de) on WordPress pages via GraphQL.

- **GitHub:** https://github.com/henkeinfo/kurso-wordpress
- **Main file:** `kurso-wordpress.php`
- **Text domain:** `kurso-wordpress`
- **Specification:** `specs/specification.md`

## Workflow Rules

- When an issue or feature is completed: **update `specs/specification.md` and `CHANGELOG.md`**
  - Check off or remove open items in the spec (`## Offene Punkte`)
  - Add new entries to the changelog under `## [Unreleased]`
- No commits without a matching changelog entry

## Tech Stack

| Component | Detail |
|---|---|
| PHP | ≥ 8.0 |
| WordPress | ≥ 6.0 |
| Template engine | Twig 3.x via Composer (`vendor/autoload.php`) |
| HTTP | WordPress HTTP API (`wp_remote_post`) |
| Caching | WordPress Transient API (`kurso_query_{slug}`) |
| Cron | WP-Cron, one job per query |
| Block | Gutenberg (React, WordPress Core, no build system) |

## File Structure

```
kurso-wordpress/
├── kurso-wordpress.php       # Plugin entry point
├── admin/
│   └── class-kurso-admin.php # Admin UI (settings, query management)
├── includes/
│   ├── class-kurso-settings.php  # Credentials, Options API
│   ├── class-kurso-graphql.php   # GraphQL client (HTTP Basic Auth)
│   ├── class-kurso-cron.php      # WP-Cron / polling
│   ├── class-kurso-renderer.php  # Twig rendering
│   ├── class-kurso-shortcode.php # [kurso query="..."]
│   └── class-kurso-block.php     # Gutenberg block
├── assets/css/ assets/js/
├── specs/specification.md    # Functional specification
├── CHANGELOG.md
└── composer.json             # Twig dependency
```

## Architectural Decisions

- **No build system:** JavaScript for the Gutenberg block uses WordPress Core React directly (`wp-element`, `wp-blocks` etc.) — no webpack, no npm build.
- **Twig instead of Mustache:** Twig supports arithmetic in templates (e.g. `maxPart - _courseBookingsMeta.count` for available spots). The plugin makes no assumptions about query structure — template and query are configured together.
- **Graceful degradation:** On API error the existing transient cache is kept.
- **No target="_blank":** KURSO adopts the design of the respective homepage.

## Demo API for Testing

```
URL:      https://demo.kurso.de/api/graphql
User:     demo
Password: demodemo
```
