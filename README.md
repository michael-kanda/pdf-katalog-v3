# PDF Blätterkatalog – WordPress Plugin v3.0

## Was ist neu in v3.0?

- **Mehrere Kataloge**: Jeder Katalog ist ein eigener Custom Post Type (`PDF Kataloge` im Admin-Menü)
- **Eigene Admin-Oberfläche**: Pro Katalog: PDF-URL, TOC-JSON, Akzentfarbe, Logo-Text, Untertitel
- **Shortcode mit Parameter**: `[pdf_katalog slug="preisliste"]` oder `[pdf_katalog id="42"]`
- **Multi-Instanz**: Mehrere Kataloge auf einer Seite möglich
- **Migration**: Bestehender v2.x-Katalog kann per Klick als CPT-Eintrag übernommen werden
- **Rückwärtskompatibel**: `[pdf_katalog]` ohne Parameter funktioniert weiterhin mit alten Einstellungen

## Installation

1. **Plugin hochladen**: Den Ordner `pdf-katalog-plugin` als ZIP unter *Plugins → Installieren → Plugin hochladen* installieren.
2. **Plugin aktivieren** im WordPress-Admin.
3. **Katalog anlegen**: Im Admin unter *PDF Kataloge → Neuer Katalog*.
4. **Shortcode einbinden**:

```
[pdf_katalog slug="hauptkatalog"]
[pdf_katalog slug="preisliste-2026"]
[pdf_katalog id="42"]
```

## Katalog anlegen

Unter *PDF Kataloge → Neuer Katalog*:

| Feld | Beschreibung |
|------|-------------|
| **Titel** | Interner Name (z.B. "Hauptkatalog 2026") |
| **PDF-Datei URL** | URL zur PDF aus der Mediathek |
| **Logo-Text** | Wird in der Sidebar angezeigt (leer = Titel) |
| **Untertitel** | z.B. "2026 / 2027" |
| **Akzentfarbe** | Individuelle Farbe pro Katalog |
| **Inhaltsverzeichnis** | JSON-Format (optional) |

Nach dem Speichern wird der Shortcode in der Sidebar-Box angezeigt.

## Inhaltsverzeichnis (JSON-Format)

```json
[
  {
    "chapter": "Kapitelname",
    "items": [
      { "title": "Eintrag", "page": 6 },
      { "title": "Weiterer Eintrag", "page": 14 }
    ]
  }
]
```

Kann auch leer bleiben – die PDF-Volltextsuche funktioniert trotzdem.

## Migration von v2.x

Nach dem Update erscheint ein Hinweis in der Katalog-Übersicht. Ein Klick auf „Jetzt migrieren" erstellt automatisch einen CPT-Eintrag mit den alten Einstellungen.

## Mehrere Kataloge auf einer Seite

Funktioniert automatisch – jede Instanz ist isoliert:

```
[pdf_katalog slug="hauptkatalog"]
[pdf_katalog slug="preisliste"]
```

## Technische Hinweise

- **PDF.js Version**: 3.11.174 (CDN)
- **Keine externen Abhängigkeiten** außer PDF.js
- **Selektoren**: Klassen statt IDs (Multi-Instanz-fähig)
- **CORS**: PDF-Dateien müssen auf derselben Domain liegen oder CORS-Header haben
