=== KURSO for WordPress ===
Contributors: kurso
Tags: kurse, graphql, twig, shortcode, gutenberg
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 8.0
Stable tag: 0.0.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Zeigt Kursdaten aus dem KURSO-Kursverwaltungssystem via GraphQL in WordPress an.

== Description ==

KURSO for WordPress verbindet Ihre WordPress-Website mit KURSO und zeigt Kursdaten auf Seiten oder Beiträgen an.

Das Plugin bietet:

* Verbindung zur KURSO-API
* Konfigurierbare Datenabfragen
* Ausgabe per Gutenberg-Block oder Shortcode
* Automatische Aktualisierung der Daten im Hintergrund

Hinweis:

* Das Plugin ist aktuell in einer frühen Version (`0.0.1`). Bitte testen Sie es vor dem Produktiveinsatz gründlich.

== Installation ==

1. Laden Sie das Plugin-ZIP in WordPress hoch: `Plugins -> Installieren -> Plugin hochladen`.
2. Aktivieren Sie das Plugin.
3. Tragen Sie unter `Einstellungen -> KURSO` Ihre Zugangsdaten ein.
4. Testen Sie die Verbindung.
5. Legen Sie eine Query an und speichern Sie diese.
6. Fügen Sie die Ausgabe im Editor per Block oder Shortcode ein.

== Frequently Asked Questions ==

= Wie zeigen Sie Kurse auf einer Seite an? =

Mit dem KURSO-Block im Editor oder per Shortcode, z. B.:

`[kurso query="meine-query"]`

Optional mit CSS-Klasse:

`[kurso query="meine-query" class="meine-klasse"]`

= Wo richten Sie die Verbindung zu KURSO ein? =

Unter `Einstellungen -> KURSO`.

= Wer kann das Plugin nutzen? =

Alle WordPress-Websites mit Zugang zu einem KURSO-System und passender API-URL.

== Changelog ==

= 0.0.1 =

* Initiale Plugin-Basis mit KURSO-GraphQL-Anbindung
* Admin-Bereich für Verbindung, Queries und Templates
* WP-Cron-Polling, Caching und manueller "Jetzt aktualisieren"-Ablauf
* Twig-Rendering, Shortcode und Gutenberg-Block mit Live-Vorschau

== Upgrade Notice ==

= 0.0.1 =

Erste veröffentlichte Version. Bitte testen Sie diese vor dem Produktiveinsatz umfassend.
