<?php
// ══════════════════════════════════════════════════
//  CONFIRMER UN RENDEZ-VOUS (médecin) - formulaire HTML legacy
//  Note : l'app principale utilise rendez_vous.php via AJAX
// ══════════════════════════════════════════════════
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'medecin') {
    header('Location: ../index.php'); exit;
}
require_once 'config.php';
$db = getDB();
$id  = intval($_GET['id'] ?? 0);
$mid = $_SESSION['user_id'];

$stmt = $db->prepare("UPDATE rendez_vous SET statut='confirme' WHERE id=? AND medecin_id=?");
$stmt->execute([$id, $mid]);

header('Location: ../index.php?success=confirmed');
exit;
