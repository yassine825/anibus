<?php
// ══════════════════════════════════════════════════
//  ANNULER UN RENDEZ-VOUS (formulaire HTML legacy)
//  Note : l'app principale utilise rendez_vous.php via AJAX
// ══════════════════════════════════════════════════
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php'); exit;
}
require_once 'config.php';
$db   = getDB();
$id   = intval($_GET['id'] ?? 0);
$role = $_SESSION['role'];

if ($role === 'patient') {
    $stmt = $db->prepare("UPDATE rendez_vous SET statut='annule' WHERE id=? AND patient_id=?");
    $stmt->execute([$id, $_SESSION['user_id']]);
} else {
    $stmt = $db->prepare("UPDATE rendez_vous SET statut='annule' WHERE id=? AND medecin_id=?");
    $stmt->execute([$id, $_SESSION['user_id']]);
}

header('Location: ../index.php?success=cancelled');
exit;
