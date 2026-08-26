<?php
// src/auth.php
require_once __DIR__ . '/Utente.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    // 'secure' e' condizionato (non semplicemente true) per non rompere lo
    // sviluppo locale su HTTP: PHP scarta silenziosamente un cookie 'secure'
    // se la richiesta non e' HTTPS, quindi su XAMMP in locale la sessione
    // non si salverebbe affatto. In produzione (HTTPS) il rilevamento sotto
    // lo attiva automaticamente — nessuna modifica da fare al deploy.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? null) == 443
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); // dietro reverse proxy/load balancer

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure' => $isHttps,
    ]);
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

    $paginaCorrente = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $consentiteConCambioPassword = ['cambia-password.php', 'logout.php'];

    if ($utente['deve_cambiare_password'] && !in_array($paginaCorrente, $consentiteConCambioPassword, true)) {
        redirect('/portale-dipendenti/cambia-password.php');
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
