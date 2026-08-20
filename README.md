# Portale Dipendenti

Portale web per la distribuzione di buste paga e CU ai dipendenti. Vedi
`docs/superpowers/specs/2026-08-20-portale-dipendenti-buste-paga-design.md`
per il design completo.

## Requisiti

- XAMPP (Apache + PHP 8.2 + MySQL)
- Composer
- Node.js + npm

## Setup locale

```bash
composer install
npm install
npm run build:css
```

Importa lo schema del database:

```bash
mysql -u root < sql/schema.sql
```

Il progetto è servito tramite il VirtualHost dedicato `portale-dipendenti.local`
(vedi `C:\xampp\apache\conf\extra\httpd-vhosts.conf`), non `localhost`.

## Sviluppo — IMPORTANTE

**Dopo ogni modifica ai file `.php` (nuove classi Tailwind/DaisyUI usate nei
template) va rigenerato il CSS compilato:**

```bash
npm run build:css
```

oppure, durante lo sviluppo attivo, tenere il watcher in esecuzione:

```bash
npm run watch:css
```

`public/assets/css/output.css` è generato e **non versionato in git**
(`.gitignore`) — se manca o è disallineato ai template, la UI appare priva
di stile (HTML grezzo, nessuna card/bottone/layout). Se la UI perde
improvvisamente lo stile dopo un `git pull` o un checkout, la causa più
probabile è proprio questa: rilanciare `npm run build:css`.
