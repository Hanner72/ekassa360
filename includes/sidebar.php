<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3 sidebar-sticky">
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'index' ? 'active' : '' ?>" href="index.php">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'rechnungen' ? 'active' : '' ?>" href="rechnungen.php">
                    <i class="bi bi-receipt me-2"></i>Rechnungen
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase">
            <span>Steuern</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'ust_voranmeldung' ? 'active' : '' ?>" href="ust_voranmeldung.php">
                    <i class="bi bi-file-earmark-text me-2"></i>USt-Voranmeldung (U30)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'einkommensteuer' ? 'active' : '' ?>" href="einkommensteuer.php">
                    <i class="bi bi-file-earmark-ruled me-2"></i>Einkommensteuer (E1a)
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase">
            <span>Anlagen</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'anlagegueter' ? 'active' : '' ?>" href="anlagegueter.php">
                    <i class="bi bi-building me-2"></i>Anlagegüter
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'abschreibungen' ? 'active' : '' ?>" href="abschreibungen.php">
                    <i class="bi bi-graph-down me-2"></i>Abschreibungen (AfA)
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase">
            <span>Berichte</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'auswertungen' ? 'active' : '' ?>" href="auswertungen.php">
                    <i class="bi bi-bar-chart-line me-2"></i>Auswertungen
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase">
            <span>System</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'import' ? 'active' : '' ?>" href="import.php">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel-Import
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'kategorien' ? 'active' : '' ?>" href="kategorien.php">
                    <i class="bi bi-tags me-2"></i>Kategorien
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'einstellungen' ? 'active' : '' ?>" href="einstellungen.php">
                    <i class="bi bi-gear me-2"></i>Einstellungen
                </a>
            </li>
            <?php if (isAdmin()): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'benutzerverwaltung' ? 'active' : '' ?>" href="benutzerverwaltung.php">
                    <i class="bi bi-people me-2"></i>Benutzerverwaltung
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'protokoll' ? 'active' : '' ?>" href="protokoll.php">
                    <i class="bi bi-journal-text me-2"></i>Änderungsprotokoll
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
