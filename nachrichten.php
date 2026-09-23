<?php
/**
 * Nachrichten - interner Chat zwischen Benutzern
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/nachrichten_functions.php';

requireLogin();

$benutzerId = $_SESSION['benutzer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'senden') {
    $anBenutzerId = (int) $_POST['an_benutzer_id'];
    sendeNachricht($benutzerId, $anBenutzerId, $_POST['inhalt'] ?? '');
    header('Location: nachrichten.php?mit=' . $anBenutzerId);
    exit;
}

$partnerId = isset($_GET['mit']) ? (int) $_GET['mit'] : null;
if ($partnerId) {
    markiereNachrichtenGelesen($benutzerId, $partnerId);
}

$uebersicht = getNachrichtenUebersicht($benutzerId);
$verlauf = $partnerId ? getNachrichtenVerlauf($benutzerId, $partnerId) : [];
$aktuellerPartner = null;
foreach ($uebersicht as $u) {
    if ((int) $u['id'] === $partnerId) {
        $aktuellerPartner = $u;
        break;
    }
}
$pageTitle = 'Nachrichten';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Buchhaltung</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .nachrichten-liste { max-height: 70vh; overflow-y: auto; }
        .nachrichten-verlauf { height: 55vh; overflow-y: auto; display: flex; flex-direction: column; }
        .nachricht-blase { max-width: 75%; padding: .5rem .75rem; border-radius: .75rem; margin-bottom: .5rem; }
        .nachricht-eigene { align-self: flex-end; background: #0d6efd; color: #fff; }
        .nachricht-fremde { align-self: flex-start; background: #f1f1f1; }
        .nachricht-zeit { font-size: .7rem; opacity: .7; display: block; margin-top: .15rem; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-chat-dots me-2"></i><?= $pageTitle ?></h1>
                </div>

                <?php displayFlashMessage(); ?>

                <div class="row">
                    <div class="col-md-4 col-lg-3 mb-3">
                        <div class="card">
                            <div class="card-header">Unterhaltungen</div>
                            <div class="list-group list-group-flush nachrichten-liste">
                                <?php if (empty($uebersicht)): ?>
                                <div class="list-group-item text-muted small">Keine anderen Benutzer vorhanden.</div>
                                <?php endif; ?>
                                <?php foreach ($uebersicht as $u): ?>
                                <a href="nachrichten.php?mit=<?= $u['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $partnerId === (int) $u['id'] ? 'active' : '' ?>">
                                    <span><?= htmlspecialchars($u['vorname'] ? trim($u['vorname'] . ' ' . $u['nachname']) : $u['benutzername']) ?></span>
                                    <?php if ($u['ungelesen'] > 0): ?>
                                    <span class="badge bg-danger rounded-pill"><?= $u['ungelesen'] ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8 col-lg-9">
                        <div class="card">
                            <?php if (!$aktuellerPartner): ?>
                            <div class="card-body text-muted text-center py-5">
                                Bitte links eine Unterhaltung auswählen.
                            </div>
                            <?php else: ?>
                            <div class="card-header">
                                <?= htmlspecialchars($aktuellerPartner['vorname'] ? trim($aktuellerPartner['vorname'] . ' ' . $aktuellerPartner['nachname']) : $aktuellerPartner['benutzername']) ?>
                            </div>
                            <div class="card-body">
                                <div class="nachrichten-verlauf mb-3" id="verlauf">
                                    <?php foreach ($verlauf as $n): ?>
                                    <div class="nachricht-blase <?= (int) $n['von_benutzer_id'] === (int) $benutzerId ? 'nachricht-eigene' : 'nachricht-fremde' ?>">
                                        <?= nl2br(htmlspecialchars($n['inhalt'])) ?>
                                        <span class="nachricht-zeit"><?= date('d.m.Y H:i', strtotime($n['created_at'])) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="senden">
                                    <input type="hidden" name="an_benutzer_id" value="<?= $aktuellerPartner['id'] ?>">
                                    <textarea name="inhalt" class="form-control" rows="1" placeholder="Nachricht schreiben..." required onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const verlauf = document.getElementById('verlauf');
        if (verlauf) { verlauf.scrollTop = verlauf.scrollHeight; }
    </script>
</body>
</html>
