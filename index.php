<?php
// index.php
require_once __DIR__ . '/src/auth.php';

$utente = current_user();

if ($utente === null) {
    redirect('/login.php');
}

if ($utente['deve_cambiare_password']) {
    redirect('/cambia-password.php');
}

if ($utente['ruolo'] === 'admin') {
    redirect('/admin/dashboard.php');
} else {
    redirect('/home.php');
}
