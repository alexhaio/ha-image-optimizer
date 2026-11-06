# HA Image Optimizer ⚡

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-787cb5.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.0-21759b.svg)](https://wordpress.org)

Leistungsstarke, rein lokale Bildoptimierung für WordPress – entwickelt für maximale Performance, Sicherheit und vollständige Unabhängigkeit von externen Cloud-APIs oder Monatsabos.

---

## 🚀 Features

* **100% Lokal & DSGVO-konform**: Keine Bildübertragung an externe Server. Keine API-Keys, keine monatlichen Limits.
* **Moderne Bildformate**: Erzeugt automatisch **WebP** und **AVIF** (sofern vom Server unterstützt) für Originalbilder und alle Zwischengrößen.
* **Duale Kompressionsstrategie**:
  * **Originale:** Standardmäßig **verlustfrei (`LOSSLESS`)** – behält 100% Pixelschärfe bei und bereinigt Metadaten/EXIF.
  * **Zwischengrößen:** Standardmäßig **Smart (`SMART`)** – verlustbehaftet komprimiert (~82%) für optimale Ladezeiten.
* **Flexible Auslieferung**:
  * **`.htaccess`-Rewrite**: 0 ms PHP-Overhead bei transparentem Webserver-Rewrite (Apache & LiteSpeed).
  * **`<picture>`-Tag-Ersetzung**: Saubere und universelle Auslieferung für jedes Setup und CDN.
  * **CSS `image-set()`**: Unterstützung von Inline-Hintergrundbildern.
* **Sicherheit & Schutz**:
  * **SizeGuard**: Verhindert negative Ersparnis (verworfene Dateien, falls optimierte Version größer wäre).
  * **Backup-System**: Sichert unveränderte Originale in einem geschützten Verzeichnis.
  * **1-Click Restore**: Stellt das Original jederzeit wieder her.
* **Massenverarbeitung**:
  * Live-AJAX-Optimierer im WordPress-Dashboard.
  * Voller **WP-CLI-Support** (`wp ha-optimizer`) für Server-Administratoren.
* **Internationalisiert**: Übersetzt in die 25 meistgenutzten WordPress-Sprachen inklusive RTL-Support.
* **Automatisierte Updates**: Integrierter GitHub-Updater via `plugin-update-checker` (PUC v5).

---

## 📋 Anforderungen

* WordPress 6.0 oder höher
* PHP 8.1 oder höher
* PHP-Erweiterung `imagick` (empfohlen) oder `gd`

---

## 🛠️ Installation & Updates

1. Lade das Repository als ZIP herunter oder klone es in dein WordPress-Plugin-Verzeichnis:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/alexhaio/ha-image-optimizer.git
   ```
2. Aktiviere das Plugin im WordPress-Admin unter **Plugins**.
3. Navigiere zu **Einstellungen → HA Image Optimizer**, um Optionen anzupassen oder die Massenoptimierung zu starten.

### Automatische Updates aus privatem GitHub-Repository
Für automatische Updates über private GitHub-Repositories trage dein Token in die `wp-config.php` ein:
```php
define( 'HA_GITHUB_UPDATE_TOKEN', 'dein_github_token' );
```

---

## 💻 WP-CLI Befehle

```bash
# Status der Mediathek und Serverfähigkeiten anzeigen
wp ha-optimizer status

# Alle Bilder der Mediathek im Hintergrund optimieren
wp ha-optimizer optimize --all

# Nur unoptimierte Bilder verarbeiten (Batch-Größe anpassen)
wp ha-optimizer optimize --batch-size=20

# Originalbilder wiederherstellen
wp ha-optimizer restore --all
```

---

## 📄 Lizenz

Dieses Projekt ist lizenziert unter der **GNU General Public License v3.0** (GPL-3.0) – siehe die [LICENSE.txt](LICENSE.txt) Datei für Details.
