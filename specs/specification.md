# KURSO for WordPress — Spezifikation

## Überblick

**KURSO** (https://www.kurso.de) ist ein cloudbasiertes Kursverwaltungssystem (SaaS).
Dieses WordPress-Plugin ermöglicht es, aktuelle Kursdaten aus KURSO direkt auf einer
WordPress-Website anzuzeigen — konfigurierbar, templatebasiert und benutzerfreundlich.

Die Anbindung erfolgt über die KURSO **GraphQL-API**:
```
https://<systemname>.kurso.de/api/graphql
```

Demo-Instanz: https://demo.kurso.de/api/graphql (User: `demo`, PW: `demodemo`)

---

## Konfiguration (Admin-Bereich)

Das Plugin legt einen Einstellungsbereich unter **Einstellungen → KURSO** an.

| Einstellung | Beschreibung | Pflichtfeld |
|---|---|---|
| GraphQL-URL | `https://<systemname>.kurso.de/api/graphql` | Ja |
| Benutzername | KURSO-Benutzername (HTTP Basic Auth) | Ja |
| Passwort | KURSO-Passwort | Ja |

Zusätzlich gibt es einen **"Verbindung testen"-Button**, der eine Test-Query absetzt
und Erfolg oder Fehlermeldung im Admin anzeigt.

Zugangsdaten werden verschlüsselt (via WordPress Options API) gespeichert.

---

## Query-Verwaltung

Das Plugin verwaltet eine Liste von **benannten GraphQL-Queries**.
Jedes Query hat folgende Eigenschaften:

| Eigenschaft | Beschreibung |
|---|---|
| Name (Slug) | Eindeutiger Bezeichner, z.B. `aktuelle-kurse` |
| Anzeigename | Lesbarer Name für die Admin-Oberfläche |
| GraphQL-Query | Vollständiger Query-Text (freies Textfeld) |
| Polling-Intervall | Wie oft die API abgefragt wird (in Minuten, Minimum: 1) |

### Dynamische Werte im Query (Twig-Preprocessing)

Bevor der Query an die KURSO-API gesendet wird, wird er durch Twig verarbeitet.
Damit lassen sich dynamische Werte direkt im Query-Text einbetten — insbesondere Datumswerte für Filter.

**Verfügbare Ausdrücke (Twig-Syntax):**

| Ausdruck | Ergebnis |
|---|---|
| `{{ date()\|date("Y-m-d") }}` | Heutiges Datum (ISO 8601) |
| `{{ date("-2 weeks")\|date("Y-m-d") }}` | Vor 2 Wochen |
| `{{ date("+1 month")\|date("Y-m-d") }}` | In einem Monat |
| `{{ date("first day of this month")\|date("Y-m-d") }}` | Erster des aktuellen Monats |

**Beispiel-Query mit dynamischem Datumsfilter:**
```graphql
query {
  allCourses(
    filter: { startDate_gte: "{{ date("-2 weeks")|date("Y-m-d") }}" }
    orderBy: startDate_ASC
  ) {
    name
    startDate
  }
}
```

Der Query-Text wird bei **jedem** Abruf neu ausgewertet — der Filter passt sich also automatisch an das aktuelle Datum an.

### Datenspeicherung

- Ergebnisse werden in der **WordPress Transient API** gespeichert.
- Cache-Key: `kurso_query_{slug}`
- TTL des Transients = Polling-Intervall
- Ein **WP-Cron-Job** pro Query aktualisiert den Cache im konfigurierten Intervall.
- Bei fehlgeschlagenem API-Aufruf bleibt der alte Cache erhalten (Graceful Degradation).
- Manueller **"Jetzt aktualisieren"-Button** im Admin füllt den Cache sofort.

### Beispiel-Query

```graphql
query AktuelleKurse {
  allCourses(
    filter: { workflowState: "planned", startDate_gte: "2026-02-19" }
    orderBy: startDate_ASC
  ) {
    id
    name
    startDate
    endDate
    location {
      name
      town
    }
    minPart
    maxPart
    onlineEnrollmentUrl
    workflowState
    # Aktuelle Buchungszahl (ohne Stornierungen) für Verfügbarkeitsanzeige:
    _courseBookingsMeta(filter: { workflowState_not_in: ["CANCELLED"] }) {
      count
    }
  }
}
```

Im Template steht dann `_courseBookingsMeta.count` für die belegten Plätze zur Verfügung.
`maxPart - _courseBookingsMeta.count` ergibt die freien Plätze — berechnet im Template,
nicht im Plugin.

---

## Templating (Twig)

Jedes Query kann mit einem **Twig-Template** verknüpft werden, das bestimmt,
wie die Daten im Frontend dargestellt werden.

**Warum Twig?** Mustache ist "logic-less" und kann keine Arithmetik oder Vergleiche
durchführen — z.B. `maxPart - _courseBookingsMeta.count` für freie Plätze ist in
Mustache nicht möglich. Twig unterstützt volle Arithmetik, Vergleiche und
Fallunterscheidungen direkt im Template, ohne dass das Plugin Berechnungen vorwegnehmen muss.
Twig ist zudem in WordPress gut etabliert (z.B. via Timber) und sicher gegen Code-Injection.

Das Template erhält das **rohe `data`-Objekt der GraphQL-Antwort** als Kontext.
Die Template-Variablen entsprechen damit exakt den Feldnamen aus dem GraphQL-Query —
ohne jede Transformation durch das Plugin.

### Syntax-Grundlagen

| Ausdruck | Bedeutung |
|---|---|
| `{{ feldname }}` | Einfacher Feldwert ausgeben |
| `{{ objekt.unterfeld }}` | Verschachtelter Feldwert |
| `{% for item in liste %}...{% endfor %}` | Über eine Liste iterieren |
| `{% if bedingung %}...{% endif %}` | Bedingte Ausgabe |
| `{% if a > b %}...{% else %}...{% endif %}` | Vergleich mit else-Zweig |
| `{{ a - b }}` | Arithmetik (auch `+`, `*`, `/`, `%`) |
| `{{ wert|date("d.m.Y") }}` | Datumsformatierung via Filter |
| `{{ wert|default("–") }}` | Fallback wenn Wert leer |

### Datenstruktur im Template

Die Struktur des Template-Kontexts ergibt sich direkt aus dem konfigurierten Query.
Beispiele für verschiedene Query-Formen:

**Query: Liste aller Kurse**
```graphql
{ allCourses { name startDate onlineEnrollmentUrl } }
```
→ Template-Kontext: `{ "allCourses": [ { "name": "...", ... }, ... ] }`
→ Im Template: `{% for course in allCourses %}{{ course.name }}{% endfor %}`

**Query: Einzelner Kurs**
```graphql
{ Course(id: "123") { name startDate } }
```
→ Template-Kontext: `{ "Course": { "name": "...", ... } }`
→ Im Template: `{{ Course.name }}`

**Query: Kursarten mit eingebetteten Terminen**
```graphql
{ allCourseTypes { name courses { startDate location { town } onlineEnrollmentUrl } } }
```
→ Im Template: `{% for ct in allCourseTypes %}{% for course in ct.courses %}...{% endfor %}{% endfor %}`

Das Plugin macht keinerlei Annahmen über die Query-Struktur.

### Verfügbarkeit und Buchen-Button

Mit Twig lässt sich die Verfügbarkeitslogik direkt im Template ausdrücken:

```twig
{% set frei = course.maxPart - course._courseBookingsMeta.count %}
{% if frei > 0 and course.onlineEnrollmentUrl %}
  <a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">
    Jetzt buchen ({{ frei }} Plätze frei)
  </a>
{% elseif frei <= 0 %}
  <span class="kurso-badge kurso-badge--voll">Ausgebucht</span>
{% endif %}
```

Die Anmeldung öffnet im gleichen Tab (kein `target="_blank"`), da KURSO das Design
der jeweiligen Homepage übernimmt.

### Sicherheit

Twig escaped alle Ausgaben standardmäßig (`{{ wert }}` = HTML-safe).
Rohe HTML-Ausgabe nur explizit via `{{ wert|raw }}` — sollte in Templates vermieden werden.
Twig erlaubt **keine PHP-Ausführung** (`<?php` etc. ist nicht möglich), was User-Templates
in einem Admin-Bereich sicher macht.

### Template im Block-Editor

Im Gutenberg-Block-Editor wird das Template direkt bearbeitet, mit einer
**Live-Vorschau** auf Basis der zuletzt gecachten API-Daten.
Ein **"Rohdaten anzeigen"-Toggle** zeigt den aktuellen Template-Kontext als JSON —
hilfreich beim Entwickeln neuer Templates.

---

## Beispiel-Templates

Das Plugin liefert drei **Beispiel-Templates** als Ausgangspunkt.
Sie sind auf konkrete Query-Strukturen zugeschnitten und müssen an die eigene
Query angepasst werden — sie illustrieren typische Anwendungsfälle.

### Beispiel 1: Kursliste (tabellarisch)

Passend zu einer Query wie `{ allCourses { name startDate endDate location { town } maxPart _courseBookingsMeta(filter:{workflowState_not_in:["CANCELLED"]}){count} onlineEnrollmentUrl } }`:

```twig
<table class="kurso-table">
  <thead>
    <tr>
      <th>Kurs</th>
      <th>Datum</th>
      <th>Ort</th>
      <th>Freie Plätze</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    {% for course in allCourses %}
    {% set frei = course.maxPart - course._courseBookingsMeta.count %}
    <tr>
      <td>{{ course.name }}</td>
      <td>{{ course.startDate|date("d.m.Y") }} – {{ course.endDate|date("d.m.Y") }}</td>
      <td>{{ course.location.town }}</td>
      <td>{{ frei > 0 ? frei : "Ausgebucht" }}</td>
      <td>
        {% if frei > 0 and course.onlineEnrollmentUrl %}
          <a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">Jetzt buchen</a>
        {% endif %}
      </td>
    </tr>
    {% endfor %}
  </tbody>
</table>
```

### Beispiel 2: Terminliste nach Kursart

Passend zu einer Query wie `{ allCourseTypes { name courses { startDate location { town } maxPart _courseBookingsMeta(filter:{workflowState_not_in:["CANCELLED"]}){count} onlineEnrollmentUrl } } }`:

```twig
{% for ct in allCourseTypes %}
<section class="kurso-coursetype">
  <h2>{{ ct.name }}</h2>
  <table class="kurso-table">
    <thead>
      <tr><th>Datum</th><th>Ort</th><th>Plätze</th><th></th></tr>
    </thead>
    <tbody>
      {% for course in ct.courses %}
      {% set frei = course.maxPart - course._courseBookingsMeta.count %}
      <tr>
        <td>{{ course.startDate|date("d.m.Y") }}</td>
        <td>{{ course.location.town }}</td>
        <td>{{ frei > 0 ? frei : "Ausgebucht" }}</td>
        <td>
          {% if frei > 0 and course.onlineEnrollmentUrl %}
            <a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">Jetzt buchen</a>
          {% endif %}
        </td>
      </tr>
      {% endfor %}
    </tbody>
  </table>
</section>
{% endfor %}
```

### Beispiel 3: Einzelkurs-Detail

Passend zu einer Query wie `{ Course(id: "123") { name startDate endDate location { name town } minPart maxPart _courseBookingsMeta(filter:{workflowState_not_in:["CANCELLED"]}){count} onlineEnrollmentUrl } }`:

```twig
{% set frei = Course.maxPart - Course._courseBookingsMeta.count %}
<article class="kurso-detail">
  <h2>{{ Course.name }}</h2>
  <dl>
    <dt>Beginn</dt><dd>{{ Course.startDate|date("d.m.Y") }}</dd>
    <dt>Ende</dt><dd>{{ Course.endDate|date("d.m.Y") }}</dd>
    <dt>Ort</dt><dd>{{ Course.location.name }}, {{ Course.location.town }}</dd>
    <dt>Kapazität</dt><dd>mind. {{ Course.minPart }}, max. {{ Course.maxPart }}</dd>
    <dt>Freie Plätze</dt><dd>{{ frei > 0 ? frei : "Ausgebucht" }}</dd>
  </dl>
  {% if frei > 0 and Course.onlineEnrollmentUrl %}
    <a href="{{ Course.onlineEnrollmentUrl }}" class="kurso-button kurso-button--large">
      Jetzt anmelden
    </a>
  {% else %}
    <p class="kurso-ausgebucht">Dieser Kurs ist leider ausgebucht.</p>
  {% endif %}
</article>
```

---

## WordPress-Integration

### Gutenberg-Block

- Block-Name: **KURSO Anzeige** (generisch, da Inhalt vom Query abhängt)
- Kategorie: eigene Kategorie "KURSO"
- Block-Einstellungen (Sidebar):
  - Query auswählen (Dropdown der konfigurierten Queries)
  - Template auswählen oder eigenes Template eingeben
  - Optionaler CSS-Klassenname
- **Live-Vorschau** im Editor auf Basis des Transient-Cache

### Shortcode

```
[kurso query="aktuelle-kurse"]
[kurso query="aktuelle-kurse" template="terminliste-nach-kursart"]
[kurso query="aktuelle-kurse" class="meine-css-klasse"]
```

| Parameter | Beschreibung | Standard |
|---|---|---|
| `query` | Slug des konfigurierten Queries | — (Pflichtfeld) |
| `template` | Slug eines gespeicherten Templates | am Query hinterlegtes Template |
| `class` | Zusätzliche CSS-Klasse für den Container | — |

---

## Datenfluss

```
[WP-Cron (Intervall)]
       ↓
[Plugin sendet GraphQL-Query + Basic Auth]
       ↓
[KURSO GraphQL-API: https://<systemname>.kurso.de/api/graphql]
       ↓
[JSON-Antwort]
       ↓
[WordPress Transient API (Cache-Key: kurso_query_{slug})]
       ↓
[Gutenberg-Block / Shortcode liest aus Cache]
       ↓
[Mustache-Template rendert HTML]
       ↓
[Frontend-Ausgabe]
```

Bei Erstinstallation oder manuellem "Jetzt aktualisieren"-Button wird der Cache
sofort befüllt (kein Warten auf den ersten Cron-Lauf).

---

## KURSO GraphQL-API — Referenz

### Relevante Query-Operationen

| Operation | Beschreibung |
|---|---|
| `allCourses(filter, first, skip, orderBy)` | Kursliste |
| `allCourseTypes(...)` | Kurstypen |
| `allExams(...)` | Prüfungen |
| `Course(id)` | Einzelner Kurs per ID |

### Wichtige Felder (Course)

| Feld | Typ | Beschreibung |
|---|---|---|
| `id` | ID | Eindeutige Kurs-ID |
| `name` | String | Kursname |
| `number` | String | Kursnummer |
| `startDate` | Date | Startdatum |
| `endDate` | Date | Enddatum |
| `location` | Location | Ort (name, street, zip, town) |
| `minPart` | Int | Mindest-Teilnehmer |
| `maxPart` | Int | Maximal-Teilnehmer |
| `onlineEnrollmentUrl` | String | Direkte Anmelde-URL |
| `workflowState` | String | Status (z.B. `planned`, `published`) |
| `courseType` | CourseType | Kurstyp (id, name) |
| `_courseBookingsMeta` | Meta | Buchungszähler (Objekt mit `count`) |

### Verfügbarkeitsberechnung

Ein häufiger Anwendungsfall: Anzeige freier Plätze und bedingte Darstellung des
"Jetzt buchen"-Buttons nur wenn noch Plätze frei sind.

**Pattern in der Query** — aktive Buchungen abrufen (ohne Stornierungen):
```graphql
_courseBookingsMeta(filter: { workflowState_not_in: ["CANCELLED"] }) {
  count
}
```

Das gleiche Prinzip gilt für Prüfungen:
```graphql
_examBookingsMeta(filter: { workflowState_not_in: ["CANCELLED"] }) {
  count
}
```

**Im Twig-Template** steht `_courseBookingsMeta.count` als belegte Plätze zur Verfügung.
Die Berechnung der freien Plätze erfolgt direkt im Template:

```twig
{% set frei = course.maxPart - course._courseBookingsMeta.count %}
{% if frei > 0 and course.onlineEnrollmentUrl %}
  <a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">
    Jetzt buchen ({{ frei }} Plätze frei)
  </a>
{% else %}
  <span class="kurso-badge--voll">Ausgebucht</span>
{% endif %}
```

### Filter-Beispiele

```graphql
# Nur veröffentlichte, zukünftige Kurse
filter: {
  workflowState: "published"
  startDate_gte: "2025-01-01"
}

# Nur bestimmter Kurstyp
filter: {
  courseTypeId: "abc123"
}
```

---

## Technische Anforderungen

| Anforderung | Wert |
|---|---|
| WordPress-Mindestversion | 6.0 |
| PHP-Mindestversion | 8.0 |
| Externe PHP-Bibliotheken | Keine (WP-HTTP-API für HTTP-Requests) |
| Template-Engine | Twig (via `twigphp/twig`, Composer-Paket) |
| JavaScript (Block) | React (WordPress Core), kein zusätzliches Build-System nötig |

---

## Offene Punkte / Spätere Erweiterungen

- [ ] Feldformatierung: Datum automatisch in deutsches Format (TT.MM.JJJJ) umwandeln
- [ ] Pagination: Seitenweise Anzeige langer Kurslisten
- [ ] Suchfilter im Frontend (z.B. nach Ort oder Kurstyp filtern)
- [ ] Mehrsprachigkeit (WPML-Kompatibilität)
- [ ] Webhook-Support: KURSO triggert WordPress direkt bei Datenänderung (statt Polling)
- [ ] Shortcode-Generator im Admin für nicht-technische Nutzer
