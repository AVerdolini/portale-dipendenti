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
