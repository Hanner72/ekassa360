<?php
$firma = getFirmendaten();
$currentYear = date('Y');
$currentUser = getCurrentUser();
?>
<nav class="navbar sticky-top flex-md-nowrap p-0 shadow-lg" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #1e3c72 100%); z-index: 1030;">
    <!-- Logo & Brand -->
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 py-2 d-flex align-items-center" href="index.php" style="background: rgba(0,0,0,0.2);">
        <div class="d-flex align-items-center">
            <div class="bg-white p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="bi bi-cash-stack text-primary fs-5"></i>
            </div>
            <div class="text-white">
                <span class="fw-bold fs-5">EKassa</span><span class="fw-light fs-5">360</span>
                <div class="text-white-50" style="font-size: 0.65rem; margin-top: -3px;">Buchhaltung AT</div>
            </div>
        </div>
    </a>
    
    <!-- Firmenname in der Mitte -->
    <div class="d-none d-md-flex flex-grow-1 justify-content-center">
        <?php if (!empty($firma['name'])): ?>
        <div class="d-flex align-items-center text-white px-4 py-2" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
            <i class="bi bi-building me-2 text-warning"></i>
            <span class="fw-semibold"><?= htmlspecialchars($firma['name']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Rechte Seite: Jahr + Benutzer + Logout -->
    <div class="navbar-nav flex-row align-items-center">
        <!-- Aktuelles Jahr Badge -->
        <div class="nav-item d-none d-md-flex align-items-center me-2">
            <span class="badge px-3 py-2" style="background: rgba(255,255,255,0.15); color: #fff;">
                <i class="bi bi-calendar3 me-1"></i><?= $currentYear ?>
            </span>
        </div>
        
        <!-- Einstellungen Button -->
        <a class="nav-link px-3 py-2 d-flex align-items-center text-white" href="einstellungen.php" 
           style="transition: all 0.3s ease;" 
           onmouseover="this.style.background='rgba(255,255,255,0.1)'" 
           onmouseout="this.style.background='transparent'">
            <i class="bi bi-gear-fill me-1"></i>
            <span class="d-none d-lg-inline">Einstellungen</span>
        </a>
        
        <!-- Benutzer-Dropdown -->
        <?php if ($currentUser): ?>
        <div class="nav-item dropdown" style="position: relative;">
            <a class="nav-link px-3 py-2 d-flex align-items-center text-white dropdown-toggle" href="#" 
               role="button" data-bs-toggle="dropdown" aria-expanded="false"
               style="transition: all 0.3s ease;">
                <i class="bi bi-person-circle me-1"></i>
                <span class="d-none d-lg-inline"><?= htmlspecialchars($currentUser['benutzername']) ?></span>
                <?php if ($currentUser['rolle'] === 'admin'): ?>
                    <span class="badge bg-warning text-dark ms-1 d-none d-xl-inline" style="font-size: 0.65rem;">Admin</span>
                <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="border-radius: 0; position: absolute; z-index: 1050;">
                <li>
                    <span class="dropdown-item-text text-muted small">
                        Angemeldet als<br>
                        <strong><?= htmlspecialchars(getCurrentUserName()) ?></strong>
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="passwort_aendern.php">
                        <i class="bi bi-key me-2"></i>Passwort ändern
                    </a>
                </li>
                <?php if (isAdmin()): ?>
                <li>
                    <a class="dropdown-item" href="benutzerverwaltung.php">
                        <i class="bi bi-people me-2"></i>Benutzerverwaltung
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="protokoll.php">
                        <i class="bi bi-journal-text me-2"></i>Änderungsprotokoll
                    </a>
                </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Abmelden
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Mobile Toggle -->
    <button class="navbar-toggler border-0 px-3 py-2 d-md-none text-white" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
        <i class="bi bi-list fs-4"></i>
    </button>
</nav>
