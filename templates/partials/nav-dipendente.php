<?php
/** @var string $paginaAttiva */
function classe_nav_dipendente(string $voce, string $paginaAttiva): string
{
    return $voce === $paginaAttiva ? 'text-primary' : 'text-base-content/60';
}
?>
<div class="btm-nav border-t bg-base-100">
    <a href="/home.php" class="<?= classe_nav_dipendente('home', $paginaAttiva) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 11.5 12 4l8 7.5" />
            <path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9" />
        </svg>
        <span class="btm-nav-label text-xs">Home</span>
    </a>
    <a href="/documenti.php" class="<?= classe_nav_dipendente('documenti', $paginaAttiva) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 4h6l1 2h3a1 1 0 0 1 1 1v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a1 1 0 0 1 1-1h3z" />
            <path d="M9 12h6" />
            <path d="M9 16h4" />
        </svg>
        <span class="btm-nav-label text-xs">Documenti</span>
    </a>
    <a href="/profilo.php" class="<?= classe_nav_dipendente('menu', $paginaAttiva) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="3.5" />
            <path d="M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6" />
        </svg>
        <span class="btm-nav-label text-xs">Menu</span>
    </a>
</div>
