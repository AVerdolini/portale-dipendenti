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
