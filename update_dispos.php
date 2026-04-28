<?php
// ══════════════════════════════════════════════════
//  METTRE À JOUR LES DISPONIBILITÉS DU MÉDECIN
// ══════════════════════════════════════════════════
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'medecin') {
    header('Location: ../index.php'); exit;
}
require_once 'config.php';
$db  = getDB();
$mid = $_SESSION['user_id'];

$jours_coches = $_POST['jours'] ?? [];
$all_jours = ['lundi','mardi','mercredi','jeudi','vendredi','samedi'];

foreach ($all_jours as $jour) {
    $dispo_id = intval($_POST['dispo_id'][$jour] ?? 0);
    $checked  = in_array($jour, $jours_coches);
    $debut    = $_POST['debut'][$jour] ?? '08:00';
    $fin      = $_POST['fin'][$jour]   ?? '17:00';

    if ($checked) {
        if ($dispo_id) {
            // Mise à jour
            $db->prepare("UPDATE disponibilites SET heure_debut=?, heure_fin=?, actif=1 WHERE id=? AND medecin_id=?")
               ->execute([$debut, $fin, $dispo_id, $mid]);
        } else {
            // Insertion — colonne correcte : "jour" (pas "jour_semaine")
            $db->prepare("INSERT INTO disponibilites (medecin_id, jour, heure_debut, heure_fin, actif) VALUES (?,?,?,?,1)")
               ->execute([$mid, $jour, $debut, $fin]);
        }
    } else {
        if ($dispo_id) {
            // Désactivation
            $db->prepare("UPDATE disponibilites SET actif=0 WHERE id=? AND medecin_id=?")
               ->execute([$dispo_id, $mid]);
        }
    }
}

header('Location: ../index.php');
exit;
