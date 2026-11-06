=== HA Image Optimizer ===
Contributors: mystryx1337
Donate link: https://seo-hacker.de/
Tags: image optimizer, webp, avif, compression, performance, media library
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.3.5
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Leistungsstarke, rein lokale Bildoptimierung für WordPress ohne externe APIs. Generiert WebP und AVIF, verwaltet Backups und bietet Vorher-Nachher-Statistiken.

== Description ==

HA Image Optimizer ist ein vollständig selbst gehostetes, werbefreies und ressourcenschonendes Bildoptimierungs-Plugin für WordPress. Es funktioniert wie Imagify, nutzt jedoch ausschließlich deine lokalen Server-Ressourcen (ImageMagick oder GD) – ohne Drittanbieter-APIs, ohne Monatsabos und zu 100% DSGVO-konform.

= Kernfunktionen =
* **Duale Kompressionsstrategie**: Originale standardmäßig verlustfrei (Lossless), Zwischengrößen smart verlustbehaftet (~82%).
* **Next-Gen Formate**: Automatische Erzeugung von WebP und AVIF für Master-Bilder und alle Thumbnails.
* **Medien-Auditor (Verwaiste Medien)**: Intelligente Tiefenanalyse spürt ungenutzte und verwaiste Bilder auf (prüft Beitragsbilder, WooCommerce-Produktgalerien, Gutenberg-Blöcke, Fließtext, Page-Builder, Header-Logos & Favicons) und gibt Ballast-Speicherplatz frei.
* **Flexible Auslieferung**: Wahlweise universelle <picture>-Tag Ersetzung (inkl. CSS image-set()) oder 0 ms PHP-Overhead via .htaccess Server-Rewrites (Apache & LiteSpeed).
* **Backup & 1-Click Restore**: Sichert Originale in einem geschützten Verzeichnis und erlaubt jederzeitige Wiederherstellung.
* **SizeGuard**: Schutz vor negativer Ersparnis (neue Dateien werden verworfen, falls sie größer als das Original wären).
* **Massenverarbeitung**: Live-AJAX-Optimierer im Dashboard und vollständige WP-CLI Unterstützung (`wp ha-optimizer`).
* **Vorher-Nachher Transparenz**: Exakte Speicherplatzersparnis in KB/MB und Prozent pro Bild und Site-weit.
* **Mehrsprachig**: Vollständig übersetzt in die 25 meistgenutzten WordPress-Sprachen inklusive RTL-Support.

== Installation ==

1. Lade den Ordner `ha-image-optimizer` in das Verzeichnis `/wp-content/plugins/` hoch.
2. Aktiviere das Plugin im WordPress-Menü unter 'Plugins'.
3. Gehe zu 'Einstellungen -> HA Image Optimizer', um Optionen anzupassen.
4. Starte die Massenoptimierung unter 'Medien -> Massenoptimierung' oder spüre ungenutzte Bilder unter 'Medien -> Verwaiste Medien' auf.

== Frequently Asked Questions ==

= Benötigt das Plugin einen API-Schlüssel? =
Nein. Das Plugin arbeitet zu 100% lokal auf deinem Webserver über PHP Imagick oder GD.

= Was passiert bei einer Deinstallation? =
Gemäß den HA-Standards werden deine Backups und WebP-Dateien standardmäßig geschützt und nicht gelöscht. In den Einstellungen gibt es einen expliziten Schalter für die vollständige Bereinigung bei Deinstallation.

== Changelog ==

= 1.3.5 =
* Neu: Erkennung von Galerie-Shortcodes & ID-Listen: Der Medien-Auditor erkennt nun zuverlässig Bilder, die als kommaseparierte IDs in Galerie-Shortcodes ([gallery ids="..."], include, images, gallery_ids) eingebunden sind.
* Neu: Erkennung von Gutenberg JSON-Array-Referenzen: Unterstützung für wp:gallery {"ids":[...]} und Block-Array-Attribute.
* Neu: Erkennung von ID-Listen in Page-Buildern und Custom Postmeta / ACF-Feldern.
* Neu: Automatische Galerie-Erkennung bei parameterlosem [gallery] in übergeordneten Beiträgen.
* Optimierung: Transparente Referenzkennzeichnung "Eingebunden im Inhalt (Galerie)" im Auditor.

= 1.3.4 =
* Neu: Umfassende Erkennung von WordPress 5.3+ Skalierungen (-scaled) und Zwischengrößen (Sub-Sizes): Der Medien-Auditor erkennt nun zuverlässig Bilder, die als verkleinerte Thumbnail-Größe (z. B. 1024x768, 300x200), unskaliertes Original oder -scaled-Variante in Beiträgen, Page-Buildern, Slitern und Tabellen eingebunden sind.
* Performance: Intelligenter Early-Exit und Batch-Pre-Fetching im Medien-Auditor für drastisch reduzierte Datenbanklast bei großen Mediatheken.
* Neu: Inkrementeller Medien-Audit mit Option zum Überspringen bereits gescannter Medien.
* Optimierung: Einbeziehung unskalierter Master-Bilder (original_image) und zugehöriger WebP/AVIF-Derivate in die Speicherplatzberechnung.

= 1.3.3 =
* Neu: Bildgrößen- & Auflösungsanzeige in der Mediathek: Direkte Anzeige von Dateigrößen (Vorher → Nachher bzw. unoptimierte Gesamtgröße), Pixel-Auflösung (Breite × Höhe) und Zwischengrößenanzahl in der Mediathek-Tabelle und der Detailansicht.
* Neu: Präzise Größenformatierung mit Dezimalstellen: Dateigrößen (KB, MB, GB) werden im gesamten Plugin nun mit mindestens einer Nachkommastelle ausgewiesen, um irreführende Rundungen wie "2MB -> 2MB (Ersparnis: 50 KB)" zu verhindern.

= 1.3.2 =
* Neu: Individuelle Bildkomprimierung mit Stufenauswahl (Hoch / Mittel / Niedrig / Standard) direkt in der Mediathek-Listenansicht und im Attachment-Details-Modal.
* Neu: Verlustfreie Neu-Berechnung bei Stufenänderung – Master-Original wird automatisch aus dem Backup wiederhergestellt und alle Thumbnails werden ohne Generationsverlust frisch berechnet.
* Neu: Optische Kennzeichnung der individuellen Komprimierungsstufe via Badge.
* Neu: WP-CLI Unterstützung für individuelle Stufen (`wp ha-optimizer optimize --id=123 --level=hoch`).
* Optimierung: Try-Catch-Absicherung und verbesserte Fehlerbehandlung in der Bildoptimierungs-Pipeline.

= 1.3.1 =
* Fix: Behebung eines Fehlers im generischen Postmeta-Scan, bei dem Attachments fälschlicherweise ihre eigenen WordPress-Metadaten (_wp_attached_file / _wp_attachment_metadata) als Verwendung gewertet haben.
* Fix: Ausschluss von Bild-Optimizer- und Backup-Tabellen (Smush, EWWW, Imagify, Updraft, Duplicator) aus dem universellen Datenbank-Scan.
* Optimierung: Schutzfrist (Sicherheits-Puffer) standardmäßig auf 0 Tage (deaktiviert) gesetzt, damit ungenutzte Medien out-of-the-box unverfälscht aufgespürt werden.

= 1.3.0 =
* Neu: Universeller Datenbank-Tiefenscan: Erkennt Bildverwendungen in allen Drittanbieter-Tabellen (Slider, Formulare, Page-Builder, Custom Plugins) vollautomatisch anhand universeller Signaturen (IDs, JSON-Strukturen, relative Upload-Pfade, Dateinamen).
* Neu: Generischer Postmeta-Tiefenscan: Spürt Bilder auch in völlig unbekannten benutzerdefinierten Meta-Feldern von Themes und Plugins auf und verlinkt den zugehörigen Beitrag.
* Neu: Intelligenter Schutzfrist-Sicherheits-Puffer: Neu hochgeladene Medien (Standard: 14 Tage, konfigurierbar) werden automatisch vor versehentlichem Löschen geschützt, selbst wenn sie noch unveröffentlicht sind.
* Neu: Entwickler-Extensibility API: Filter-Hooks `ha_io_audit_custom_tables`, `ha_io_audit_ignored_tables`, `ha_io_audit_safety_period_days` und `ha_io_audit_attachment_references`.
* Neu: Erweiterte Einstellungen im Dashboard für den universellen Datenbank-Scan, die Schutzfrist und benutzerdefinierte Tabellen.

= 1.2.2 =
* Neu: Smart Slider 3 Schutz im Medien-Auditor: Vollständige Erkennung von Slider-Hintergründen, Bild-Ebenen (Layers), mobilen Varianten, Slider-Einstellungen und Thumbnails aus benutzerdefinierten Tabellen (nextend2_smartslider3_slides/sliders).
* Neu: Detaillierte Nachweise für Smart Slider 3 inklusive Direkt-Links zum Bearbeiten des betroffenen Sliders bzw. Slides im WordPress-Admin.
* Neu: Erweiterte Slider-Plugin-Unterstützung für Slider Revolution (revslider) und LayerSlider.
* Neu: Tiefenprüfung von WordPress-Widgets und Optionen in der Tabelle wp_options (z. B. Bild-Widgets, Sidebar-Banner und Plugin-Optionen).

= 1.2.1 =
* Neu: Direkte Verlinkung gefundener Posts, Seiten, WooCommerce-Produkte, Theme-Customizer-Sektionen und Theme-Dateien in der Spalte "Fundorte & Verwendungs-Nachweise".
* Neu: Erweiterte Theme- und Customizer-Erkennung (Header-Bild, Hintergrundbild, Theme-Logo, Browser-Favicon, FSE Block-Theme Templates & Scan aktiver Theme-Dateien wie style.css und theme.json).
* Neu: Vollständige Page-Builder-Sicherheit: Robuste Erkennung für Elementor (_elementor_data, generiertes CSS, globale Kits), Divi, WPBakery, Beaver Builder, Bricks, Oxygen und ACF (Advanced Custom Fields).
* Fix: Behebung der Speicherplatz-Inkonsistenz bei "Alle erneut optimieren (Erzwingen)" durch automatische Wiederherstellung aus dem Original-Backup bzw. Wahrung der unkomprimierten Ausgangsbasis.
* UI: Vollständig einheitliche Button-Größen und Abstände im Massenoptimierer.
* i18n: Vollständige Übersetzung des "Alle erneut optimieren (Erzwingen)"-Buttons in alle 26 Sprachpakete.

= 1.2.0 =
* Neu: Intelligenter Medien-Auditor ("Verwaiste Medien") unter Medien -> Verwaiste Medien.
* Neu: 3-Stufen-Statusklassifizierung: Aktiv verwendet, Zugeordnet aber inaktiv, Vollständig verwaist.
* Neu: Automatische Tiefenprüfung auf Beitragsbilder, WooCommerce-Produktgalerien, Gutenberg-Blockattribute, HTML <img>-Tags, Upload-URLs, Page-Builder (Elementor), Site-Icon, Header-Logo und Taxonomien.
* Neu: Exakte Berechnung des freigebbaren Festplattenspeichers (Master-Datei + alle Zwischengrößen + WebP/AVIF-Dateien).
* Neu: Sicheres Verschieben verwaister Dateien in den WordPress-Papierkorb (mit 1-Klick-Wiederherstellung) und Sammel-Aktionen.
* Neu: WP-CLI Befehle `wp ha-optimizer audit` und `wp ha-optimizer clean-orphans`.
* Neu: Nahtlose Header-Navigation (Tabs) zwischen Einstellungen, Massenoptimierung und Medien-Auditor.

= 1.1.1 =
* Fix: Behebung von 40-Sekunden Gateway-Timeouts bei Massenoptimierung großer Mediatheken durch Umstellung der Batch-Größe auf 1 Attachment pro AJAX-Request.
* Performance: Optimierung der WebP-Kompressionsmethode (`webp:method` 4) und Konfiguration optimaler AVIF-Encoder-Geschwindigkeiten (`speed` 6), wodurch Verarbeitungszeiten um ca. 70% sinken.
* Stabilität: Automatischer Reset des PHP Execution Time Limits vor jedem Thumbnail-Sub-Size und Erhöhung des Memory Limits bei AJAX-Batches.
* Performance: Optimierung der Datenbankabfrage in `ProgressTracker::get_stats()` via JOIN und `COUNT()` anstelle speicherintensiver `IN (...)`-Klauseln.
* Diagnose: Detaillierte Erkennung und Anzeige von HTTP 504 / 502 / 500 Fehlern im Live-Log des Massenoptimierers.

= 1.1.0 =
* Neu: Theme- und benutzerdefinierte Verzeichnisse scannen und optimieren (inkl. WebP & AVIF Erzeugung).
* Neu: Frontend ContentFilter unterstützt nun Theme-Assets und benutzerdefinierte Verzeichnisse für <picture>-Tags und CSS image-set().
* Neu: WP-CLI Befehle `wp ha-optimizer optimize-custom` und `wp ha-optimizer restore-custom`.
* Neu: Automatisierte GitHub Actions CI Pipeline mit PHP 8.1 - 8.4 Matrix und Standalone-Test-Suite.
* Neu: Automatisierte GitHub Actions Packaging & Release-Action zum Bereitstellen des Installations-ZIPs direkt im Repository.

= 1.0.1 =
* Neu: Schnellzugriffs-Links direkt in der Plugin-Verwaltung (wp-admin/plugins.php).
* Neu: Visueller 4-Phasen-Stepper für die Massenoptimierung (Crawlen -> Komprimieren -> WebP/AVIF -> Fertig).
* Neu: Live-Statusanzeige mit pulsierendem Indikator und Dateiname der aktuellen Datei.
* Neu: Interaktives, aufklappbares Verlaufs-Dropdown für bereits verarbeitete Bilder mit Vorher/Nachher-Größen.
* Neu: Quick-Start Banner auf der Einstellungsseite.
* Aktualisiert: Autor-Referenzen auf https://seo-hacker.de/.

= 1.0.0 =
* Erstveröffentlichung mit lokaler Imagick/GD Engine, WebP/AVIF-Generierung, .htaccess & Picture-Tag Delivery, WP-CLI und 25 Sprachen.
