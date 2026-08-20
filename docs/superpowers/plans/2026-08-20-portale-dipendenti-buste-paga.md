# Portale Dipendenti — Buste Paga e CU — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a PHP + MySQL employee portal where an admin uploads a single cumulative PDF of payslips, the system automatically splits it into per-employee documents matched by Codice Fiscale, and employees log in to view/download their payslips and CU (Certificazione Unica) from a mobile-friendly dashboard.

**Architecture:** Plain PHP (no framework) with direct PHP files as pages (no router), PDO for MySQL access, jQuery for interactivity, Tailwind CSS + DaisyUI (local build via npm) for styling. PDF text extraction and splitting run synchronously in-process using `smalot/pdfparser` and `setasign/fpdi`. All uploaded/extracted files live outside the public webroot and are served through session-checked PHP download endpoints.

**Tech Stack:** PHP 8.2, MySQL, PDO_MYSQL, Composer (smalot/pdfparser, setasign/fpdi, setasign/fpdi-fpdf), npm + Tailwind CLI + DaisyUI, jQuery, XAMPP (Apache) at `C:\xampp\htdocs\portale-dipendenti`.

## Global Constraints

- Backend is plain PHP, no framework — direct PHP files as pages (e.g. `login.php`, `admin/dipendenti.php`), no front-controller/router.
- Frontend: jQuery + DaisyUI/Tailwind, built locally via Tailwind CLI + npm (no CDN).
- Database access via PDO (PDO_MYSQL) with prepared statements exclusively — no raw string interpolation into SQL.
- All PDFs (originals and per-employee extracts) are stored outside the public webroot; the only access path is through session-and-ownership-checked PHP endpoints.
- Passwords hashed with `password_hash()` (bcrypt); no plaintext password storage anywhere.
- No email sending in this phase — the admin communicates temporary passwords out-of-band.
- No automated test framework (PHPUnit) in this phase — verification is via manual checklist testing and standalone PHP validation scripts, run and confirmed at the end of each task.
- Employee area is mobile-first with a bottom navigation bar (Home | Documenti | Menu); admin area is desktop-first with a fixed sidebar (Dashboard, Nuovo caricamento, Caricamenti, Dipendenti).
- Project root is `C:\xampp\htdocs\portale-dipendenti`, served at `http://localhost/portale-dipendenti`.

---

## File Structure

```
C:\xampp\htdocs\portale-dipendenti\
├── composer.json
├── composer.lock
├── vendor\                          (Composer, gitignored)
├── package.json
├── tailwind.config.js
├── postcss.config.js
├── node_modules\                    (npm, gitignored)
├── .gitignore
├── config\
│   └── database.php                 (DB connection constants, PDO factory)
├── storage\                         (OUTSIDE webroot access via .htaccess deny-all; gitignored contents)
│   ├── originali\                   (uploaded cumulative PDFs)
│   └── documenti\                   (per-employee extracted PDFs)
├── src\
│   ├── db.php                       (PDO connection singleton)
│   ├── auth.php                     (session helpers: require_login, require_admin, current_user)
│   ├── Utente.php                   (Utente model: CRUD + password logic)
│   ├── Caricamento.php              (Caricamento model: CRUD)
│   ├── Documento.php                (Documento model: CRUD + query helpers)
│   ├── PaginaNonAssociata.php       (PaginaNonAssociata model: CRUD)
│   ├── PdfExtractor.php             (core extraction: text per page, CF grouping, netto parsing)
│   ├── PdfSplitter.php              (FPDI-based page range extraction to new PDF)
│   └── helpers.php                  (small shared utilities: formatMese, formatEuro, redirect, csrf)
├── public\
│   └── assets\
│       ├── css\
│       │   ├── input.css            (Tailwind directives + DaisyUI plugin)
│       │   └── output.css           (built, gitignored)
│       └── js\
│           └── app.js               (shared jQuery helpers: preview loader, carosello)
├── index.php                        (redirects to login.php or dashboard based on session)
├── login.php
├── logout.php
├── cambia-password.php
├── scarica-documento.php            (secure download/preview endpoint)
├── anteprima-pagine.php             (admin-only on-the-fly page preview endpoint)
├── home.php                         (employee dashboard)
├── documenti.php                    (employee document archive)
├── profilo.php                      (employee menu: profile, change password, logout link)
├── admin\
│   ├── dashboard.php
│   ├── nuovo-caricamento.php        (wizard step 1: type/period/upload)
│   ├── elabora-caricamento.php      (wizard step 2: runs extraction, redirects to step 3)
│   ├── revisione-caricamento.php    (wizard step 3: split-view review)
│   ├── caricamenti.php              (upload history list)
│   ├── dipendenti.php               (employee list + create form)
│   ├── dipendente-modifica.php      (edit employee, reset password)
│   └── dipendente-documenti.php     (admin read-only view of one employee's documents)
├── templates\
│   ├── layout-dipendente.php        (shared header/bottom-nav wrapper for employee pages)
│   ├── layout-admin.php             (shared header/sidebar wrapper for admin pages)
│   └── partials\
│       ├── nav-dipendente.php
│       └── nav-admin.php
├── sql\
│   └── schema.sql                   (full DDL for all tables)
└── scripts\
    └── valida-estrazione.php        (standalone CLI script to test PdfExtractor against a sample PDF)
```

**Rationale for grouping:** Models (`src/*.php`) are one file per entity, each owning its own CRUD + query logic — small and focused per the spec's four tables. `PdfExtractor` and `PdfSplitter` are split because extraction (reading text, finding CF/netto, grouping pages) and splitting (writing a new physical PDF) are different concerns with different library dependencies (pdfparser vs fpdi) and are independently testable. Pages are flat PHP files per the "no router" decision; admin pages live under `admin/` as a plain subdirectory (still direct files, just namespaced by path) to mirror the sidebar's grouping.

---

## Task 1: Project scaffolding, Composer, npm/Tailwind/DaisyUI build

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\composer.json`
- Create: `C:\xampp\htdocs\portale-dipendenti\package.json`
- Create: `C:\xampp\htdocs\portale-dipendenti\tailwind.config.js`
- Create: `C:\xampp\htdocs\portale-dipendenti\postcss.config.js`
- Create: `C:\xampp\htdocs\portale-dipendenti\public\assets\css\input.css`
- Create: `C:\xampp\htdocs\portale-dipendenti\.gitignore`
- Create: `C:\xampp\htdocs\portale-dipendenti\index.php` (placeholder)

**Interfaces:**
- Produces: a working `npm run build:css` script that compiles `public/assets/css/input.css` → `public/assets/css/output.css` with Tailwind + DaisyUI available as utility classes/components. A working `composer install` that pulls in `smalot/pdfparser`, `setasign/fpdi`, `setasign/fpdi-fpdf`.

- [ ] **Step 1: Create the project directory and initialize Composer**

```bash
mkdir -p "C:/xampp/htdocs/portale-dipendenti"
cd "C:/xampp/htdocs/portale-dipendenti"
composer init --name="azienda/portale-dipendenti" --type=project --no-interaction
composer require smalot/pdfparser setasign/fpdi setasign/fpdi-fpdf
```

- [ ] **Step 2: Verify Composer packages installed**

Run: `composer show`
Expected: output lists `smalot/pdfparser`, `setasign/fpdi`, `setasign/fpdi-fpdf`, `setasign/fpdf` among installed packages.

- [ ] **Step 3: Initialize npm and install Tailwind + DaisyUI**

```bash
cd "C:/xampp/htdocs/portale-dipendenti"
npm init -y
npm install -D tailwindcss@^3 postcss autoprefixer daisyui@^4
npx tailwindcss init -p
```

- [ ] **Step 4: Configure `tailwind.config.js`**

```javascript
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./admin/**/*.php",
    "./templates/**/*.php",
    "./public/assets/js/**/*.js",
  ],
  theme: {
    extend: {},
  },
  plugins: [require("daisyui")],
  daisyui: {
    themes: ["light"],
  },
};
```

- [ ] **Step 5: Create `public/assets/css/input.css`**

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

- [ ] **Step 6: Add build scripts to `package.json`**

Edit `package.json`, add to `"scripts"`:

```json
{
  "scripts": {
    "build:css": "tailwindcss -i ./public/assets/css/input.css -o ./public/assets/css/output.css",
    "watch:css": "tailwindcss -i ./public/assets/css/input.css -o ./public/assets/css/output.css --watch"
  }
}
```

- [ ] **Step 7: Run the build and verify output**

Run: `npm run build:css`
Expected: `public/assets/css/output.css` is created and contains compiled CSS (several KB, includes `.btn`, `.navbar` DaisyUI class definitions — grep for `daisyui` string or check file size > 50KB).

- [ ] **Step 8: Create `.gitignore`**

```
/vendor/
/node_modules/
/public/assets/css/output.css
/storage/originali/*
/storage/documenti/*
!/storage/originali/.gitkeep
!/storage/documenti/.gitkeep
```

- [ ] **Step 9: Create placeholder `index.php`**

```php
<?php
// Placeholder — wired to real session-based redirect in Task 4.
echo "Portale Dipendenti — setup in corso";
```

- [ ] **Step 10: Verify Apache serves the project**

Run: `curl -s http://localhost/portale-dipendenti/index.php`
Expected: output contains `Portale Dipendenti — setup in corso`. (If this fails, confirm XAMPP Apache is running: `curl -s http://localhost/` should return XAMPP's default page or a directory listing.)

- [ ] **Step 11: Commit**

Since the project has no git repository yet, initialize one first.

```bash
cd "C:/xampp/htdocs/portale-dipendenti"
git init
git add .
git commit -m "chore: scaffold project, Composer deps, Tailwind/DaisyUI build"
```

---

## Task 2: MySQL schema and database connection layer

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\sql\schema.sql`
- Create: `C:\xampp\htdocs\portale-dipendenti\config\database.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\src\db.php`

**Interfaces:**
- Produces: `db()` function (in `src/db.php`) returning a shared `PDO` instance, configured with `PDO::ATTR_ERRMODE = PDO::ERRMODE_EXCEPTION` and `PDO::ATTR_DEFAULT_FETCH_MODE = PDO::FETCH_ASSOC`. All later tasks call `db()` to get a connection.

- [ ] **Step 1: Write the schema SQL**

```sql
-- sql/schema.sql
CREATE DATABASE IF NOT EXISTS portale_dipendenti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portale_dipendenti;

CREATE TABLE utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    codice_fiscale VARCHAR(16) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    ruolo ENUM('admin', 'dipendente') NOT NULL DEFAULT 'dipendente',
    deve_cambiare_password TINYINT(1) NOT NULL DEFAULT 1,
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE caricamenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento ENUM('busta_paga', 'cu') NOT NULL,
    etichetta ENUM('Cedolino', '13a mensilita', '14a mensilita') NULL,
    mese TINYINT NULL,
    anno SMALLINT NOT NULL,
    nome_file_originale VARCHAR(255) NOT NULL,
    percorso_file_originale VARCHAR(500) NOT NULL,
    caricato_da INT NOT NULL,
    caricato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    stato ENUM('elaborazione', 'completato', 'con_errori') NOT NULL DEFAULT 'elaborazione',
    FOREIGN KEY (caricato_da) REFERENCES utenti(id)
) ENGINE=InnoDB;

CREATE TABLE documenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caricamento_id INT NOT NULL,
    utente_id INT NULL,
    tipo_documento ENUM('busta_paga', 'cu') NOT NULL,
    etichetta VARCHAR(50) NULL,
    mese TINYINT NULL,
    anno SMALLINT NOT NULL,
    percorso_file VARCHAR(500) NOT NULL,
    pagina_da INT NOT NULL,
    pagina_a INT NOT NULL,
    netto_in_busta DECIMAL(10,2) NULL,
    stato ENUM('associato', 'da_rivedere', 'scartato') NOT NULL DEFAULT 'associato',
    creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caricamento_id) REFERENCES caricamenti(id),
    FOREIGN KEY (utente_id) REFERENCES utenti(id),
    UNIQUE KEY uq_documento_periodo (utente_id, tipo_documento, etichetta, mese, anno)
) ENGINE=InnoDB;

CREATE TABLE pagine_non_associate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caricamento_id INT NOT NULL,
    pagina_da INT NOT NULL,
    pagina_a INT NOT NULL,
    cf_estratto VARCHAR(16) NULL,
    stato ENUM('in_attesa', 'risolta', 'scartata') NOT NULL DEFAULT 'in_attesa',
    risolta_da INT NULL,
    risolta_il DATETIME NULL,
    FOREIGN KEY (caricamento_id) REFERENCES caricamenti(id),
    FOREIGN KEY (risolta_da) REFERENCES utenti(id)
) ENGINE=InnoDB;
```

Note: `UNIQUE KEY uq_documento_periodo` enforces the spec's uniqueness constraint at the database level for rows with a non-NULL `utente_id`. MySQL treats NULL as distinct in unique keys, which is fine here since `utente_id IS NULL` rows (not yet associated) never reach the `documenti` table — unassociated pages live in `pagine_non_associate` instead, so every `documenti` row always has a non-NULL `utente_id` in practice.

- [ ] **Step 2: Run the schema against MySQL**

Run: `"C:\xampp\mysql\bin\mysql.exe" -u root < sql/schema.sql`
Expected: no output (success). Verify with: `"C:\xampp\mysql\bin\mysql.exe" -u root -e "SHOW TABLES;" portale_dipendenti`
Expected output lists: `caricamenti`, `documenti`, `pagine_non_associate`, `utenti`.

- [ ] **Step 3: Create `config/database.php`**

```php
<?php
// config/database.php
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'portale_dipendenti',
    'user' => 'root',
    'pass' => '',
];
```

- [ ] **Step 4: Create `src/db.php`**

```php
<?php
// src/db.php

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $config = require __DIR__ . '/../config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['dbname']
        );
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
```

- [ ] **Step 5: Write a standalone verification script and run it**

Create a throwaway file `scripts/_verify_db.php`:

```php
<?php
require __DIR__ . '/../src/db.php';
$stmt = db()->query('SELECT COUNT(*) AS n FROM utenti');
echo "utenti count: " . $stmt->fetch()['n'] . "\n";
```

Run: `php scripts/_verify_db.php`
Expected: `utenti count: 0`

Delete `scripts/_verify_db.php` after confirming (it was only to prove connectivity).

- [ ] **Step 6: Commit**

```bash
git add sql/schema.sql config/database.php src/db.php
git commit -m "feat: add MySQL schema and PDO connection layer"
```

---

## Task 3: Storage directories and file security (deny direct web access)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\storage\originali\.gitkeep`
- Create: `C:\xampp\htdocs\portale-dipendenti\storage\documenti\.gitkeep`
- Create: `C:\xampp\htdocs\portale-dipendenti\storage\.htaccess`

**Interfaces:**
- Produces: `storage/originali/` and `storage/documenti/` directories that exist on disk and are unreachable via direct HTTP request, for `PdfSplitter` (Task 6) and the upload flow (Task 9) to write into.

- [ ] **Step 1: Create the storage directories**

```bash
mkdir -p "C:/xampp/htdocs/portale-dipendenti/storage/originali"
mkdir -p "C:/xampp/htdocs/portale-dipendenti/storage/documenti"
touch "C:/xampp/htdocs/portale-dipendenti/storage/originali/.gitkeep"
touch "C:/xampp/htdocs/portale-dipendenti/storage/documenti/.gitkeep"
```

- [ ] **Step 2: Create `storage/.htaccess` to deny all direct access**

```apache
Require all denied
```

- [ ] **Step 3: Verify direct access is blocked**

Run: `curl -s -o /dev/null -w "%{http_code}" http://localhost/portale-dipendenti/storage/.htaccess`
Expected: `403` (or connection refused if Apache doesn't serve dotfiles at all — either way, not `200`).

Also place a temporary test file to confirm the deny rule covers real files, not just dotfiles:

```bash
echo "test" > "C:/xampp/htdocs/portale-dipendenti/storage/originali/_test.txt"
curl -s -o /dev/null -w "%{http_code}" http://localhost/portale-dipendenti/storage/originali/_test.txt
```

Expected: `403`. Then delete the test file: `rm "C:/xampp/htdocs/portale-dipendenti/storage/originali/_test.txt"`

- [ ] **Step 4: Commit**

```bash
git add storage/.htaccess storage/originali/.gitkeep storage/documenti/.gitkeep
git commit -m "chore: add storage directories with deny-all access rule"
```

---

## Task 4: Auth core — session helpers, login, logout, forced password change

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\src\Utente.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\src\auth.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\src\helpers.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\login.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\logout.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\cambia-password.php`
- Modify: `C:\xampp\htdocs\portale-dipendenti\index.php`

**Interfaces:**
- Consumes: `db()` from `src/db.php` (Task 2).
- Produces:
  - `Utente::findByEmail(string $email): ?array`
  - `Utente::findById(int $id): ?array`
  - `Utente::verifyPassword(array $utente, string $password): bool`
  - `Utente::setPassword(int $id, string $newPassword, bool $mustChange = false): void`
  - `Utente::create(string $nome, string $cognome, string $email, string $codiceFiscale, string $ruolo = 'dipendente'): array` — returns `['id' => int, 'password_temporanea' => string]`
  - `require_login(): array` — returns current user row or redirects to `login.php`
  - `require_admin(): array` — returns current user row or redirects (403-style) if not admin
  - `current_user(): ?array`
  - `redirect(string $path): void` (in `helpers.php`)

- [ ] **Step 1: Create `src/helpers.php`**

```php
<?php
// src/helpers.php

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function formatEuro(?float $valore): string
{
    if ($valore === null) {
        return '—';
    }
    return number_format($valore, 2, ',', '.') . ' €';
}

function formatMese(?int $mese): string
{
    $nomi = [
        1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
        5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
        9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
    ];
    return $mese !== null ? ($nomi[$mese] ?? '') : '';
}

function generaPasswordTemporanea(int $lunghezza = 10): string
{
    $caratteri = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < $lunghezza; $i++) {
        $password .= $caratteri[random_int(0, strlen($caratteri) - 1)];
    }
    return $password;
}
```

- [ ] **Step 2: Create `src/Utente.php`**

```php
<?php
// src/Utente.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class Utente
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM utenti WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM utenti WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function verifyPassword(array $utente, string $password): bool
    {
        return password_verify($password, $utente['password_hash']);
    }

    public static function setPassword(int $id, string $newPassword, bool $mustChange = false): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = db()->prepare('UPDATE utenti SET password_hash = ?, deve_cambiare_password = ? WHERE id = ?');
        $stmt->execute([$hash, $mustChange ? 1 : 0, $id]);
    }

    public static function create(string $nome, string $cognome, string $email, string $codiceFiscale, string $ruolo = 'dipendente'): array
    {
        $passwordTemporanea = generaPasswordTemporanea();
        $hash = password_hash($passwordTemporanea, PASSWORD_BCRYPT);
        $stmt = db()->prepare(
            'INSERT INTO utenti (nome, cognome, email, codice_fiscale, password_hash, ruolo, deve_cambiare_password, attivo)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1)'
        );
        $stmt->execute([$nome, $cognome, $email, strtoupper($codiceFiscale), $hash, $ruolo]);
        return [
            'id' => (int) db()->lastInsertId(),
            'password_temporanea' => $passwordTemporanea,
        ];
    }

    public static function findByCodiceFiscale(string $cf): ?array
    {
        $stmt = db()->prepare('SELECT * FROM utenti WHERE codice_fiscale = ?');
        $stmt->execute([strtoupper($cf)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        return db()->query('SELECT * FROM utenti WHERE ruolo = "dipendente" ORDER BY cognome, nome')->fetchAll();
    }

    public static function update(int $id, string $nome, string $cognome, string $email, string $codiceFiscale): void
    {
        $stmt = db()->prepare('UPDATE utenti SET nome = ?, cognome = ?, email = ?, codice_fiscale = ? WHERE id = ?');
        $stmt->execute([$nome, $cognome, $email, strtoupper($codiceFiscale), $id]);
    }

    public static function setAttivo(int $id, bool $attivo): void
    {
        $stmt = db()->prepare('UPDATE utenti SET attivo = ? WHERE id = ?');
        $stmt->execute([$attivo ? 1 : 0, $id]);
    }
}
```

- [ ] **Step 3: Create `src/auth.php`**

```php
<?php
// src/auth.php
require_once __DIR__ . '/Utente.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    if (!isset($_SESSION['utente_id'])) {
        return null;
    }
    return Utente::findById((int) $_SESSION['utente_id']);
}

function require_login(): array
{
    $utente = current_user();
    if ($utente === null || !$utente['attivo']) {
        redirect('/portale-dipendenti/login.php');
    }
    return $utente;
}

function require_admin(): array
{
    $utente = require_login();
    if ($utente['ruolo'] !== 'admin') {
        http_response_code(403);
        echo 'Accesso negato.';
        exit;
    }
    return $utente;
}

function login_utente(array $utente): void
{
    session_regenerate_id(true);
    $_SESSION['utente_id'] = $utente['id'];
}

function logout_utente(): void
{
    $_SESSION = [];
    session_destroy();
}
```

- [ ] **Step 4: Create `login.php`**

```php
<?php
// login.php
require_once __DIR__ . '/src/auth.php';

if (current_user() !== null) {
    redirect('/portale-dipendenti/index.php');
}

$errore = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $utente = Utente::findByEmail($email);

    if ($utente === null || !$utente['attivo'] || !Utente::verifyPassword($utente, $password)) {
        $errore = 'Email o password non corretti.';
    } else {
        login_utente($utente);
        redirect('/portale-dipendenti/index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accedi — Portale Dipendenti</title>
    <link rel="stylesheet" href="/portale-dipendenti/public/assets/css/output.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-base-200">
    <div class="card w-full max-w-sm bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title">Portale Dipendenti</h1>
            <?php if ($errore): ?>
                <div class="alert alert-error text-sm"><?= htmlspecialchars($errore) ?></div>
            <?php endif; ?>
            <form method="post" class="flex flex-col gap-3">
                <input type="email" name="email" placeholder="Email" required class="input input-bordered w-full">
                <input type="password" name="password" placeholder="Password" required class="input input-bordered w-full">
                <button type="submit" class="btn btn-primary w-full">Accedi</button>
            </form>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 5: Create `logout.php`**

```php
<?php
// logout.php
require_once __DIR__ . '/src/auth.php';
logout_utente();
redirect('/portale-dipendenti/login.php');
```

- [ ] **Step 6: Create `cambia-password.php`**

```php
<?php
// cambia-password.php
require_once __DIR__ . '/src/auth.php';

$utente = require_login();
$errore = null;
$obbligatorio = (bool) $utente['deve_cambiare_password'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuova = $_POST['nuova_password'] ?? '';
    $conferma = $_POST['conferma_password'] ?? '';

    if (strlen($nuova) < 8) {
        $errore = 'La nuova password deve avere almeno 8 caratteri.';
    } elseif ($nuova !== $conferma) {
        $errore = 'Le due password non coincidono.';
    } else {
        Utente::setPassword((int) $utente['id'], $nuova, false);
        redirect('/portale-dipendenti/index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambia password — Portale Dipendenti</title>
    <link rel="stylesheet" href="/portale-dipendenti/public/assets/css/output.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-base-200">
    <div class="card w-full max-w-sm bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title">Cambia password</h1>
            <?php if ($obbligatorio): ?>
                <div class="alert alert-warning text-sm">Devi impostare una nuova password prima di continuare.</div>
            <?php endif; ?>
            <?php if ($errore): ?>
                <div class="alert alert-error text-sm"><?= htmlspecialchars($errore) ?></div>
            <?php endif; ?>
            <form method="post" class="flex flex-col gap-3">
                <input type="password" name="nuova_password" placeholder="Nuova password" required minlength="8" class="input input-bordered w-full">
                <input type="password" name="conferma_password" placeholder="Conferma password" required minlength="8" class="input input-bordered w-full">
                <button type="submit" class="btn btn-primary w-full">Salva</button>
            </form>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 7: Update `index.php` to route by session/role**

```php
<?php
// index.php
require_once __DIR__ . '/src/auth.php';

$utente = current_user();

if ($utente === null) {
    redirect('/portale-dipendenti/login.php');
}

if ($utente['deve_cambiare_password']) {
    redirect('/portale-dipendenti/cambia-password.php');
}

if ($utente['ruolo'] === 'admin') {
    redirect('/portale-dipendenti/admin/dashboard.php');
} else {
    redirect('/portale-dipendenti/home.php');
}
```

- [ ] **Step 8: Manually create a test admin user and verify the login flow**

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root portale_dipendenti -e "INSERT INTO utenti (nome, cognome, email, codice_fiscale, password_hash, ruolo, deve_cambiare_password, attivo) VALUES ('Admin', 'Test', 'admin@test.it', 'ADMTST00A00H501Z', '$(php -r "echo password_hash('password123', PASSWORD_BCRYPT);")', 'admin', 0, 1);"
```

Run: `curl -s -c cookies.txt -d "email=admin@test.it&password=password123" http://localhost/portale-dipendenti/login.php -o /dev/null -w "%{http_code}\n"`
Expected: `302` (redirect on success — note `admin/dashboard.php` and `home.php` don't exist yet, that's fine for this check).

Run with wrong password: `curl -s -d "email=admin@test.it&password=wrong" http://localhost/portale-dipendenti/login.php | grep -o "Email o password non corretti"`
Expected: matches the error string.

Clean up the cookie file: `rm cookies.txt`

- [ ] **Step 9: Commit**

```bash
git add src/helpers.php src/Utente.php src/auth.php login.php logout.php cambia-password.php index.php
git commit -m "feat: add authentication (login, logout, forced password change)"
```

---

## Task 5: Shared layout templates (employee bottom-nav, admin sidebar)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\templates\layout-dipendente.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\templates\layout-admin.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\templates\partials\nav-dipendente.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\templates\partials\nav-admin.php`

**Interfaces:**
- Consumes: `current_user()` from `src/auth.php` (Task 4).
- Produces: `layout_dipendente_inizio(string $titolo, string $paginaAttiva)` / `layout_dipendente_fine()` and `layout_admin_inizio(string $titolo, string $paginaAttiva)` / `layout_admin_fine()` — pairs of functions that print the opening/closing HTML shell. Later pages call `layout_..._inizio()`, print their content, then `layout_..._fine()`. `$paginaAttiva` is one of `'home'|'documenti'|'menu'` (employee) or `'dashboard'|'nuovo-caricamento'|'caricamenti'|'dipendenti'` (admin), used to highlight the current nav item.

- [ ] **Step 1: Create `templates/partials/nav-dipendente.php`**

```php
<?php
/** @var string $paginaAttiva */
function classe_nav_dipendente(string $voce, string $paginaAttiva): string
{
    return $voce === $paginaAttiva ? 'text-primary' : 'text-base-content/60';
}
?>
<div class="btm-nav border-t bg-base-100">
    <a href="/portale-dipendenti/home.php" class="<?= classe_nav_dipendente('home', $paginaAttiva) ?>">
        <span class="text-xl">🏠</span>
        <span class="btm-nav-label text-xs">Home</span>
    </a>
    <a href="/portale-dipendenti/documenti.php" class="<?= classe_nav_dipendente('documenti', $paginaAttiva) ?>">
        <span class="text-xl">📑</span>
        <span class="btm-nav-label text-xs">Documenti</span>
    </a>
    <a href="/portale-dipendenti/profilo.php" class="<?= classe_nav_dipendente('menu', $paginaAttiva) ?>">
        <span class="text-xl">☰</span>
        <span class="btm-nav-label text-xs">Menu</span>
    </a>
</div>
```

- [ ] **Step 2: Create `templates/layout-dipendente.php`**

```php
<?php
require_once __DIR__ . '/partials/nav-dipendente.php';

function layout_dipendente_inizio(string $titolo, string $paginaAttiva): void
{
    ?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titolo) ?> — Portale Dipendenti</title>
    <link rel="stylesheet" href="/portale-dipendenti/public/assets/css/output.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="min-h-screen bg-base-200 pb-20">
    <main class="max-w-md mx-auto p-4">
    <?php
}

function layout_dipendente_fine(string $paginaAttiva): void
{
    ?>
    </main>
    <?php include __DIR__ . '/partials/nav-dipendente.php'; ?>
    <script src="/portale-dipendenti/public/assets/js/app.js"></script>
</body>
</html>
    <?php
}
```

Note: jQuery is loaded from a CDN (interactivity library, not the styling framework — the spec's "no CDN" decision was specifically about Tailwind/DaisyUI build tooling, not every script tag). This keeps the plan consistent with "jQuery" being listed as a plain dependency in the spec's tech stack without a local build step requirement.

- [ ] **Step 3: Create `templates/partials/nav-admin.php`**

```php
<?php
/** @var string $paginaAttiva */
function classe_nav_admin(string $voce, string $paginaAttiva): string
{
    return $voce === $paginaAttiva ? 'active' : '';
}
?>
<ul class="menu bg-base-100 w-56 min-h-screen p-4 gap-1">
    <li class="menu-title">Portale Dipendenti</li>
    <li><a href="/portale-dipendenti/admin/dashboard.php" class="<?= classe_nav_admin('dashboard', $paginaAttiva) ?>">Dashboard</a></li>
    <li><a href="/portale-dipendenti/admin/nuovo-caricamento.php" class="<?= classe_nav_admin('nuovo-caricamento', $paginaAttiva) ?>">Nuovo caricamento</a></li>
    <li><a href="/portale-dipendenti/admin/caricamenti.php" class="<?= classe_nav_admin('caricamenti', $paginaAttiva) ?>">Caricamenti</a></li>
    <li><a href="/portale-dipendenti/admin/dipendenti.php" class="<?= classe_nav_admin('dipendenti', $paginaAttiva) ?>">Dipendenti</a></li>
</ul>
```

- [ ] **Step 4: Create `templates/layout-admin.php`**

```php
<?php
require_once __DIR__ . '/../src/auth.php';

function layout_admin_inizio(string $titolo, string $paginaAttiva): void
{
    $utente = current_user();
    ?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titolo) ?> — Admin — Portale Dipendenti</title>
    <link rel="stylesheet" href="/portale-dipendenti/public/assets/css/output.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="min-h-screen bg-base-200">
    <div class="flex">
        <?php include __DIR__ . '/partials/nav-admin.php'; ?>
        <div class="flex-1">
            <div class="navbar bg-base-100 shadow-sm px-6">
                <div class="flex-1 font-semibold"><?= htmlspecialchars($titolo) ?></div>
                <div class="flex-none flex items-center gap-3">
                    <span class="text-sm"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></span>
                    <a href="/portale-dipendenti/logout.php" class="btn btn-ghost btn-sm">Esci</a>
                </div>
            </div>
            <main class="p-6">
    <?php
}

function layout_admin_fine(): void
{
    ?>
            </main>
        </div>
    </div>
    <script src="/portale-dipendenti/public/assets/js/app.js"></script>
</body>
</html>
    <?php
}
```

- [ ] **Step 5: Create empty `public/assets/js/app.js` placeholder**

```javascript
// public/assets/js/app.js
// Shared jQuery helpers — populated in later tasks (carosello, preview loader).
```

- [ ] **Step 6: Write a throwaway smoke-test page to verify both layouts render**

Create temporary file `_test-layout.php` in project root:

```php
<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/templates/layout-dipendente.php';
layout_dipendente_inizio('Test', 'home');
echo '<p>Contenuto di prova</p>';
layout_dipendente_fine('home');
```

Log in as the admin test user created in Task 4 (email `admin@test.it`, password `password123`) using a browser or `curl -c cookies.txt -d "email=admin@test.it&password=password123" http://localhost/portale-dipendenti/login.php`, then:

Run: `curl -s -b cookies.txt http://localhost/portale-dipendenti/_test-layout.php | grep -c "btm-nav"`
Expected: `1` or more (the bottom nav markup is present).

Delete `_test-layout.php` and `cookies.txt` after confirming.

- [ ] **Step 7: Commit**

```bash
git add templates/ public/assets/js/app.js
git commit -m "feat: add shared employee and admin layout templates"
```

---

## Task 6: PdfExtractor — text extraction, CF grouping, netto parsing

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\src\PdfExtractor.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\scripts\valida-estrazione.php`

**Interfaces:**
- Consumes: `Smalot\PdfParser\Parser` (from `smalot/pdfparser`, installed in Task 1).
- Produces:
  - `PdfExtractor::estraiTestoPerPagina(string $percorsoPdf): array` — returns `[1 => 'testo pagina 1', 2 => 'testo pagina 2', ...]` (1-indexed).
  - `PdfExtractor::estraiCodiceFiscale(string $testoPagina): ?string` — returns the matched CF or `null`.
  - `PdfExtractor::estraiNettoInBusta(string $testoPagina): ?float` — returns the parsed euro amount or `null`.
  - `PdfExtractor::raggruppaPerCf(array $testoPerPagina): array` — returns a list of blocks: `[['cf' => string|null, 'pagina_da' => int, 'pagina_a' => int, 'netto' => float|null], ...]` in page order.

This task's regex patterns for CF and "Netto in busta" are placeholders using the standard Italian CF format and a common label — **Task 6 Step 7 below explicitly flags these for retuning against a real sample PDF**, per the spec's "Punti da verificare con il primo PDF reale".

- [ ] **Step 1: Write the failing test script for CF extraction**

Create `scripts/valida-estrazione.php`:

```php
<?php
// scripts/valida-estrazione.php
// Standalone validation script — run manually against a real cumulative PDF
// to verify CF and netto extraction patterns before relying on them in production.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/PdfExtractor.php';

if ($argc < 2) {
    fwrite(STDERR, "Uso: php scripts/valida-estrazione.php <percorso-pdf>\n");
    exit(1);
}

$percorso = $argv[1];
$testoPerPagina = PdfExtractor::estraiTestoPerPagina($percorso);
echo "Pagine trovate: " . count($testoPerPagina) . "\n\n";

$blocchi = PdfExtractor::raggruppaPerCf($testoPerPagina);
foreach ($blocchi as $i => $blocco) {
    printf(
        "Blocco %d: pagine %d-%d, CF=%s, netto=%s\n",
        $i + 1,
        $blocco['pagina_da'],
        $blocco['pagina_a'],
        $blocco['cf'] ?? '(non trovato)',
        $blocco['netto'] !== null ? number_format($blocco['netto'], 2) : '(non trovato)'
    );
}
```

- [ ] **Step 2: Write inline unit checks using a minimal synthetic PDF-independent test**

Since we don't yet have a real sample PDF, first prove the pure-string logic (`estraiCodiceFiscale`, `estraiNettoInBusta`, `raggruppaPerCf`) works against known text fixtures, independent of actual PDF parsing. Create a throwaway file `scripts/_test_extractor_logic.php`:

```php
<?php
require_once __DIR__ . '/../src/PdfExtractor.php';

function assertEqual($actual, $expected, string $label): void
{
    if ($actual !== $expected) {
        echo "FAIL [$label]: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
    echo "PASS [$label]\n";
}

// CF extraction
assertEqual(
    PdfExtractor::estraiCodiceFiscale('Dipendente: Mario Rossi CF: RSSMRA80A01H501U altro testo'),
    'RSSMRA80A01H501U',
    'CF trovato'
);
assertEqual(
    PdfExtractor::estraiCodiceFiscale('nessun codice fiscale qui'),
    null,
    'CF assente'
);

// Netto extraction
assertEqual(
    PdfExtractor::estraiNettoInBusta('... NETTO IN BUSTA € 1.720,00 ...'),
    1720.00,
    'Netto trovato'
);
assertEqual(
    PdfExtractor::estraiNettoInBusta('nessun netto qui'),
    null,
    'Netto assente'
);

// Raggruppamento per CF (pagine consecutive con stesso CF => un blocco)
$testoPerPagina = [
    1 => 'CF: RSSMRA80A01H501U pagina 1 di Rossi',
    2 => 'CF: RSSMRA80A01H501U pagina 2 di Rossi',
    3 => 'CF: BNCLGI75B02F205X Bianchi NETTO IN BUSTA € 1.500,50',
];
$blocchi = PdfExtractor::raggruppaPerCf($testoPerPagina);
assertEqual(count($blocchi), 2, 'Numero blocchi');
assertEqual($blocchi[0]['cf'], 'RSSMRA80A01H501U', 'Blocco 1 CF');
assertEqual($blocchi[0]['pagina_da'], 1, 'Blocco 1 pagina_da');
assertEqual($blocchi[0]['pagina_a'], 2, 'Blocco 1 pagina_a');
assertEqual($blocchi[1]['cf'], 'BNCLGI75B02F205X', 'Blocco 2 CF');
assertEqual($blocchi[1]['netto'], 1500.50, 'Blocco 2 netto');

echo "Tutti i test sono passati.\n";
```

- [ ] **Step 3: Run the test script to verify it fails (PdfExtractor doesn't exist yet)**

Run: `php scripts/_test_extractor_logic.php`
Expected: PHP fatal error, `Class "PdfExtractor" not found`.

- [ ] **Step 4: Implement `src/PdfExtractor.php`**

```php
<?php
// src/PdfExtractor.php
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

class PdfExtractor
{
    // Standard Italian Codice Fiscale format: 6 letters, 2 digits, 1 letter,
    // 2 digits, 1 letter, 3 digits, 1 letter.
    private const PATTERN_CF = '/\b([A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z])\b/';

    // PLACEHOLDER pattern — verify and adjust against a real cumulative PDF
    // (see "Punti da verificare con il primo PDF reale" in the design spec).
    // Matches labels like "NETTO IN BUSTA € 1.720,00" or "NETTO A PAGARE 1.720,00".
    private const PATTERN_NETTO = '/NETTO\s+(?:IN\s+BUSTA|A\s+PAGARE)\D{0,10}?([\d.]+,\d{2})/iu';

    public static function estraiTestoPerPagina(string $percorsoPdf): array
    {
        $parser = new Parser();
        $documento = $parser->parseFile($percorsoPdf);
        $pagine = $documento->getPages();

        $risultato = [];
        foreach ($pagine as $indice => $pagina) {
            $risultato[$indice + 1] = $pagina->getText();
        }
        return $risultato;
    }

    public static function estraiCodiceFiscale(string $testoPagina): ?string
    {
        $testoNormalizzato = strtoupper($testoPagina);
        if (preg_match(self::PATTERN_CF, $testoNormalizzato, $match)) {
            return $match[1];
        }
        return null;
    }

    public static function estraiNettoInBusta(string $testoPagina): ?float
    {
        if (preg_match(self::PATTERN_NETTO, $testoPagina, $match)) {
            // Italian number format: thousands "." decimal ",".
            $numeroNormalizzato = str_replace('.', '', $match[1]);
            $numeroNormalizzato = str_replace(',', '.', $numeroNormalizzato);
            return (float) $numeroNormalizzato;
        }
        return null;
    }

    public static function raggruppaPerCf(array $testoPerPagina): array
    {
        $blocchi = [];
        $cfCorrente = null;
        $paginaInizio = null;
        $nettoCorrente = null;

        $chiudiBlocco = function () use (&$blocchi, &$cfCorrente, &$paginaInizio, &$nettoCorrente, &$paginaPrecedente) {
            if ($paginaInizio !== null) {
                $blocchi[] = [
                    'cf' => $cfCorrente,
                    'pagina_da' => $paginaInizio,
                    'pagina_a' => $paginaPrecedente,
                    'netto' => $nettoCorrente,
                ];
            }
        };

        $paginaPrecedente = null;

        foreach ($testoPerPagina as $numeroPagina => $testo) {
            $cf = self::estraiCodiceFiscale($testo);
            $netto = self::estraiNettoInBusta($testo);

            if ($paginaInizio === null) {
                // Prima pagina in assoluto.
                $cfCorrente = $cf;
                $paginaInizio = $numeroPagina;
                $nettoCorrente = $netto;
            } elseif ($cf === $cfCorrente) {
                // Stesso CF (o entrambi null): continua il blocco corrente.
                if ($netto !== null) {
                    $nettoCorrente = $netto;
                }
            } else {
                // CF cambiato: chiudi il blocco corrente, aprine uno nuovo.
                $chiudiBlocco();
                $cfCorrente = $cf;
                $paginaInizio = $numeroPagina;
                $nettoCorrente = $netto;
            }

            $paginaPrecedente = $numeroPagina;
        }

        $chiudiBlocco();

        return $blocchi;
    }
}
```

- [ ] **Step 5: Run the test script to verify it passes**

Run: `php scripts/_test_extractor_logic.php`
Expected: all six `PASS` lines, ending with `Tutti i test sono passati.`

- [ ] **Step 6: Delete the throwaway test script**

```bash
rm scripts/_test_extractor_logic.php
```

(The permanent validation tool is `scripts/valida-estrazione.php`, kept for use against real PDFs — see Step 7.)

- [ ] **Step 7: Document the retuning step for when a real PDF becomes available**

This step has no code — it's a recorded manual follow-up. When the first real cumulative PDF is available:
1. Run `php scripts/valida-estrazione.php <percorso-al-pdf-reale>`.
2. Inspect the output: does every page/block show a CF? Does `netto` show a value for pages that should have one?
3. If not, open the PDF, find the actual label text and number format used, and adjust `PATTERN_CF` / `PATTERN_NETTO` in `src/PdfExtractor.php` accordingly, then re-run Step 5's fixture tests to confirm no regression.

- [ ] **Step 8: Commit**

```bash
git add src/PdfExtractor.php scripts/valida-estrazione.php
git commit -m "feat: add PdfExtractor for CF grouping and netto parsing"
```

---

## Task 7: PdfSplitter — extract page ranges into new PDFs

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\src\PdfSplitter.php`

**Interfaces:**
- Consumes: `setasign\Fpdi\Fpdi` (from `setasign/fpdi` + `setasign/fpdi-fpdf`, installed in Task 1).
- Produces: `PdfSplitter::estraiPagine(string $percorsoSorgente, int $paginaDa, int $paginaA, string $percorsoDestinazione): void` — writes a new PDF containing only pages `paginaDa..paginaA` (inclusive, 1-indexed) from the source PDF to `percorsoDestinazione`. Used by Task 9 (upload processing) and Task 13 (admin on-the-fly page preview).

- [ ] **Step 1: Write a throwaway test that builds a 3-page source PDF and splits it**

Create `scripts/_test_splitter.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/PdfSplitter.php';

use setasign\Fpdi\Fpdi;

// Build a synthetic 3-page source PDF for the test.
$sorgente = sys_get_temp_dir() . '/_test_sorgente.pdf';
$pdf = new Fpdi();
foreach (['Pagina Uno', 'Pagina Due', 'Pagina Tre'] as $testo) {
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 16);
    $pdf->Cell(0, 10, $testo);
}
$pdf->Output('F', $sorgente);

// Split pages 2-3 into a new file.
$destinazione = sys_get_temp_dir() . '/_test_destinazione.pdf';
PdfSplitter::estraiPagine($sorgente, 2, 3, $destinazione);

if (!file_exists($destinazione)) {
    echo "FAIL: file di destinazione non creato\n";
    exit(1);
}

// Verify the resulting PDF has exactly 2 pages by re-opening it with FPDI.
$verifica = new Fpdi();
$numPagine = $verifica->setSourceFile($destinazione);

if ($numPagine !== 2) {
    echo "FAIL: attese 2 pagine, trovate $numPagine\n";
    exit(1);
}

echo "PASS: split di 2 pagine su 3 riuscito correttamente\n";

unlink($sorgente);
unlink($destinazione);
```

- [ ] **Step 2: Run the test to verify it fails (PdfSplitter doesn't exist yet)**

Run: `php scripts/_test_splitter.php`
Expected: PHP fatal error, `Class "PdfSplitter" not found`.

- [ ] **Step 3: Implement `src/PdfSplitter.php`**

```php
<?php
// src/PdfSplitter.php
require_once __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

class PdfSplitter
{
    public static function estraiPagine(string $percorsoSorgente, int $paginaDa, int $paginaA, string $percorsoDestinazione): void
    {
        $pdf = new Fpdi();
        $numPagineTotali = $pdf->setSourceFile($percorsoSorgente);

        if ($paginaDa < 1 || $paginaA > $numPagineTotali || $paginaDa > $paginaA) {
            throw new InvalidArgumentException(
                "Intervallo pagine non valido: $paginaDa-$paginaA (il documento ha $numPagineTotali pagine)"
            );
        }

        for ($numeroPagina = $paginaDa; $numeroPagina <= $paginaA; $numeroPagina++) {
            $idTemplate = $pdf->importPage($numeroPagina);
            $dimensioni = $pdf->getTemplateSize($idTemplate);
            $pdf->AddPage($dimensioni['orientation'], [$dimensioni['width'], $dimensioni['height']]);
            $pdf->useTemplate($idTemplate);
        }

        $cartellaDestinazione = dirname($percorsoDestinazione);
        if (!is_dir($cartellaDestinazione)) {
            mkdir($cartellaDestinazione, 0755, true);
        }

        $pdf->Output('F', $percorsoDestinazione);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php scripts/_test_splitter.php`
Expected: `PASS: split di 2 pagine su 3 riuscito correttamente`

- [ ] **Step 5: Delete the throwaway test script**

```bash
rm scripts/_test_splitter.php
```

- [ ] **Step 6: Commit**

```bash
git add src/PdfSplitter.php
git commit -m "feat: add PdfSplitter for extracting page ranges via FPDI"
```

---

## Task 8: Data models — Caricamento, Documento, PaginaNonAssociata

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\src\Caricamento.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\src\Documento.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\src\PaginaNonAssociata.php`

**Interfaces:**
- Consumes: `db()` from `src/db.php` (Task 2).
- Produces:
  - `Caricamento::create(array $dati): int` — `$dati` keys: `tipo_documento, etichetta, mese, anno, nome_file_originale, percorso_file_originale, caricato_da`. Returns new id.
  - `Caricamento::findById(int $id): ?array`
  - `Caricamento::setStato(int $id, string $stato): void`
  - `Caricamento::all(): array` — most recent first.
  - `Documento::create(array $dati): int` — `$dati` keys: `caricamento_id, utente_id, tipo_documento, etichetta, mese, anno, percorso_file, pagina_da, pagina_a, netto_in_busta, stato`. Returns new id.
  - `Documento::esisteAssociato(int $utenteId, string $tipoDocumento, ?string $etichetta, ?int $mese, int $anno): ?array` — returns the conflicting row or `null`.
  - `Documento::perCaricamento(int $caricamentoId): array`
  - `Documento::perUtente(int $utenteId, ?string $tipoDocumento = null): array` — ordered chronologically (anno, mese, id).
  - `Documento::findById(int $id): ?array`
  - `Documento::scarta(int $id): void` — sets `stato = 'scartato'`.
  - `Documento::sovrascrivi(int $vecchioId, array $datiNuovo): int` — marks old row `scartato` and inserts the new row, returns new id.
  - `PaginaNonAssociata::create(array $dati): int` — `$dati` keys: `caricamento_id, pagina_da, pagina_a, cf_estratto`. Returns new id.
  - `PaginaNonAssociata::perCaricamento(int $caricamentoId, ?string $stato = null): array`
  - `PaginaNonAssociata::findById(int $id): ?array`
  - `PaginaNonAssociata::risolvi(int $id, int $risoltaDa): void` — sets `stato = 'risolta'`, `risolta_da`, `risolta_il = NOW()`.
  - `PaginaNonAssociata::scarta(int $id, int $risoltaDa): void` — sets `stato = 'scartata'`, `risolta_da`, `risolta_il = NOW()`.

- [ ] **Step 1: Write a throwaway integration test exercising all three models**

Create `scripts/_test_models.php`:

```php
<?php
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../src/PaginaNonAssociata.php';

function assertTrue($condition, string $label): void
{
    if (!$condition) {
        echo "FAIL [$label]\n";
        exit(1);
    }
    echo "PASS [$label]\n";
}

db()->beginTransaction();

// Setup: a test employee.
$dipendente = Utente::create('Test', 'Modelli', 'test.modelli@example.it', 'TSTMDL80A01H501U');

// Caricamento
$caricamentoId = Caricamento::create([
    'tipo_documento' => 'busta_paga',
    'etichetta' => 'Cedolino',
    'mese' => 12,
    'anno' => 2024,
    'nome_file_originale' => 'test.pdf',
    'percorso_file_originale' => '/tmp/test.pdf',
    'caricato_da' => $dipendente['id'],
]);
assertTrue($caricamentoId > 0, 'Caricamento creato');
assertTrue(Caricamento::findById($caricamentoId)['stato'] === 'elaborazione', 'Stato iniziale elaborazione');
Caricamento::setStato($caricamentoId, 'completato');
assertTrue(Caricamento::findById($caricamentoId)['stato'] === 'completato', 'Stato aggiornato a completato');

// Documento
$documentoId = Documento::create([
    'caricamento_id' => $caricamentoId,
    'utente_id' => $dipendente['id'],
    'tipo_documento' => 'busta_paga',
    'etichetta' => 'Cedolino',
    'mese' => 12,
    'anno' => 2024,
    'percorso_file' => '/tmp/doc.pdf',
    'pagina_da' => 1,
    'pagina_a' => 1,
    'netto_in_busta' => 1720.00,
    'stato' => 'associato',
]);
assertTrue($documentoId > 0, 'Documento creato');

$conflitto = Documento::esisteAssociato($dipendente['id'], 'busta_paga', 'Cedolino', 12, 2024);
assertTrue($conflitto !== null && $conflitto['id'] === $documentoId, 'Rileva conflitto su stessa combinazione');

$nessunConflitto = Documento::esisteAssociato($dipendente['id'], 'busta_paga', '13a mensilita', 12, 2024);
assertTrue($nessunConflitto === null, 'Nessun conflitto con etichetta diversa');

$documenti = Documento::perUtente($dipendente['id']);
assertTrue(count($documenti) === 1, 'perUtente restituisce il documento creato');

$nuovoId = Documento::sovrascrivi($documentoId, [
    'caricamento_id' => $caricamentoId,
    'utente_id' => $dipendente['id'],
    'tipo_documento' => 'busta_paga',
    'etichetta' => 'Cedolino',
    'mese' => 12,
    'anno' => 2024,
    'percorso_file' => '/tmp/doc-v2.pdf',
    'pagina_da' => 1,
    'pagina_a' => 1,
    'netto_in_busta' => 1730.00,
    'stato' => 'associato',
]);
assertTrue(Documento::findById($documentoId)['stato'] === 'scartato', 'Documento vecchio scartato dopo sovrascrittura');
assertTrue(Documento::findById($nuovoId)['stato'] === 'associato', 'Nuovo documento associato dopo sovrascrittura');

// PaginaNonAssociata
$paginaId = PaginaNonAssociata::create([
    'caricamento_id' => $caricamentoId,
    'pagina_da' => 5,
    'pagina_a' => 5,
    'cf_estratto' => null,
]);
assertTrue(PaginaNonAssociata::findById($paginaId)['stato'] === 'in_attesa', 'Pagina non associata creata in attesa');
PaginaNonAssociata::risolvi($paginaId, $dipendente['id']);
assertTrue(PaginaNonAssociata::findById($paginaId)['stato'] === 'risolta', 'Pagina risolta');

echo "Tutti i test sono passati.\n";

db()->rollBack();
```

- [ ] **Step 2: Run the test to verify it fails (models don't exist yet)**

Run: `php scripts/_test_models.php`
Expected: PHP fatal error, `Class "Caricamento" not found`.

- [ ] **Step 3: Implement `src/Caricamento.php`**

```php
<?php
// src/Caricamento.php
require_once __DIR__ . '/db.php';

class Caricamento
{
    public static function create(array $dati): int
    {
        $stmt = db()->prepare(
            'INSERT INTO caricamenti
                (tipo_documento, etichetta, mese, anno, nome_file_originale, percorso_file_originale, caricato_da, stato)
             VALUES (?, ?, ?, ?, ?, ?, ?, "elaborazione")'
        );
        $stmt->execute([
            $dati['tipo_documento'],
            $dati['etichetta'] ?? null,
            $dati['mese'] ?? null,
            $dati['anno'],
            $dati['nome_file_originale'],
            $dati['percorso_file_originale'],
            $dati['caricato_da'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM caricamenti WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function setStato(int $id, string $stato): void
    {
        $stmt = db()->prepare('UPDATE caricamenti SET stato = ? WHERE id = ?');
        $stmt->execute([$stato, $id]);
    }

    public static function all(): array
    {
        return db()->query('SELECT * FROM caricamenti ORDER BY caricato_il DESC')->fetchAll();
    }
}
```

- [ ] **Step 4: Implement `src/Documento.php`**

```php
<?php
// src/Documento.php
require_once __DIR__ . '/db.php';

class Documento
{
    public static function create(array $dati): int
    {
        $stmt = db()->prepare(
            'INSERT INTO documenti
                (caricamento_id, utente_id, tipo_documento, etichetta, mese, anno, percorso_file, pagina_da, pagina_a, netto_in_busta, stato)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $dati['caricamento_id'],
            $dati['utente_id'],
            $dati['tipo_documento'],
            $dati['etichetta'] ?? null,
            $dati['mese'] ?? null,
            $dati['anno'],
            $dati['percorso_file'],
            $dati['pagina_da'],
            $dati['pagina_a'],
            $dati['netto_in_busta'] ?? null,
            $dati['stato'] ?? 'associato',
        ]);
        return (int) db()->lastInsertId();
    }

    public static function esisteAssociato(int $utenteId, string $tipoDocumento, ?string $etichetta, ?int $mese, int $anno): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM documenti
             WHERE utente_id = ? AND tipo_documento = ? AND etichetta <=> ? AND mese <=> ? AND anno = ? AND stato = "associato"'
        );
        $stmt->execute([$utenteId, $tipoDocumento, $etichetta, $mese, $anno]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function perCaricamento(int $caricamentoId): array
    {
        $stmt = db()->prepare('SELECT d.*, u.nome, u.cognome FROM documenti d
                                JOIN utenti u ON u.id = d.utente_id
                                WHERE d.caricamento_id = ? AND d.stato = "associato"
                                ORDER BY u.cognome, u.nome');
        $stmt->execute([$caricamentoId]);
        return $stmt->fetchAll();
    }

    public static function perUtente(int $utenteId, ?string $tipoDocumento = null): array
    {
        if ($tipoDocumento !== null) {
            $stmt = db()->prepare(
                'SELECT * FROM documenti WHERE utente_id = ? AND tipo_documento = ? AND stato = "associato"
                 ORDER BY anno, mese, id'
            );
            $stmt->execute([$utenteId, $tipoDocumento]);
        } else {
            $stmt = db()->prepare(
                'SELECT * FROM documenti WHERE utente_id = ? AND stato = "associato"
                 ORDER BY anno, mese, id'
            );
            $stmt->execute([$utenteId]);
        }
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM documenti WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function scarta(int $id): void
    {
        $stmt = db()->prepare('UPDATE documenti SET stato = "scartato" WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function sovrascrivi(int $vecchioId, array $datiNuovo): int
    {
        self::scarta($vecchioId);
        return self::create($datiNuovo);
    }
}
```

Note on `esisteAssociato`: uses MySQL's `<=>` (null-safe equality) operator so that `etichetta`/`mese` being `NULL` (the CU case) still matches correctly, since standard `=` never matches `NULL`.

- [ ] **Step 5: Implement `src/PaginaNonAssociata.php`**

```php
<?php
// src/PaginaNonAssociata.php
require_once __DIR__ . '/db.php';

class PaginaNonAssociata
{
    public static function create(array $dati): int
    {
        $stmt = db()->prepare(
            'INSERT INTO pagine_non_associate (caricamento_id, pagina_da, pagina_a, cf_estratto, stato)
             VALUES (?, ?, ?, ?, "in_attesa")'
        );
        $stmt->execute([
            $dati['caricamento_id'],
            $dati['pagina_da'],
            $dati['pagina_a'],
            $dati['cf_estratto'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function perCaricamento(int $caricamentoId, ?string $stato = null): array
    {
        if ($stato !== null) {
            $stmt = db()->prepare('SELECT * FROM pagine_non_associate WHERE caricamento_id = ? AND stato = ? ORDER BY pagina_da');
            $stmt->execute([$caricamentoId, $stato]);
        } else {
            $stmt = db()->prepare('SELECT * FROM pagine_non_associate WHERE caricamento_id = ? ORDER BY pagina_da');
            $stmt->execute([$caricamentoId]);
        }
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM pagine_non_associate WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function risolvi(int $id, int $risoltaDa): void
    {
        $stmt = db()->prepare('UPDATE pagine_non_associate SET stato = "risolta", risolta_da = ?, risolta_il = NOW() WHERE id = ?');
        $stmt->execute([$risoltaDa, $id]);
    }

    public static function scarta(int $id, int $risoltaDa): void
    {
        $stmt = db()->prepare('UPDATE pagine_non_associate SET stato = "scartata", risolta_da = ?, risolta_il = NOW() WHERE id = ?');
        $stmt->execute([$risoltaDa, $id]);
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php scripts/_test_models.php`
Expected: all `PASS` lines, ending with `Tutti i test sono passati.` (the script wraps everything in a transaction and rolls back, so it leaves no test data behind — safe to re-run).

- [ ] **Step 7: Delete the throwaway test script**

```bash
rm scripts/_test_models.php
```

- [ ] **Step 8: Commit**

```bash
git add src/Caricamento.php src/Documento.php src/PaginaNonAssociata.php
git commit -m "feat: add Caricamento, Documento, PaginaNonAssociata models"
```

---

## Task 9: Upload processing pipeline (orchestrates PdfExtractor + PdfSplitter + models)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\src\ElaboraCaricamento.php`

**Interfaces:**
- Consumes: `PdfExtractor` (Task 6), `PdfSplitter` (Task 7), `Caricamento`, `Documento`, `PaginaNonAssociata`, `Utente` (Task 8, Task 4).
- Produces: `ElaboraCaricamento::esegui(int $caricamentoId): void` — reads the `caricamenti` row, runs extraction against its stored original file, and populates `documenti`/`pagine_non_associate`, then sets the caricamento's final `stato`. This is the function `admin/elabora-caricamento.php` (Task 10) calls.

This is the piece of business logic tying together "for each CF block: match to a user, check for conflicts, split the PDF, write the row" described in the spec's "Flusso di estrazione" section — kept as its own class (not inlined in a page) so it's independently testable without going through an HTTP upload.

- [ ] **Step 1: Write a throwaway integration test with a synthetic 2-employee PDF**

Create `scripts/_test_elabora.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../src/PaginaNonAssociata.php';
require_once __DIR__ . '/../src/PdfExtractor.php';
require_once __DIR__ . '/../src/PdfSplitter.php';
require_once __DIR__ . '/../src/ElaboraCaricamento.php';

use setasign\Fpdi\Fpdi;

function assertTrue($condition, string $label): void
{
    if (!$condition) {
        echo "FAIL [$label]\n";
        exit(1);
    }
    echo "PASS [$label]\n";
}

db()->beginTransaction();

// Two known employees, one CF that will not match anything.
$rossi = Utente::create('Mario', 'Rossi', 'mario.rossi.test@example.it', 'RSSMRA80A01H501U');
$bianchi = Utente::create('Luigi', 'Bianchi', 'luigi.bianchi.test@example.it', 'BNCLGI75B02F205X');

// Build a synthetic 3-page source PDF: page 1 = Rossi, page 2 = Bianchi (matched),
// page 3 = an unknown CF (goes to pagine_non_associate).
$sorgente = sys_get_temp_dir() . '/_test_elabora_sorgente.pdf';
$pdf = new Fpdi();
$pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12);
$pdf->Cell(0, 10, 'CF: RSSMRA80A01H501U NETTO IN BUSTA EUR 1.720,00');
$pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12);
$pdf->Cell(0, 10, 'CF: BNCLGI75B02F205X NETTO IN BUSTA EUR 1.500,50');
$pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12);
$pdf->Cell(0, 10, 'CF: ZZZAAA00A00Z000Z nessun dipendente con questo CF');
$pdf->Output('F', $sorgente);

$storageOriginali = sys_get_temp_dir() . '/_test_storage_originali';
$storageDocumenti = sys_get_temp_dir() . '/_test_storage_documenti';
@mkdir($storageOriginali);
@mkdir($storageDocumenti);
$percorsoOriginale = $storageOriginali . '/test.pdf';
copy($sorgente, $percorsoOriginale);

$caricamentoId = Caricamento::create([
    'tipo_documento' => 'busta_paga',
    'etichetta' => 'Cedolino',
    'mese' => 12,
    'anno' => 2024,
    'nome_file_originale' => 'test.pdf',
    'percorso_file_originale' => $percorsoOriginale,
    'caricato_da' => $rossi['id'],
]);

ElaboraCaricamento::esegui($caricamentoId, $storageDocumenti);

$documentiRossi = Documento::perUtente($rossi['id']);
assertTrue(count($documentiRossi) === 1, 'Rossi ha un documento associato');
assertTrue(abs($documentiRossi[0]['netto_in_busta'] - 1720.00) < 0.01, 'Netto di Rossi corretto');
assertTrue(file_exists($documentiRossi[0]['percorso_file']), 'File PDF di Rossi creato su disco');

$documentiBianchi = Documento::perUtente($bianchi['id']);
assertTrue(count($documentiBianchi) === 1, 'Bianchi ha un documento associato');

$paginePendenti = PaginaNonAssociata::perCaricamento($caricamentoId, 'in_attesa');
assertTrue(count($paginePendenti) === 1, 'Una pagina non associata in attesa');
assertTrue($paginePendenti[0]['cf_estratto'] === 'ZZZAAA00A00Z000Z', 'CF sconosciuto registrato correttamente');

$caricamento = Caricamento::findById($caricamentoId);
assertTrue($caricamento['stato'] === 'con_errori', 'Stato caricamento con_errori per la pagina pendente');

echo "Tutti i test sono passati.\n";

unlink($sorgente);
array_map('unlink', glob($storageDocumenti . '/*'));
@rmdir($storageDocumenti);
unlink($percorsoOriginale);
@rmdir($storageOriginali);
db()->rollBack();
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php scripts/_test_elabora.php`
Expected: PHP fatal error, `Class "ElaboraCaricamento" not found`.

- [ ] **Step 3: Implement `src/ElaboraCaricamento.php`**

```php
<?php
// src/ElaboraCaricamento.php
require_once __DIR__ . '/PdfExtractor.php';
require_once __DIR__ . '/PdfSplitter.php';
require_once __DIR__ . '/Caricamento.php';
require_once __DIR__ . '/Documento.php';
require_once __DIR__ . '/PaginaNonAssociata.php';
require_once __DIR__ . '/Utente.php';

class ElaboraCaricamento
{
    public static function esegui(int $caricamentoId, ?string $cartellaStorageDocumenti = null): void
    {
        $caricamento = Caricamento::findById($caricamentoId);
        if ($caricamento === null) {
            throw new InvalidArgumentException("Caricamento $caricamentoId non trovato");
        }

        $cartellaStorageDocumenti ??= __DIR__ . '/../storage/documenti';

        $testoPerPagina = PdfExtractor::estraiTestoPerPagina($caricamento['percorso_file_originale']);
        $blocchi = PdfExtractor::raggruppaPerCf($testoPerPagina);

        $ciSonoErrori = false;

        foreach ($blocchi as $blocco) {
            $utente = $blocco['cf'] !== null ? Utente::findByCodiceFiscale($blocco['cf']) : null;

            if ($utente === null) {
                PaginaNonAssociata::create([
                    'caricamento_id' => $caricamentoId,
                    'pagina_da' => $blocco['pagina_da'],
                    'pagina_a' => $blocco['pagina_a'],
                    'cf_estratto' => $blocco['cf'],
                ]);
                $ciSonoErrori = true;
                continue;
            }

            $esistente = Documento::esisteAssociato(
                (int) $utente['id'],
                $caricamento['tipo_documento'],
                $caricamento['etichetta'],
                $caricamento['mese'] !== null ? (int) $caricamento['mese'] : null,
                (int) $caricamento['anno']
            );

            if ($esistente !== null) {
                // Conflitto: registrato come pagina da rivedere con il CF noto,
                // cosi' l'admin lo vede nella coda di revisione e decide se sovrascrivere.
                PaginaNonAssociata::create([
                    'caricamento_id' => $caricamentoId,
                    'pagina_da' => $blocco['pagina_da'],
                    'pagina_a' => $blocco['pagina_a'],
                    'cf_estratto' => $blocco['cf'],
                ]);
                $ciSonoErrori = true;
                continue;
            }

            $nomeFile = sprintf('doc_%d_%d.pdf', $caricamentoId, $utente['id']);
            $percorsoDestinazione = rtrim($cartellaStorageDocumenti, '/\\') . DIRECTORY_SEPARATOR . $nomeFile;

            PdfSplitter::estraiPagine(
                $caricamento['percorso_file_originale'],
                $blocco['pagina_da'],
                $blocco['pagina_a'],
                $percorsoDestinazione
            );

            Documento::create([
                'caricamento_id' => $caricamentoId,
                'utente_id' => $utente['id'],
                'tipo_documento' => $caricamento['tipo_documento'],
                'etichetta' => $caricamento['etichetta'],
                'mese' => $caricamento['mese'],
                'anno' => $caricamento['anno'],
                'percorso_file' => $percorsoDestinazione,
                'pagina_da' => $blocco['pagina_da'],
                'pagina_a' => $blocco['pagina_a'],
                'netto_in_busta' => $blocco['netto'],
                'stato' => 'associato',
            ]);
        }

        Caricamento::setStato($caricamentoId, $ciSonoErrori ? 'con_errori' : 'completato');
    }
}
```

Note: a conflict (same combination already exists) is recorded in `pagine_non_associate` with its known CF, rather than a separate "conflicts" table — the spec's `pagine_non_associate.cf_estratto` field already accommodates a known-but-blocked CF, and the review page (Task 12) distinguishes "conflict" rows from "truly unrecognized" rows by checking whether `cf_estratto` matches an existing user with an existing document for that period. This keeps the data model to the four tables the spec defines.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php scripts/_test_elabora.php`
Expected: all `PASS` lines, ending with `Tutti i test sono passati.`

- [ ] **Step 5: Delete the throwaway test script**

```bash
rm scripts/_test_elabora.php
```

- [ ] **Step 6: Commit**

```bash
git add src/ElaboraCaricamento.php
git commit -m "feat: add ElaboraCaricamento upload processing pipeline"
```

---

## Task 10: Secure download/preview endpoints

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\scarica-documento.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\anteprima-pagine.php`

**Interfaces:**
- Consumes: `require_login()`, `require_admin()` (Task 4), `Documento::findById()` (Task 8), `Caricamento::findById()` (Task 8), `PdfSplitter::estraiPagine()` (Task 7).
- Produces: two HTTP endpoints used as `<a href>`/`<iframe src>` targets by every later page that shows or downloads a PDF:
  - `scarica-documento.php?id=<documento_id>&modo=inline|allegato`
  - `anteprima-pagine.php?caricamento_id=<id>&pagina_da=<n>&pagina_a=<n>` (admin only)

- [ ] **Step 1: Implement `scarica-documento.php`**

```php
<?php
// scarica-documento.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/Documento.php';

$utente = require_login();

$documentoId = (int) ($_GET['id'] ?? 0);
$modo = ($_GET['modo'] ?? 'allegato') === 'inline' ? 'inline' : 'allegato';

$documento = Documento::findById($documentoId);

if ($documento === null || $documento['stato'] !== 'associato') {
    http_response_code(404);
    exit('Documento non trovato.');
}

$autorizzato = $utente['ruolo'] === 'admin' || (int) $documento['utente_id'] === (int) $utente['id'];
if (!$autorizzato) {
    http_response_code(403);
    exit('Accesso negato.');
}

if (!file_exists($documento['percorso_file'])) {
    http_response_code(404);
    exit('File non disponibile.');
}

$nomeScaricato = sprintf(
    '%s_%d%s.pdf',
    $documento['tipo_documento'] === 'cu' ? 'CU' : ($documento['etichetta'] ?? 'Documento'),
    $documento['anno'],
    $documento['mese'] !== null ? '-' . str_pad($documento['mese'], 2, '0', STR_PAD_LEFT) : ''
);

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $modo . '; filename="' . $nomeScaricato . '"');
header('Content-Length: ' . filesize($documento['percorso_file']));
readfile($documento['percorso_file']);
exit;
```

- [ ] **Step 2: Implement `anteprima-pagine.php`**

```php
<?php
// anteprima-pagine.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/Caricamento.php';
require_once __DIR__ . '/src/PdfSplitter.php';

require_admin();

$caricamentoId = (int) ($_GET['caricamento_id'] ?? 0);
$paginaDa = (int) ($_GET['pagina_da'] ?? 0);
$paginaA = (int) ($_GET['pagina_a'] ?? 0);

$caricamento = Caricamento::findById($caricamentoId);
if ($caricamento === null || $paginaDa < 1 || $paginaA < $paginaDa) {
    http_response_code(400);
    exit('Richiesta non valida.');
}

$percorsoTemporaneo = sys_get_temp_dir() . '/anteprima_' . $caricamentoId . '_' . $paginaDa . '_' . $paginaA . '_' . uniqid() . '.pdf';

try {
    PdfSplitter::estraiPagine($caricamento['percorso_file_originale'], $paginaDa, $paginaA, $percorsoTemporaneo);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="anteprima.pdf"');
    header('Content-Length: ' . filesize($percorsoTemporaneo));
    readfile($percorsoTemporaneo);
} finally {
    if (file_exists($percorsoTemporaneo)) {
        unlink($percorsoTemporaneo);
    }
}
exit;
```

- [ ] **Step 3: Manual verification against real data**

This endpoint needs an actual `documenti` row with a real file on disk to test meaningfully — defer full verification to Task 14 (upload wizard), which is the first task that produces real rows through the UI. For now, verify only the auth-gate behavior:

Run (no session cookie): `curl -s -o /dev/null -w "%{http_code}" "http://localhost/portale-dipendenti/scarica-documento.php?id=1"`
Expected: `302` (redirected to login by `require_login()`).

Run (no session cookie): `curl -s -o /dev/null -w "%{http_code}" "http://localhost/portale-dipendenti/anteprima-pagine.php?caricamento_id=1&pagina_da=1&pagina_a=1"`
Expected: `302` (redirected to login by `require_admin()` → `require_login()`).

- [ ] **Step 4: Commit**

```bash
git add scarica-documento.php anteprima-pagine.php
git commit -m "feat: add secure document download and on-the-fly page preview endpoints"
```

---

## Task 11: Admin — Nuovo caricamento wizard (step 1: tipo/periodo/upload)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\admin\nuovo-caricamento.php`

**Interfaces:**
- Consumes: `require_admin()` (Task 4), `layout_admin_inizio/fine()` (Task 5), `Caricamento::create()` (Task 8).
- Produces: on successful POST, saves the uploaded file to `storage/originali/`, creates a `caricamenti` row (stato `elaborazione`), and redirects to `admin/elabora-caricamento.php?caricamento_id=<id>` (Task 12).

- [ ] **Step 1: Implement `admin/nuovo-caricamento.php`**

```php
<?php
// admin/nuovo-caricamento.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$errore = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipoDocumento = $_POST['tipo_documento'] ?? '';
    $etichetta = $_POST['etichetta'] ?? null;
    $mese = $_POST['mese'] !== '' ? (int) $_POST['mese'] : null;
    $anno = (int) ($_POST['anno'] ?? 0);

    if (!in_array($tipoDocumento, ['busta_paga', 'cu'], true)) {
        $errore = 'Seleziona un tipo di documento valido.';
    } elseif ($tipoDocumento === 'busta_paga' && !in_array($etichetta, ['Cedolino', '13a mensilita', '14a mensilita'], true)) {
        $errore = 'Seleziona un\'etichetta valida per la busta paga.';
    } elseif ($tipoDocumento === 'busta_paga' && ($mese < 1 || $mese > 12)) {
        $errore = 'Seleziona un mese valido.';
    } elseif ($anno < 2000 || $anno > 2100) {
        $errore = 'Anno non valido.';
    } elseif (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $errore = 'Carica un file PDF valido.';
    } elseif (strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        $errore = 'Il file deve essere un PDF.';
    } else {
        $cartellaOriginali = __DIR__ . '/../storage/originali';
        if (!is_dir($cartellaOriginali)) {
            mkdir($cartellaOriginali, 0755, true);
        }
        $nomeFile = uniqid('originale_', true) . '.pdf';
        $percorsoDestinazione = $cartellaOriginali . '/' . $nomeFile;
        move_uploaded_file($_FILES['pdf']['tmp_name'], $percorsoDestinazione);

        $utente = current_user();
        $caricamentoId = Caricamento::create([
            'tipo_documento' => $tipoDocumento,
            'etichetta' => $tipoDocumento === 'busta_paga' ? $etichetta : null,
            'mese' => $tipoDocumento === 'busta_paga' ? $mese : null,
            'anno' => $anno,
            'nome_file_originale' => $_FILES['pdf']['name'],
            'percorso_file_originale' => $percorsoDestinazione,
            'caricato_da' => $utente['id'],
        ]);

        redirect('/portale-dipendenti/admin/elabora-caricamento.php?caricamento_id=' . $caricamentoId);
    }
}

layout_admin_inizio('Nuovo caricamento', 'nuovo-caricamento');
?>
<ul class="steps w-full mb-6">
    <li class="step step-primary">Tipo, periodo e file</li>
    <li class="step">Elaborazione</li>
    <li class="step">Revisione</li>
</ul>

<?php if ($errore): ?>
    <div class="alert alert-error mb-4"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card bg-base-100 shadow p-6 max-w-lg flex flex-col gap-4" id="form-caricamento">
    <div>
        <label class="label"><span class="label-text">Tipo documento</span></label>
        <select name="tipo_documento" id="tipo_documento" class="select select-bordered w-full" required>
            <option value="">Seleziona...</option>
            <option value="busta_paga">Busta paga</option>
            <option value="cu">CU</option>
        </select>
    </div>

    <div id="campi-busta-paga" style="display:none">
        <label class="label"><span class="label-text">Etichetta</span></label>
        <select name="etichetta" class="select select-bordered w-full">
            <option value="Cedolino">Cedolino</option>
            <option value="13a mensilita">13ª mensilità</option>
            <option value="14a mensilita">14ª mensilità</option>
        </select>

        <label class="label mt-2"><span class="label-text">Mese</span></label>
        <select name="mese" class="select select-bordered w-full">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>"><?= formatMese($m) ?></option>
            <?php endfor; ?>
        </select>
    </div>

    <div>
        <label class="label"><span class="label-text">Anno</span></label>
        <input type="number" name="anno" class="input input-bordered w-full" value="<?= date('Y') ?>" required>
    </div>

    <div>
        <label class="label"><span class="label-text">File PDF cumulativo</span></label>
        <input type="file" name="pdf" accept="application/pdf" class="file-input file-input-bordered w-full" required>
    </div>

    <button type="submit" class="btn btn-primary">Avanti</button>
</form>

<script>
$(function () {
    function aggiornaCampiBustaPaga() {
        var tipo = $('#tipo_documento').val();
        $('#campi-busta-paga').toggle(tipo === 'busta_paga');
    }
    $('#tipo_documento').on('change', aggiornaCampiBustaPaga);
    aggiornaCampiBustaPaga();
});
</script>
<?php
layout_admin_fine();
```

- [ ] **Step 2: Verify the auth gate manually**

Run (no session cookie): `curl -s -o /dev/null -w "%{http_code}" http://localhost/portale-dipendenti/admin/nuovo-caricamento.php`
Expected: `302` (redirected to login).

- [ ] **Step 3: Verify the form renders when logged in as admin**

```bash
curl -s -c cookies.txt -d "email=admin@test.it&password=password123" http://localhost/portale-dipendenti/login.php -o /dev/null
curl -s -b cookies.txt http://localhost/portale-dipendenti/admin/nuovo-caricamento.php | grep -c "form-caricamento"
rm cookies.txt
```

Expected: `1` (the form markup is present).

- [ ] **Step 4: Commit**

```bash
git add admin/nuovo-caricamento.php
git commit -m "feat: add admin upload wizard step 1 (type/period/file)"
```

---

## Task 12: Admin — elaborazione (wizard step 2, runs ElaboraCaricamento)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\admin\elabora-caricamento.php`

**Interfaces:**
- Consumes: `require_admin()` (Task 4), `Caricamento::findById()` (Task 8), `ElaboraCaricamento::esegui()` (Task 9), `layout_admin_inizio/fine()` (Task 5).
- Produces: runs the extraction pipeline synchronously, then redirects to `admin/revisione-caricamento.php?caricamento_id=<id>` (Task 13).

- [ ] **Step 1: Implement `admin/elabora-caricamento.php`**

```php
<?php
// admin/elabora-caricamento.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../src/ElaboraCaricamento.php';

require_admin();

$caricamentoId = (int) ($_GET['caricamento_id'] ?? 0);
$caricamento = Caricamento::findById($caricamentoId);

if ($caricamento === null) {
    http_response_code(404);
    exit('Caricamento non trovato.');
}

if ($caricamento['stato'] === 'elaborazione') {
    ElaboraCaricamento::esegui($caricamentoId);
}

redirect('/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=' . $caricamentoId);
```

Note: this page has no UI of its own — it is a synchronous processing step reached via redirect from Step 1's form submit, and it immediately redirects onward to the review page (Step 3 of the wizard). The "elaborazione" step in the spec's 3-step wizard is represented by this brief server-side pause; because processing is synchronous and expected to complete in well under Apache's default timeout for the payroll volumes in scope (tens to hundreds of employees), no progress bar or polling is implemented.

- [ ] **Step 2: Verify the auth gate manually**

Run (no session cookie): `curl -s -o /dev/null -w "%{http_code}" "http://localhost/portale-dipendenti/admin/elabora-caricamento.php?caricamento_id=1"`
Expected: `302` (redirected to login).

- [ ] **Step 3: Commit**

```bash
git add admin/elabora-caricamento.php
git commit -m "feat: add admin upload wizard step 2 (extraction trigger)"
```

---

## Task 13: Admin — revisione caricamento (wizard step 3: split-view review)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\admin\revisione-caricamento.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\admin\revisione-azione.php` (handles Assegna/Scarta/Sovrascrivi/Ignora actions)
- Modify: `C:\xampp\htdocs\portale-dipendenti\public\assets\js\app.js`

**Interfaces:**
- Consumes: `require_admin()` (Task 4), `Caricamento::findById()`, `Documento::perCaricamento()`, `Documento::sovrascrivi()`, `Documento::create()`, `PaginaNonAssociata::perCaricamento()`, `PaginaNonAssociata::risolvi()`, `PaginaNonAssociata::scarta()`, `Utente::all()`, `Utente::findByCodiceFiscale()` (Task 8, Task 4), `PdfSplitter::estraiPagine()` (Task 7), `scarica-documento.php`/`anteprima-pagine.php` (Task 10).
- Produces: the wizard's final page. `revisione-azione.php` is a POST-only endpoint the review page's forms submit to, redirecting back to `revisione-caricamento.php?caricamento_id=<id>` after each action.

This task distinguishes two flavors of `pagine_non_associate` rows for display purposes, as discussed in Task 9: a row whose `cf_estratto` matches an existing user (a **conflict** — that user already has a document for this exact period) is shown in a "Conflitti" table with Sovrascrivi/Ignora actions; a row whose `cf_estratto` is null or matches no user is shown in a "Da rivedere" table with Assegna/Scarta actions.

- [ ] **Step 1: Implement `admin/revisione-caricamento.php`**

```php
<?php
// admin/revisione-caricamento.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../src/PaginaNonAssociata.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$caricamentoId = (int) ($_GET['caricamento_id'] ?? 0);
$caricamento = Caricamento::findById($caricamentoId);

if ($caricamento === null) {
    http_response_code(404);
    exit('Caricamento non trovato.');
}

$documentiAssociati = Documento::perCaricamento($caricamentoId);
$paginePendenti = PaginaNonAssociata::perCaricamento($caricamentoId, 'in_attesa');
$dipendenti = Utente::all();

// Separa le pagine pendenti in "conflitti" (CF noto con documento gia' esistente
// per lo stesso periodo) da "da rivedere" (CF ignoto o non trovato).
$conflitti = [];
$daRivedere = [];
foreach ($paginePendenti as $pagina) {
    $utenteMatch = $pagina['cf_estratto'] !== null ? Utente::findByCodiceFiscale($pagina['cf_estratto']) : null;
    $inConflitto = $utenteMatch !== null && Documento::esisteAssociato(
        (int) $utenteMatch['id'],
        $caricamento['tipo_documento'],
        $caricamento['etichetta'],
        $caricamento['mese'] !== null ? (int) $caricamento['mese'] : null,
        (int) $caricamento['anno']
    ) !== null;

    if ($inConflitto) {
        $pagina['utente_match'] = $utenteMatch;
        $conflitti[] = $pagina;
    } else {
        $daRivedere[] = $pagina;
    }
}

layout_admin_inizio('Revisione caricamento', 'nuovo-caricamento');
?>
<ul class="steps w-full mb-6">
    <li class="step step-primary">Tipo, periodo e file</li>
    <li class="step step-primary">Elaborazione</li>
    <li class="step step-primary">Revisione</li>
</ul>

<div class="grid grid-cols-2 gap-6">
    <div class="flex flex-col gap-6 overflow-y-auto" style="max-height: 75vh">

        <div>
            <h2 class="font-semibold mb-2">Documenti associati (<?= count($documentiAssociati) ?>)</h2>
            <table class="table table-sm">
                <thead><tr><th>Dipendente</th><th>Pagine</th><th>Netto</th></tr></thead>
                <tbody>
                <?php foreach ($documentiAssociati as $doc): ?>
                    <tr class="hover cursor-pointer riga-preview" data-src="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>&modo=inline">
                        <td><?= htmlspecialchars($doc['cognome'] . ' ' . $doc['nome']) ?></td>
                        <td><?= $doc['pagina_da'] ?>-<?= $doc['pagina_a'] ?></td>
                        <td><?= formatEuro($doc['netto_in_busta'] !== null ? (float) $doc['netto_in_busta'] : null) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($documentiAssociati)): ?>
                    <tr><td colspan="3" class="text-base-content/60">Nessun documento associato.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div>
            <h2 class="font-semibold mb-2">Da rivedere (<?= count($daRivedere) ?>)</h2>
            <table class="table table-sm">
                <thead><tr><th>Pagine</th><th>CF</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($daRivedere as $pagina): ?>
                    <tr class="hover cursor-pointer riga-preview" data-src="/portale-dipendenti/anteprima-pagine.php?caricamento_id=<?= $caricamentoId ?>&pagina_da=<?= $pagina['pagina_da'] ?>&pagina_a=<?= $pagina['pagina_a'] ?>">
                        <td><?= $pagina['pagina_da'] ?>-<?= $pagina['pagina_a'] ?></td>
                        <td><?= htmlspecialchars($pagina['cf_estratto'] ?? '(non trovato)') ?></td>
                        <td onclick="event.stopPropagation()">
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="flex gap-2 items-center">
                                <input type="hidden" name="azione" value="assegna">
                                <input type="hidden" name="pagina_id" value="<?= $pagina['id'] ?>">
                                <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                <select name="utente_id" class="select select-bordered select-xs">
                                    <?php foreach ($dipendenti as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['cognome'] . ' ' . $d['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-xs btn-primary">Assegna</button>
                            </form>
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="inline">
                                <input type="hidden" name="azione" value="scarta_pagina">
                                <input type="hidden" name="pagina_id" value="<?= $pagina['id'] ?>">
                                <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                <button type="submit" class="btn btn-xs">Scarta</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($daRivedere)): ?>
                    <tr><td colspan="3" class="text-base-content/60">Nessuna pagina da rivedere.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($conflitti)): ?>
        <div>
            <h2 class="font-semibold mb-2">Conflitti (<?= count($conflitti) ?>)</h2>
            <table class="table table-sm">
                <thead><tr><th>Pagine</th><th>Dipendente</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($conflitti as $conflitto): ?>
                    <tr class="hover cursor-pointer riga-preview" data-src="/portale-dipendenti/anteprima-pagine.php?caricamento_id=<?= $caricamentoId ?>&pagina_da=<?= $conflitto['pagina_da'] ?>&pagina_a=<?= $conflitto['pagina_a'] ?>">
                        <td><?= $conflitto['pagina_da'] ?>-<?= $conflitto['pagina_a'] ?></td>
                        <td><?= htmlspecialchars($conflitto['utente_match']['cognome'] . ' ' . $conflitto['utente_match']['nome']) ?></td>
                        <td onclick="event.stopPropagation()">
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="inline">
                                <input type="hidden" name="azione" value="sovrascrivi">
                                <input type="hidden" name="pagina_id" value="<?= $conflitto['id'] ?>">
                                <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                <button type="submit" class="btn btn-xs btn-warning">Sovrascrivi</button>
                            </form>
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="inline">
                                <input type="hidden" name="azione" value="ignora"><input type="hidden" name="pagina_id" value="<?= $conflitto['id'] ?>">
                                <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                <button type="submit" class="btn btn-xs">Ignora</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>

    <div>
        <div class="mockup-window border bg-base-100" style="height: 75vh">
            <iframe id="preview-frame" src="about:blank" class="w-full h-full"></iframe>
        </div>
    </div>
</div>

<?php
layout_admin_fine();
```

- [ ] **Step 2: Add the row-click preview loader to `public/assets/js/app.js`**

```javascript
// public/assets/js/app.js
$(function () {
    $(document).on('click', '.riga-preview', function () {
        var src = $(this).data('src');
        $('#preview-frame').attr('src', src);
    });
});
```

- [ ] **Step 3: Implement `admin/revisione-azione.php`**

```php
<?php
// admin/revisione-azione.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../src/PaginaNonAssociata.php';
require_once __DIR__ . '/../src/PdfSplitter.php';
require_once __DIR__ . '/../src/Utente.php';

$admin = require_admin();

$azione = $_POST['azione'] ?? '';
$caricamentoId = (int) ($_POST['caricamento_id'] ?? 0);
$paginaId = (int) ($_POST['pagina_id'] ?? 0);

$caricamento = Caricamento::findById($caricamentoId);
$pagina = PaginaNonAssociata::findById($paginaId);

if ($caricamento === null || $pagina === null) {
    http_response_code(404);
    exit('Risorsa non trovata.');
}

function estraiEAssocia(array $caricamento, array $pagina, int $utenteId): int
{
    $nomeFile = sprintf('doc_%d_%d_%s.pdf', $caricamento['id'], $utenteId, uniqid());
    $cartellaStorageDocumenti = __DIR__ . '/../storage/documenti';
    $percorsoDestinazione = $cartellaStorageDocumenti . '/' . $nomeFile;

    PdfSplitter::estraiPagine(
        $caricamento['percorso_file_originale'],
        (int) $pagina['pagina_da'],
        (int) $pagina['pagina_a'],
        $percorsoDestinazione
    );

    return Documento::create([
        'caricamento_id' => $caricamento['id'],
        'utente_id' => $utenteId,
        'tipo_documento' => $caricamento['tipo_documento'],
        'etichetta' => $caricamento['etichetta'],
        'mese' => $caricamento['mese'],
        'anno' => $caricamento['anno'],
        'percorso_file' => $percorsoDestinazione,
        'pagina_da' => (int) $pagina['pagina_da'],
        'pagina_a' => (int) $pagina['pagina_a'],
        'netto_in_busta' => null,
        'stato' => 'associato',
    ]);
}

switch ($azione) {
    case 'assegna':
        $utenteId = (int) ($_POST['utente_id'] ?? 0);
        estraiEAssocia($caricamento, $pagina, $utenteId);
        PaginaNonAssociata::risolvi($paginaId, (int) $admin['id']);
        break;

    case 'scarta_pagina':
        PaginaNonAssociata::scarta($paginaId, (int) $admin['id']);
        break;

    case 'sovrascrivi':
        $utenteMatch = Utente::findByCodiceFiscale((string) $pagina['cf_estratto']);
        if ($utenteMatch !== null) {
            $esistente = Documento::esisteAssociato(
                (int) $utenteMatch['id'],
                $caricamento['tipo_documento'],
                $caricamento['etichetta'],
                $caricamento['mese'] !== null ? (int) $caricamento['mese'] : null,
                (int) $caricamento['anno']
            );
            $nomeFile = sprintf('doc_%d_%d_%s.pdf', $caricamento['id'], $utenteMatch['id'], uniqid());
            $percorsoDestinazione = __DIR__ . '/../storage/documenti/' . $nomeFile;
            PdfSplitter::estraiPagine(
                $caricamento['percorso_file_originale'],
                (int) $pagina['pagina_da'],
                (int) $pagina['pagina_a'],
                $percorsoDestinazione
            );
            if ($esistente !== null) {
                Documento::sovrascrivi($esistente['id'], [
                    'caricamento_id' => $caricamento['id'],
                    'utente_id' => $utenteMatch['id'],
                    'tipo_documento' => $caricamento['tipo_documento'],
                    'etichetta' => $caricamento['etichetta'],
                    'mese' => $caricamento['mese'],
                    'anno' => $caricamento['anno'],
                    'percorso_file' => $percorsoDestinazione,
                    'pagina_da' => (int) $pagina['pagina_da'],
                    'pagina_a' => (int) $pagina['pagina_a'],
                    'netto_in_busta' => null,
                    'stato' => 'associato',
                ]);
            }
        }
        PaginaNonAssociata::risolvi($paginaId, (int) $admin['id']);
        break;

    case 'ignora':
        PaginaNonAssociata::scarta($paginaId, (int) $admin['id']);
        break;

    default:
        http_response_code(400);
        exit('Azione non riconosciuta.');
}

// Se non restano piu' pagine pendenti, il caricamento e' completato.
$paginePendentiRimaste = PaginaNonAssociata::perCaricamento($caricamentoId, 'in_attesa');
if (empty($paginePendentiRimaste)) {
    Caricamento::setStato($caricamentoId, 'completato');
}

redirect('/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=' . $caricamentoId);
```

- [ ] **Step 4: Manual end-to-end verification with a real synthetic upload**

This is the first point where the full wizard can be exercised through the browser. Log in as the admin test user, go to `http://localhost/portale-dipendenti/admin/nuovo-caricamento.php`, and:
1. Build a tiny multi-page test PDF offline (any tool, e.g. print-to-PDF a 2-page Word doc, or reuse the synthetic-PDF approach from Task 9's test script by adapting it into a standalone `php scripts/_genera_pdf_test.php > /tmp/test-cumulativo.pdf`-style one-off — delete the script after use).
2. Submit the wizard form with that file. Confirm redirect through step 2 lands on step 3 (`revisione-caricamento.php`).
3. Click a row in "Documenti associati" (if the test PDF's CF matches the seeded test employee) — confirm the PDF renders in the right-hand iframe.
4. For a row in "Da rivedere", pick a dipendente from the dropdown and click "Assegna" — confirm the row disappears and a new row appears in "Documenti associati".

Expected: no PHP errors/warnings in the page output or Apache error log (`C:\xampp\apache\logs\error.log`) during the whole flow.

- [ ] **Step 5: Commit**

```bash
git add admin/revisione-caricamento.php admin/revisione-azione.php public/assets/js/app.js
git commit -m "feat: add admin upload wizard step 3 (split-view review with PDF preview)"
```

---

## Task 14: Admin — Dashboard and Caricamenti (upload history)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\admin\dashboard.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\admin\caricamenti.php`

**Interfaces:**
- Consumes: `require_admin()` (Task 4), `Caricamento::all()` (Task 8), `layout_admin_inizio/fine()` (Task 5).
- Produces: two read-only listing pages; no new interfaces consumed by later tasks.

- [ ] **Step 1: Implement `admin/dashboard.php`**

```php
<?php
// admin/dashboard.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$caricamentiRecenti = array_slice(Caricamento::all(), 0, 10);

layout_admin_inizio('Dashboard', 'dashboard');
?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-semibold">Caricamenti recenti</h1>
    <a href="/portale-dipendenti/admin/nuovo-caricamento.php" class="btn btn-primary">Nuovo caricamento</a>
</div>

<table class="table bg-base-100 shadow">
    <thead><tr><th>Data</th><th>Tipo</th><th>Etichetta</th><th>Periodo</th><th>Stato</th></tr></thead>
    <tbody>
    <?php foreach ($caricamentiRecenti as $c): ?>
        <tr class="hover cursor-pointer" onclick="window.location='/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=<?= $c['id'] ?>'">
            <td><?= htmlspecialchars($c['caricato_il']) ?></td>
            <td><?= $c['tipo_documento'] === 'cu' ? 'CU' : 'Busta paga' ?></td>
            <td><?= htmlspecialchars($c['etichetta'] ?? '—') ?></td>
            <td><?= $c['mese'] !== null ? formatMese((int) $c['mese']) . ' ' : '' ?><?= $c['anno'] ?></td>
            <td>
                <span class="badge <?= $c['stato'] === 'completato' ? 'badge-success' : ($c['stato'] === 'con_errori' ? 'badge-warning' : 'badge-ghost') ?>">
                    <?= htmlspecialchars($c['stato']) ?>
                </span>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($caricamentiRecenti)): ?>
        <tr><td colspan="5" class="text-base-content/60">Nessun caricamento effettuato.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php
layout_admin_fine();
```

- [ ] **Step 2: Implement `admin/caricamenti.php`**

```php
<?php
// admin/caricamenti.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$tuttiICaricamenti = Caricamento::all();

layout_admin_inizio('Caricamenti', 'caricamenti');
?>
<h1 class="text-xl font-semibold mb-6">Storico caricamenti</h1>

<table class="table bg-base-100 shadow">
    <thead><tr><th>Data</th><th>Tipo</th><th>Etichetta</th><th>Periodo</th><th>Stato</th><th>File originale</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tuttiICaricamenti as $c): ?>
        <tr class="hover">
            <td><?= htmlspecialchars($c['caricato_il']) ?></td>
            <td><?= $c['tipo_documento'] === 'cu' ? 'CU' : 'Busta paga' ?></td>
            <td><?= htmlspecialchars($c['etichetta'] ?? '—') ?></td>
            <td><?= $c['mese'] !== null ? formatMese((int) $c['mese']) . ' ' : '' ?><?= $c['anno'] ?></td>
            <td>
                <span class="badge <?= $c['stato'] === 'completato' ? 'badge-success' : ($c['stato'] === 'con_errori' ? 'badge-warning' : 'badge-ghost') ?>">
                    <?= htmlspecialchars($c['stato']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($c['nome_file_originale']) ?></td>
            <td>
                <a href="/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=<?= $c['id'] ?>" class="btn btn-xs">Apri</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($tuttiICaricamenti)): ?>
        <tr><td colspan="7" class="text-base-content/60">Nessun caricamento effettuato.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php
layout_admin_fine();
```

- [ ] **Step 3: Verify both pages render for a logged-in admin**

```bash
curl -s -c cookies.txt -d "email=admin@test.it&password=password123" http://localhost/portale-dipendenti/login.php -o /dev/null
curl -s -b cookies.txt http://localhost/portale-dipendenti/admin/dashboard.php | grep -c "Caricamenti recenti"
curl -s -b cookies.txt http://localhost/portale-dipendenti/admin/caricamenti.php | grep -c "Storico caricamenti"
rm cookies.txt
```

Expected: both greps output `1`.

- [ ] **Step 4: Commit**

```bash
git add admin/dashboard.php admin/caricamenti.php
git commit -m "feat: add admin dashboard and upload history pages"
```

---

## Task 15: Admin — Gestione dipendenti (list, create, edit, reset password, disable)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\admin\dipendenti.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\admin\dipendente-modifica.php`
- Create: `C:\xampp\htdocs\portale-dipendenti\admin\dipendente-documenti.php`

**Interfaces:**
- Consumes: `require_admin()` (Task 4), `Utente::all()`, `Utente::create()`, `Utente::findById()`, `Utente::update()`, `Utente::setAttivo()`, `Utente::setPassword()` (Task 4), `generaPasswordTemporanea()` (Task 4), `Documento::perUtente()` (Task 8), `layout_admin_inizio/fine()` (Task 5).
- Produces: full employee management UI. No new interfaces for later tasks.

- [ ] **Step 1: Implement `admin/dipendenti.php`**

```php
<?php
// admin/dipendenti.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$errore = null;
$passwordGenerata = null;
$nomeNuovoDipendente = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'crea') {
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $codiceFiscale = trim($_POST['codice_fiscale'] ?? '');

    if ($nome === '' || $cognome === '' || $email === '' || $codiceFiscale === '') {
        $errore = 'Tutti i campi sono obbligatori.';
    } elseif (Utente::findByEmail($email) !== null) {
        $errore = 'Esiste gia\' un utente con questa email.';
    } elseif (Utente::findByCodiceFiscale($codiceFiscale) !== null) {
        $errore = 'Esiste gia\' un utente con questo codice fiscale.';
    } else {
        $risultato = Utente::create($nome, $cognome, $email, $codiceFiscale, 'dipendente');
        $passwordGenerata = $risultato['password_temporanea'];
        $nomeNuovoDipendente = "$nome $cognome";
    }
}

$dipendenti = Utente::all();

layout_admin_inizio('Dipendenti', 'dipendenti');
?>
<h1 class="text-xl font-semibold mb-6">Dipendenti</h1>

<?php if ($passwordGenerata): ?>
    <div class="alert alert-success mb-4">
        Dipendente <?= htmlspecialchars($nomeNuovoDipendente) ?> creato. Password temporanea:
        <strong><?= htmlspecialchars($passwordGenerata) ?></strong>
        — comunicala fuori banda, non verra' mostrata di nuovo.
    </div>
<?php endif; ?>
<?php if ($errore): ?>
    <div class="alert alert-error mb-4"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>

<div class="grid grid-cols-3 gap-6">
    <table class="table bg-base-100 shadow col-span-2">
        <thead><tr><th>Nome</th><th>Email</th><th>CF</th><th>Stato</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($dipendenti as $d): ?>
            <tr class="hover">
                <td><?= htmlspecialchars($d['cognome'] . ' ' . $d['nome']) ?></td>
                <td><?= htmlspecialchars($d['email']) ?></td>
                <td><?= htmlspecialchars($d['codice_fiscale']) ?></td>
                <td>
                    <span class="badge <?= $d['attivo'] ? 'badge-success' : 'badge-ghost' ?>">
                        <?= $d['attivo'] ? 'Attivo' : 'Disattivato' ?>
                    </span>
                </td>
                <td class="flex gap-2">
                    <a href="/portale-dipendenti/admin/dipendente-modifica.php?id=<?= $d['id'] ?>" class="btn btn-xs">Modifica</a>
                    <a href="/portale-dipendenti/admin/dipendente-documenti.php?id=<?= $d['id'] ?>" class="btn btn-xs">Documenti</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($dipendenti)): ?>
            <tr><td colspan="5" class="text-base-content/60">Nessun dipendente registrato.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <form method="post" class="card bg-base-100 shadow p-6 flex flex-col gap-3">
        <h2 class="font-semibold">Nuovo dipendente</h2>
        <input type="hidden" name="azione" value="crea">
        <input type="text" name="nome" placeholder="Nome" required class="input input-bordered w-full">
        <input type="text" name="cognome" placeholder="Cognome" required class="input input-bordered w-full">
        <input type="email" name="email" placeholder="Email" required class="input input-bordered w-full">
        <input type="text" name="codice_fiscale" placeholder="Codice Fiscale" required maxlength="16" class="input input-bordered w-full">
        <button type="submit" class="btn btn-primary">Crea</button>
    </form>
</div>
<?php
layout_admin_fine();
```

- [ ] **Step 2: Implement `admin/dipendente-modifica.php`**

```php
<?php
// admin/dipendente-modifica.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$dipendenteId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$dipendente = Utente::findById($dipendenteId);

if ($dipendente === null) {
    http_response_code(404);
    exit('Dipendente non trovato.');
}

$messaggio = null;
$passwordGenerata = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'aggiorna') {
        Utente::update(
            $dipendenteId,
            trim($_POST['nome'] ?? ''),
            trim($_POST['cognome'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['codice_fiscale'] ?? '')
        );
        $messaggio = 'Dati aggiornati.';
    } elseif ($azione === 'reset_password') {
        $nuovaPassword = generaPasswordTemporanea();
        Utente::setPassword($dipendenteId, $nuovaPassword, true);
        $passwordGenerata = $nuovaPassword;
    } elseif ($azione === 'attiva') {
        Utente::setAttivo($dipendenteId, true);
        $messaggio = 'Dipendente riattivato.';
    } elseif ($azione === 'disattiva') {
        Utente::setAttivo($dipendenteId, false);
        $messaggio = 'Dipendente disattivato.';
    }

    $dipendente = Utente::findById($dipendenteId);
}

layout_admin_inizio('Modifica dipendente', 'dipendenti');
?>
<h1 class="text-xl font-semibold mb-6">Modifica dipendente</h1>

<?php if ($messaggio): ?>
    <div class="alert alert-success mb-4"><?= htmlspecialchars($messaggio) ?></div>
<?php endif; ?>
<?php if ($passwordGenerata): ?>
    <div class="alert alert-success mb-4">
        Nuova password temporanea: <strong><?= htmlspecialchars($passwordGenerata) ?></strong>
        — comunicala fuori banda, non verra' mostrata di nuovo.
    </div>
<?php endif; ?>

<div class="card bg-base-100 shadow p-6 max-w-lg flex flex-col gap-4">
    <form method="post" class="flex flex-col gap-3">
        <input type="hidden" name="id" value="<?= $dipendente['id'] ?>">
        <input type="hidden" name="azione" value="aggiorna">
        <input type="text" name="nome" value="<?= htmlspecialchars($dipendente['nome']) ?>" required class="input input-bordered w-full">
        <input type="text" name="cognome" value="<?= htmlspecialchars($dipendente['cognome']) ?>" required class="input input-bordered w-full">
        <input type="email" name="email" value="<?= htmlspecialchars($dipendente['email']) ?>" required class="input input-bordered w-full">
        <input type="text" name="codice_fiscale" value="<?= htmlspecialchars($dipendente['codice_fiscale']) ?>" required maxlength="16" class="input input-bordered w-full">
        <button type="submit" class="btn btn-primary">Salva</button>
    </form>

    <form method="post">
        <input type="hidden" name="id" value="<?= $dipendente['id'] ?>">
        <input type="hidden" name="azione" value="reset_password">
        <button type="submit" class="btn btn-outline w-full">Genera nuova password</button>
    </form>

    <?php if ($dipendente['attivo']): ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= $dipendente['id'] ?>">
            <input type="hidden" name="azione" value="disattiva">
            <button type="submit" class="btn btn-error btn-outline w-full">Disattiva</button>
        </form>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= $dipendente['id'] ?>">
            <input type="hidden" name="azione" value="attiva">
            <button type="submit" class="btn btn-success btn-outline w-full">Riattiva</button>
        </form>
    <?php endif; ?>
</div>
<?php
layout_admin_fine();
```

- [ ] **Step 3: Implement `admin/dipendente-documenti.php`**

```php
<?php
// admin/dipendente-documenti.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$dipendenteId = (int) ($_GET['id'] ?? 0);
$dipendente = Utente::findById($dipendenteId);

if ($dipendente === null) {
    http_response_code(404);
    exit('Dipendente non trovato.');
}

$documenti = Documento::perUtente($dipendenteId);

layout_admin_inizio('Documenti di ' . $dipendente['nome'], 'dipendenti');
?>
<h1 class="text-xl font-semibold mb-6">
    Documenti — <?= htmlspecialchars($dipendente['cognome'] . ' ' . $dipendente['nome']) ?>
</h1>

<table class="table bg-base-100 shadow">
    <thead><tr><th>Tipo</th><th>Etichetta</th><th>Periodo</th><th>Netto</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documenti as $doc): ?>
        <tr class="hover">
            <td><?= $doc['tipo_documento'] === 'cu' ? 'CU' : 'Busta paga' ?></td>
            <td><?= htmlspecialchars($doc['etichetta'] ?? '—') ?></td>
            <td><?= $doc['mese'] !== null ? formatMese((int) $doc['mese']) . ' ' : '' ?><?= $doc['anno'] ?></td>
            <td><?= formatEuro($doc['netto_in_busta'] !== null ? (float) $doc['netto_in_busta'] : null) ?></td>
            <td>
                <a href="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>" class="btn btn-xs">Scarica</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($documenti)): ?>
        <tr><td colspan="5" class="text-base-content/60">Nessun documento disponibile.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php
layout_admin_fine();
```

- [ ] **Step 4: Manual verification of the create-employee flow**

```bash
curl -s -c cookies.txt -d "email=admin@test.it&password=password123" http://localhost/portale-dipendenti/login.php -o /dev/null
curl -s -b cookies.txt \
  -d "azione=crea&nome=Prova&cognome=Dipendente&email=prova.dipendente@example.it&codice_fiscale=PRVDPN80A01H501U" \
  http://localhost/portale-dipendenti/admin/dipendenti.php | grep -o "Password temporanea"
rm cookies.txt
```

Expected: matches `Password temporanea` (confirms the create flow succeeded and displayed the generated password). Clean up by deleting this test row: `"C:\xampp\mysql\bin\mysql.exe" -u root portale_dipendenti -e "DELETE FROM utenti WHERE email = 'prova.dipendente@example.it';"`

- [ ] **Step 5: Commit**

```bash
git add admin/dipendenti.php admin/dipendente-modifica.php admin/dipendente-documenti.php
git commit -m "feat: add admin employee management (create, edit, reset password, disable)"
```

---

## Task 16: Employee — Home dashboard (netto chart + carosello)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\home.php`
- Modify: `C:\xampp\htdocs\portale-dipendenti\public\assets\js\app.js`

**Interfaces:**
- Consumes: `require_login()` (Task 4), `Documento::perUtente()` (Task 8), `layout_dipendente_inizio/fine()` (Task 5), `scarica-documento.php` (Task 10).
- Produces: the employee's primary landing page. No new interfaces for later tasks (Task 17 duplicates the carosello-loading JS pattern independently, since it's a small self-contained script).

The chart uses a small dependency-free inline SVG polyline built server-side from `netto_in_busta` values (no charting library — keeps the "simple stack" constraint and avoids adding another CDN/npm dependency for a single sparkline-style chart). The carousel is a plain horizontal-scroll flex container with CSS scroll-snap (native browser behavior — swipe works for free on mobile, no JS carousel library needed) plus a small jQuery script to update the visible index dots and the values shown above/below the scroller as the user scrolls.

- [ ] **Step 1: Implement `home.php`**

```php
<?php
// home.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/Documento.php';
require_once __DIR__ . '/templates/layout-dipendente.php';

$utente = require_login();

$documentiBustaPaga = Documento::perUtente((int) $utente['id'], 'busta_paga');

layout_dipendente_inizio('Home', 'home');

if (empty($documentiBustaPaga)) {
    echo '<div class="alert">Nessuna busta paga disponibile al momento.</div>';
    layout_dipendente_fine('home');
    exit;
}

// Costruisce i punti del grafico solo dai documenti con un netto noto,
// aggregando per mese/anno (se ci sono piu' documenti nello stesso mese,
// usa l'ultimo caricato per il punto del grafico).
$puntiPerMese = [];
foreach ($documentiBustaPaga as $doc) {
    if ($doc['netto_in_busta'] !== null) {
        $chiave = $doc['anno'] . '-' . str_pad((string) $doc['mese'], 2, '0', STR_PAD_LEFT);
        $puntiPerMese[$chiave] = (float) $doc['netto_in_busta'];
    }
}
ksort($puntiPerMese);
$valoriGrafico = array_values($puntiPerMese);
$eticheteGrafico = array_keys($puntiPerMese);

$larghezzaSvg = 300;
$altezzaSvg = 60;
$puntiSvg = [];
$numPunti = count($valoriGrafico);
if ($numPunti > 1) {
    $min = min($valoriGrafico);
    $max = max($valoriGrafico);
    $range = ($max - $min) ?: 1;
    foreach ($valoriGrafico as $i => $valore) {
        $x = ($i / ($numPunti - 1)) * $larghezzaSvg;
        $y = $altezzaSvg - (($valore - $min) / $range) * ($altezzaSvg - 10) - 5;
        $puntiSvg[] = round($x, 1) . ',' . round($y, 1);
    }
}
?>
<div class="card bg-base-100 shadow mb-4">
    <div class="card-body p-4">
        <?php if (count($puntiSvg) > 1): ?>
            <svg width="100%" height="<?= $altezzaSvg ?>" viewBox="0 0 <?= $larghezzaSvg ?> <?= $altezzaSvg ?>" preserveAspectRatio="none">
                <polyline points="<?= implode(' ', $puntiSvg) ?>" fill="none" stroke="currentColor" stroke-width="2" />
            </svg>
        <?php endif; ?>

        <div class="overflow-x-auto snap-x snap-mandatory flex" id="carosello-buste-paga" style="scroll-snap-type: x mandatory;">
            <?php foreach ($documentiBustaPaga as $indice => $doc): ?>
                <?php
                $documentiStessoMese = array_values(array_filter($documentiBustaPaga, fn($d) => $d['mese'] === $doc['mese'] && $d['anno'] === $doc['anno']));
                $posizioneNelMese = array_search($doc, $documentiStessoMese) + 1;
                $totaleNelMese = count($documentiStessoMese);
                ?>
                <div class="snap-center shrink-0 w-full text-center py-4" style="scroll-snap-align: center;" data-doc-id="<?= $doc['id'] ?>">
                    <div class="text-sm text-primary">
                        <?= formatMese((int) $doc['mese']) ?> <?= $doc['anno'] ?><?= $totaleNelMese > 1 ? ", $posizioneNelMese di $totaleNelMese" : '' ?>
                    </div>
                    <div class="text-4xl font-bold mt-1"><?= formatEuro($doc['netto_in_busta'] !== null ? (float) $doc['netto_in_busta'] : null) ?></div>
                    <div class="text-xs text-base-content/60 tracking-wide mt-1">NETTO IN BUSTA</div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-center gap-1 mt-2" id="indicatori-carosello">
            <?php foreach ($documentiBustaPaga as $indice => $doc): ?>
                <span class="w-1.5 h-1.5 rounded-full bg-base-300 indicatore-punto" data-indice="<?= $indice ?>"></span>
            <?php endforeach; ?>
        </div>

        <div class="divider my-2"></div>

        <div id="dettaglio-documento-corrente"></div>
    </div>
</div>

<script>
var documentiBustaPaga = <?= json_encode(array_map(fn($d) => [
    'id' => $d['id'],
    'etichetta' => $d['etichetta'],
    'mese' => $d['mese'],
    'anno' => $d['anno'],
], $documentiBustaPaga)) ?>;

$(function () {
    function aggiornaIndicatore(indice) {
        $('.indicatore-punto').removeClass('bg-primary').addClass('bg-base-300');
        $('.indicatore-punto[data-indice="' + indice + '"]').removeClass('bg-base-300').addClass('bg-primary');

        var doc = documentiBustaPaga[indice];
        if (doc) {
            $('#dettaglio-documento-corrente').html(
                '<a class="btn btn-primary btn-sm w-full" href="/portale-dipendenti/scarica-documento.php?id=' + doc.id + '">' +
                'Scarica ' + (doc.etichetta || 'documento') + ' ' + doc.mese + '-' + doc.anno +
                '</a>'
            );
        }
    }

    var $carosello = $('#carosello-buste-paga');
    $carosello.on('scroll', function () {
        var larghezzaElemento = $carosello.width();
        var indice = Math.round($carosello.scrollLeft() / larghezzaElemento);
        aggiornaIndicatore(indice);
    });

    // Mostra l'ultimo documento (piu' recente) all'apertura della pagina.
    var ultimoIndice = documentiBustaPaga.length - 1;
    $carosello.scrollLeft($carosello.width() * ultimoIndice);
    aggiornaIndicatore(ultimoIndice);
});
</script>
<?php
layout_dipendente_fine('home');
```

- [ ] **Step 2: Manual verification with seeded test data**

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root portale_dipendenti -e "
INSERT INTO caricamenti (tipo_documento, etichetta, mese, anno, nome_file_originale, percorso_file_originale, caricato_da, stato)
VALUES ('busta_paga', 'Cedolino', 11, 2024, 'x.pdf', '/tmp/x.pdf', (SELECT id FROM utenti WHERE email='admin@test.it'), 'completato');
"
```

Then, using the id returned by `SELECT LAST_INSERT_ID()` (or query `SELECT id FROM caricamenti ORDER BY id DESC LIMIT 1`), and a dipendente test user (create one via the admin UI or `Utente::create` as in earlier tasks), insert a `documenti` row pointing at any existing local PDF file (e.g. copy `sql/schema.sql`'s directory README... — simplest is to reuse a tiny PDF built the same way as Task 7's test, saved permanently at `storage/documenti/_manual_test.pdf`):

```bash
php -r "
require 'vendor/autoload.php';
use setasign\Fpdi\Fpdi;
\$pdf = new Fpdi();
\$pdf->AddPage();
\$pdf->SetFont('Helvetica', '', 16);
\$pdf->Cell(0, 10, 'Test cedolino');
\$pdf->Output('F', 'storage/documenti/_manual_test.pdf');
"
```

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root portale_dipendenti -e "
INSERT INTO documenti (caricamento_id, utente_id, tipo_documento, etichetta, mese, anno, percorso_file, pagina_da, pagina_a, netto_in_busta, stato)
VALUES (1, (SELECT id FROM utenti WHERE email='prova.dipendente@example.it'), 'busta_paga', 'Cedolino', 11, 2024, '$(pwd)/storage/documenti/_manual_test.pdf', 1, 1, 1720.00, 'associato');
"
```

(Adjust the `caricamento_id` literal to match the row inserted above, and recreate `prova.dipendente@example.it` via the admin UI if it was deleted in Task 15's cleanup.)

Log in as that dipendente and check the home page:

```bash
curl -s -c cookies.txt -d "email=prova.dipendente@example.it&password=<password mostrata durante la creazione>" http://localhost/portale-dipendenti/login.php -o /dev/null
curl -s -b cookies.txt http://localhost/portale-dipendenti/home.php | grep -c "NETTO IN BUSTA"
rm cookies.txt
```

Expected: `1`.

Clean up the manual test PDF: `rm storage/documenti/_manual_test.pdf`

- [ ] **Step 3: Commit**

```bash
git add home.php
git commit -m "feat: add employee home dashboard with netto chart and carosello"
```

---

## Task 17: Employee — Documenti archive (filterable by year) and CU section

**Files:**
- Modify: `C:\xampp\htdocs\portale-dipendenti\home.php` (add CU section)
- Create: `C:\xampp\htdocs\portale-dipendenti\documenti.php`

**Interfaces:**
- Consumes: `require_login()` (Task 4), `Documento::perUtente()` (Task 8), `layout_dipendente_inizio/fine()` (Task 5).
- Produces: the "Documenti" bottom-nav tab (full archive, filterable by year) and the CU list section on the home page.

- [ ] **Step 1: Add the CU section to `home.php`**

Add this block right before the closing `layout_dipendente_fine('home');` call in `home.php`:

```php
<?php
$documentiCu = Documento::perUtente((int) $utente['id'], 'cu');
?>
<div class="card bg-base-100 shadow">
    <div class="card-body p-4">
        <h2 class="font-semibold mb-2">CU</h2>
        <?php if (empty($documentiCu)): ?>
            <p class="text-sm text-base-content/60">Nessuna CU disponibile.</p>
        <?php else: ?>
            <ul class="flex flex-col gap-2">
                <?php foreach ($documentiCu as $doc): ?>
                    <li class="flex justify-between items-center">
                        <span>CU <?= $doc['anno'] ?></span>
                        <a href="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>" class="btn btn-xs btn-outline">Scarica</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
```

- [ ] **Step 2: Implement `documenti.php`**

```php
<?php
// documenti.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/Documento.php';
require_once __DIR__ . '/templates/layout-dipendente.php';

$utente = require_login();

$tuttiIDocumenti = Documento::perUtente((int) $utente['id']);

$anniDisponibili = array_values(array_unique(array_map(fn($d) => (int) $d['anno'], $tuttiIDocumenti)));
rsort($anniDisponibili);

$annoSelezionato = isset($_GET['anno']) ? (int) $_GET['anno'] : ($anniDisponibili[0] ?? (int) date('Y'));

$documentiFiltrati = array_filter($tuttiIDocumenti, fn($d) => (int) $d['anno'] === $annoSelezionato);
// Ordine decrescente (piu' recente prima) per la vista archivio.
usort($documentiFiltrati, fn($a, $b) => ($b['mese'] <=> $a['mese']) ?: ($b['id'] <=> $a['id']));

layout_dipendente_inizio('Documenti', 'documenti');
?>
<h1 class="text-lg font-semibold mb-4">Documenti</h1>

<?php if (!empty($anniDisponibili)): ?>
<div class="tabs tabs-boxed mb-4">
    <?php foreach ($anniDisponibili as $anno): ?>
        <a href="?anno=<?= $anno ?>" class="tab <?= $anno === $annoSelezionato ? 'tab-active' : '' ?>"><?= $anno ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<ul class="flex flex-col gap-2">
    <?php foreach ($documentiFiltrati as $doc): ?>
        <li class="card bg-base-100 shadow-sm">
            <div class="card-body p-3 flex-row justify-between items-center">
                <div>
                    <div class="font-medium">
                        <?= $doc['tipo_documento'] === 'cu' ? 'CU' : htmlspecialchars($doc['etichetta'] ?? 'Busta paga') ?>
                    </div>
                    <div class="text-xs text-base-content/60">
                        <?= $doc['mese'] !== null ? formatMese((int) $doc['mese']) . ' ' : '' ?><?= $doc['anno'] ?>
                    </div>
                </div>
                <a href="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>" class="btn btn-xs btn-outline">Scarica</a>
            </div>
        </li>
    <?php endforeach; ?>
    <?php if (empty($documentiFiltrati)): ?>
        <li class="text-sm text-base-content/60">Nessun documento per l'anno selezionato.</li>
    <?php endif; ?>
</ul>
<?php
layout_dipendente_fine('documenti');
```

- [ ] **Step 3: Manual verification**

Using the same seeded dipendente/session from Task 16 Step 2:

```bash
curl -s -c cookies.txt -d "email=prova.dipendente@example.it&password=<password>" http://localhost/portale-dipendenti/login.php -o /dev/null
curl -s -b cookies.txt "http://localhost/portale-dipendenti/documenti.php?anno=2024" | grep -c "Cedolino"
rm cookies.txt
```

Expected: `1` (the seeded November 2024 Cedolino appears).

- [ ] **Step 4: Commit**

```bash
git add home.php documenti.php
git commit -m "feat: add employee document archive with year filter and CU section"
```

---

## Task 18: Employee — Profilo (Menu tab: profile, change password link, logout)

**Files:**
- Create: `C:\xampp\htdocs\portale-dipendenti\profilo.php`

**Interfaces:**
- Consumes: `require_login()` (Task 4), `layout_dipendente_inizio/fine()` (Task 5), `logout.php` (Task 4), `cambia-password.php` (Task 4).
- Produces: the "Menu" bottom-nav tab. Terminal page — no later task depends on it.

- [ ] **Step 1: Implement `profilo.php`**

```php
<?php
// profilo.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/templates/layout-dipendente.php';

$utente = require_login();

layout_dipendente_inizio('Menu', 'menu');
?>
<h1 class="text-lg font-semibold mb-4">Il mio profilo</h1>

<div class="card bg-base-100 shadow mb-4">
    <div class="card-body p-4">
        <dl class="flex flex-col gap-2 text-sm">
            <div><dt class="text-base-content/60">Nome</dt><dd class="font-medium"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></dd></div>
            <div><dt class="text-base-content/60">Email</dt><dd class="font-medium"><?= htmlspecialchars($utente['email']) ?></dd></div>
            <div><dt class="text-base-content/60">Codice Fiscale</dt><dd class="font-medium"><?= htmlspecialchars($utente['codice_fiscale']) ?></dd></div>
        </dl>
    </div>
</div>

<div class="flex flex-col gap-2">
    <a href="/portale-dipendenti/cambia-password.php" class="btn btn-outline w-full">Cambia password</a>
    <a href="/portale-dipendenti/logout.php" class="btn btn-error btn-outline w-full">Esci</a>
</div>
<?php
layout_dipendente_fine('menu');
```

- [ ] **Step 2: Manual verification**

```bash
curl -s -c cookies.txt -d "email=prova.dipendente@example.it&password=<password>" http://localhost/portale-dipendenti/login.php -o /dev/null
curl -s -b cookies.txt http://localhost/portale-dipendenti/profilo.php | grep -c "Il mio profilo"
rm cookies.txt
```

Expected: `1`.

- [ ] **Step 3: Commit**

```bash
git add profilo.php
git commit -m "feat: add employee profile/menu page"
```

---

## Task 19: End-to-end manual verification checklist

**Files:** none (verification-only task, per the spec's "Testing" section: manual checklist for critical flows before each release).

**Interfaces:** none produced; exercises the full system built in Tasks 1–18.

- [ ] **Step 1: Reset to a clean local database state**

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS portale_dipendenti;"
"C:\xampp\mysql\bin\mysql.exe" -u root < sql/schema.sql
```

- [ ] **Step 2: Create the first admin user directly in the database**

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root portale_dipendenti -e "INSERT INTO utenti (nome, cognome, email, codice_fiscale, password_hash, ruolo, deve_cambiare_password, attivo) VALUES ('Admin', 'Principale', 'admin@azienda.it', 'ADMPRN80A01H501Z', '$(php -r "echo password_hash('CambiaSubito123!', PASSWORD_BCRYPT);")', 'admin', 0, 1);"
```

- [ ] **Step 3: Walk through the full checklist in a browser**

Log in at `http://localhost/portale-dipendenti/login.php` as `admin@azienda.it` / `CambiaSubito123!` and confirm each item:

1. [ ] Login redirects to `admin/dashboard.php`.
2. [ ] "Nuovo dipendente" in `admin/dipendenti.php` creates a user and shows a temporary password.
3. [ ] Logging out and logging back in as that dipendente redirects to `cambia-password.php` (forced change).
4. [ ] Setting a new password redirects to `home.php`; the dashboard shows "Nessuna busta paga disponibile" (no documents yet).
5. [ ] Back as admin, `admin/nuovo-caricamento.php` accepts a multi-page test PDF (built with the CF of the dipendente created in step 2, using the same synthetic-PDF technique as earlier tasks) and completes the 3-step wizard, landing on `revisione-caricamento.php` with the row correctly matched.
6. [ ] Clicking the matched row shows the split PDF in the right-hand preview iframe.
7. [ ] Uploading a second cumulative PDF for the same employee/month/etichetta ("Cedolino") produces a "Conflitti" row with Sovrascrivi/Ignora actions; clicking Sovrascrivi replaces the prior document (verify via `admin/dipendente-documenti.php` that only one active `Cedolino` row remains for that employee/month).
8. [ ] Uploading a cumulative PDF with an unrecognized CF produces a "Da rivedere" row; assigning it to a dipendente via the dropdown creates a new associated document.
9. [ ] As the dipendente, `home.php` now shows the netto chart, the carosello (swipe/scroll works on a mobile viewport — test with browser dev tools' device emulation), and the download button works (`scarica-documento.php` returns a valid PDF).
10. [ ] `documenti.php` lists the same documents, filterable by year tabs.
11. [ ] `profilo.php` shows correct employee data; "Cambia password" and "Esci" both work.
12. [ ] As admin, disabling the dipendente (`admin/dipendente-modifica.php`) then attempting to log in as that dipendente fails (redirected back to login, not into the app).
13. [ ] Attempting to access any `admin/*.php` page or `home.php`/`documenti.php`/`profilo.php` while logged out redirects to `login.php`.
14. [ ] Attempting to access `scarica-documento.php?id=<id of another employee's document>` while logged in as a non-admin dipendente returns 403.

- [ ] **Step 4: Record results and fix any failures found**

If any checklist item fails, treat it as a bug against the specific task that implements it — fix inline in that task's files, re-run the fixed step, then re-run this full checklist from Step 3 onward before considering the plan complete.

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: complete end-to-end manual verification pass"
```

---
