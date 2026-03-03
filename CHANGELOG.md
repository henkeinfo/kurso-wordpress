# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Security
- Sanitize all `$_GET` parameters in admin page (`sanitize_key`, `sanitize_text_field`)
- Add `wp_unslash()` to all `$_GET` and `$_POST` parameters before sanitization
- Wrap all `admin_url()` output in `esc_url()` in HTML attributes
- Replace `wp_redirect()` with `wp_safe_redirect()` in all admin handlers
- Replace base64 password encoding with AES-256-CBC encryption using WordPress AUTH_KEY; automatic migration of existing passwords
- Replace hardcoded fallback encryption key with auto-generated random key when `AUTH_KEY` is unavailable
- Apply `wp_kses_post()` to Twig-rendered HTML output on frontend and REST preview
- Add Twig Sandbox (SecurityPolicy) to both template renderer and variable preprocessor — restricts allowed tags, filters, and functions
- Truncate HTTP response body in error messages to prevent data leakage
- Add REST API args schema with `sanitize_callback` and `validate_callback` to all endpoints including `/evaluate-variables`
- Restrict `/preview` REST endpoint permission from `edit_posts` to `manage_options`
- Register plugin settings via `register_setting()` with `sanitize_callback`
- Use `esc_html__()` for translatable strings in HTML context
- Wrap all `echo` output in `esc_attr()` for HTML attribute context (tab classes, readonly)
- Wrap `error_log()` in `WP_DEBUG` check to prevent information leakage in production
- Add `index.php` sentinel files to all plugin directories including root and `assets/js/vendor/`

### Added
- GitHub Action to build a release ZIP and publish a GitHub Release on version tag push (closes #2)
- Query list now shows timestamp of last successful fetch; distinguishes between cache expired and never fetched (closes #3)
- GraphQL queries support native variables via a dedicated **Variables (JSON)** field; Twig expressions in the variables field allow dynamic values like `{{ date('-2 weeks')|date('Y-m-d') }}` (closes #4)
- Syntax highlighting in the query editor: GraphQL mode for the query field, JSON mode for the variables field, HTML+Twig mode for the template field (using WordPress-bundled CodeMirror)
- **▶ Evaluate** button in the Variables field previews evaluated Twig expressions server-side via REST API
- **Open in GraphiQL ↗** button opens the configured GraphQL endpoint in a new tab

### Fixed
- CodeMirror: use `window.wp.CodeMirror` (the real WordPress CodeMirror instance) instead of `window.CodeMirror` (empty placeholder set by the bundle)

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
