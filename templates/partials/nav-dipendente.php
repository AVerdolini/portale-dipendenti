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
