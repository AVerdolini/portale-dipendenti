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
