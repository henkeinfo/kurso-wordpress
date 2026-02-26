# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- GitHub Action to build a release ZIP and publish a GitHub Release on version tag push (closes #2)
- Query list now shows timestamp of last successful fetch; distinguishes between cache expired and never fetched (closes #3)

## [0.0.1] – 2026-02-19

### Added
- Base plugin structure for WordPress
- GraphQL client with HTTP Basic Auth (WP HTTP API)
- Admin area under **Settings → KURSO** with three tabs:
  - **Connection**: URL, username, password, connection test
  - **Queries**: List of all configured queries with status
  - **Edit Query**: GraphQL query and Twig template per query
- Automatic slug generation from query name in admin
- WP-Cron based polling: each query has its own fetch interval (in minutes)
- Caching of API results in WordPress Transient API
- Manual "Fetch now" button per query in admin
- Graceful degradation: existing cache is kept on API error
- Twig 3.x as template engine (via Composer)
- Shortcode `[kurso query="slug"]` with optional `class` parameter
- Gutenberg block **"KURSO Display"** with:
  - Query selection and template editor in the sidebar
  - Live preview via REST API
  - "Show raw data" toggle (JSON output of cached data)
- REST endpoints `kurso/v1/preview` and `kurso/v1/rawdata/{slug}`
- Frontend CSS with base styles for table, card grid and button
- Admin CSS and JS (auto-slug from name)
- Example templates in specification: course list, schedule by course type, single course detail

[Unreleased]: https://github.com/henkeinfo/kurso-wordpress/compare/v0.0.1...HEAD
[0.0.1]: https://github.com/henkeinfo/kurso-wordpress/releases/tag/v0.0.1
