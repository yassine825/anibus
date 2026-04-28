<?php
// ══════════════════════════════════════════════════
//  RÉSERVATION D'UN RENDEZ-VOUS (formulaire HTML legacy)
//  Note : l'app principale utilise rendez_vous.php via AJAX
// ══════════════════════════════════════════════════
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../index.php'); exit;
}
require_once 'config.php';
$db = getDB();

$patient_id  = $_SESSION['user_id'];
$medecin_id  = intval($_POST['medecin_id'] ?? 0);
$date_rdv    = $_POST['date_rdv']  ?? '';
$heure_rdv   = $_POST['heure_rdv'] ?? '';
$motif       = trim($_POST['motif'] ?? 'Consultation');

if (!$medecin_id || !$date_rdv || !$heure_rdv) {
    header('Location: ../index.php?error=invalid'); exit;
}

// Vérifier que le créneau est libre
$stmt = $db->prepare("SELECT id FROM rendez_vous WHERE medecin_id=? AND date_rdv=? AND heure_rdv=? AND statut != 'annule'");
$stmt->execute([$medecin_id, $date_rdv, $heure_rdv]);
if ($stmt->fetch()) {
    header('Location: ../index.php?error=taken'); exit;
}

// Insérer le rendez-vous
$ins = $db->prepare("INSERT INTO rendez_vous (patient_id, medecin_id, date_rdv, heure_rdv, motif, statut) VALUES (?,?,?,?,?,'en_attente')");
$ins->execute([$patient_id, $medecin_id, $date_rdv, $heure_rdv, $motif]);

header('Location: ../index.php?success=booked');
exit;
