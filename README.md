# KURSO for WordPress

WordPress plugin for integrating the [KURSO course management system](https://www.kurso.de) via GraphQL API.

## Features

- **GraphQL integration** – Connects WordPress to any KURSO instance via `https://<systemname>.kurso.de/api/graphql`
- **Flexible query configuration** – Define any GraphQL queries; each instance has its own data structure
- **Twig templating** – Full arithmetic, comparisons and loops in templates (e.g. calculate free spots)
- **Automatic caching** – WP-Cron fetches data at the configured interval and stores results in the WordPress Transient API
- **Gutenberg block** – With live preview and "Show raw data" toggle
- **Shortcode** – Classic embed via `[kurso query="..."]`

## Requirements

- WordPress ≥ 6.0
- PHP ≥ 8.0
- Composer (for development)

## Installation

### From ZIP

1. Download the ZIP file from [Releases](../../releases)
2. In WordPress: **Plugins → Add New Plugin → Upload Plugin**
3. Activate the plugin

### From Source

```bash
git clone https://github.com/<org>/kurso-for-wordpress.git
cd kurso-for-wordpress
composer install --no-dev --optimize-autoloader
```

Then copy the folder to `wp-content/plugins/kurso-for-wordpress/` and activate it in WordPress.

## Configuration

After activation, go to **Settings → KURSO**:

| Setting | Description |
|---|---|
| GraphQL URL | `https://<systemname>.kurso.de/api/graphql` |
| Username | KURSO username |
| Password | KURSO password |

Use **"Test Connection"** to verify the API connection.

## Queries

Under the **Queries** tab you can add multiple GraphQL queries:

- **Slug** – Unique identifier used in the shortcode
- **GraphQL Query** – Full query text
- **Polling interval** – Fetch frequency in minutes
- **Twig Template** – HTML template with Twig syntax

### Example Query

```graphql
query {
  allCourses(orderBy: startDate_ASC, first: 20) {
    name
    startDate
    endDate
    location { name town }
    maxPart
    _bookingsMeta { count }
    onlineEnrollmentUrl
  }
}
```

### Example Template (Table)

```twig
<table class="kurso-table">
  <thead>
    <tr><th>Course</th><th>Date</th><th>Location</th><th>Spots</th><th></th></tr>
  </thead>
  <tbody>
    {% for course in allCourses %}
    {% set free = course.maxPart - course._bookingsMeta.count %}
    <tr>
      <td>{{ course.name }}</td>
      <td>{{ course.startDate|date("d.m.Y") }}</td>
      <td>{{ course.location.town }}</td>
      <td>{{ free > 0 ? free : "Fully booked" }}</td>
      <td>
        {% if free > 0 and course.onlineEnrollmentUrl %}
          <a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">Book now</a>
        {% endif %}
      </td>
    </tr>
    {% endfor %}
  </tbody>
</table>
```

> **Important:** Template variables exactly match the field names from the GraphQL query.
> The plugin makes no assumptions about data structure — query and template are configured together.

## Usage

### Shortcode

```
[kurso query="my-query"]
[kurso query="my-query" class="my-css-class"]
```

### Gutenberg Block

Drag the **"KURSO Display"** block from the *Embeds* category into the editor. Select query and template in the sidebar.

## Twig Syntax Quick Reference

| Expression | Meaning |
|---|---|
| `{{ variable }}` | Output value |
| `{{ object.field }}` | Nested value |
| `{% for item in list %}...{% endfor %}` | Loop |
| `{% if condition %}...{% endif %}` | Condition |
| `{{ a - b }}` | Arithmetic |
| `{{ value\|date("d.m.Y") }}` | Date format |
| `{{ value\|default("–") }}` | Fallback |

## Project Structure

```
kurso-for-wordpress/
├── kurso-for-wordpress.php   # Plugin main file
├── admin/
│   └── class-kurso-admin.php # Admin UI
├── includes/
│   ├── class-kurso-settings.php  # Settings management
│   ├── class-kurso-graphql.php   # GraphQL client
│   ├── class-kurso-cron.php      # WP-Cron / polling
│   ├── class-kurso-renderer.php  # Twig rendering
│   ├── class-kurso-shortcode.php # Shortcode [kurso]
│   └── class-kurso-block.php     # Gutenberg block
├── assets/
│   ├── css/
│   │   ├── kurso-admin.css
│   │   └── kurso-frontend.css
│   └── js/
│       ├── kurso-admin.js
│       └── block.js
├── composer.json
└── vendor/                   # Twig (not in Git)
```

## License

MIT – see [LICENSE](LICENSE)
