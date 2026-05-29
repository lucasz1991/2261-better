# Webreach CMS Skeleton

Dieses Projekt wurde auf ein schlankes Laravel-Grundgeruest fuer eine Webservice-Praesentationsplattform mit CMS-Funktion reduziert.

## Enthalten

- Laravel 10 + Jetstream (Livewire)
- CMS-Modelle: `WebPage`, `WebContent`, `PagebuilderProject`
- Datei-/Asset-Handling: `File`, `FilePool`, API-Controller fuer Upload/Asset-URL
- Oeffentliche Seiten: Start, Kontakt, FAQs, How-To, Impressum, Datenschutz, AGB, Sitemap

## Lokale Nutzung

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
```

## Build

```bash
npm run build
```
