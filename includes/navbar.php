<?php
$firma = getFirmendaten();
$currentYear = date('Y');
?>
<nav class="navbar sticky-top flex-md-nowrap p-0 shadow-lg" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #1e3c72 100%);">
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
    
    <!-- Rechte Seite: Jahr + Einstellungen -->
    <div class="navbar-nav flex-row">
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
    </div>
    
    <!-- Mobile Toggle -->
    <button class="navbar-toggler border-0 px-3 py-2 d-md-none text-white" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
        <i class="bi bi-list fs-4"></i>
    </button>
</nav>
