<?php
// logout.php
require_once __DIR__ . '/src/auth.php';
logout_utente();
redirect('/login.php');
