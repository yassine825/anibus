<?php
// ══════════════════════════════════════════════════
//  METTRE À JOUR LE PROFIL MÉDECIN
// ══════════════════════════════════════════════════
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'medecin') {
    header('Location: ../index.php'); exit;
}
require_once 'config.php';
$db  = getDB();
$mid = $_SESSION['user_id'];

$prenom    = trim($_POST['prenom']      ?? '');
$nom       = trim($_POST['nom']         ?? '');
$telephone = trim($_POST['telephone']   ?? '');
$adresse   = trim($_POST['adresse']     ?? '');
$tarif     = floatval($_POST['tarif']   ?? 0);
// Colonne correcte : "description" (pas "bio")
$description = trim($_POST['description'] ?? $_POST['bio'] ?? '');

$db->prepare("UPDATE medecins SET prenom=?, nom=?, telephone=?, adresse=?, tarif=?, description=? WHERE id=?")
   ->execute([$prenom, $nom, $telephone, $adresse, $tarif, $description, $mid]);

$_SESSION['prenom'] = $prenom;
$_SESSION['nom']    = $nom;

header('Location: ../index.php');
exit;
