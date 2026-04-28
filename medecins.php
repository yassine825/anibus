<?php
// ============================================================
//  MediRDV — API Médecins
//  GET  /php/medecins.php                        liste tous
//  GET  /php/medecins.php?id=5                   un médecin
//  GET  /php/medecins.php?specialite=Cardiologue filtrer
//  GET  /php/medecins.php?ville=Tunis
//  GET  /php/medecins.php?search=mansour
//  GET  /php/medecins.php?action=specialites     liste spécialités
//  GET  /php/medecins.php?action=disponibilites&medecin_id=1&date=2026-05-10
//  PUT  /php/medecins.php?action=update_profil   (médecin connecté)
// ============================================================
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$db     = getDB();
$action = $_GET['action'] ?? '';

// ── LISTE DES SPÉCIALITÉS ───────────────────────────────────
if ($action === 'specialites') {
    $rows = $db->query("SELECT DISTINCT specialite FROM medecins ORDER BY specialite")->fetchAll();
    jsonResponse(['success'=>true,'specialites'=>array_column($rows,'specialite')]);
}

// ── CRÉNEAUX DISPONIBLES POUR UNE DATE ─────────────────────
if ($action === 'disponibilites') {
    $medecin_id = intval($_GET['medecin_id'] ?? 0);
    $date       = sanitize($_GET['date'] ?? '');
    if (!$medecin_id || !$date) jsonResponse(['success'=>false,'error'=>'Paramètres manquants']);

    $ts   = strtotime($date);
    $jours_fr = ['sunday'=>'dimanche','monday'=>'lundi','tuesday'=>'mardi','wednesday'=>'mercredi',
                 'thursday'=>'jeudi','friday'=>'vendredi','saturday'=>'samedi'];
    $jour = $jours_fr[strtolower(date('l', $ts))];

    // Plages horaires du médecin pour ce jour
    $dispo = $db->prepare("SELECT heure_debut, heure_fin FROM disponibilites WHERE medecin_id=? AND jour=? AND actif=1");
    $dispo->execute([$medecin_id, $jour]);
    $plages = $dispo->fetchAll();

    // Créneaux déjà pris
    $pris = $db->prepare("SELECT TIME_FORMAT(heure_rdv, '%H:%i') AS heure_rdv FROM rendez_vous WHERE medecin_id=? AND date_rdv=? AND statut != 'annule'");
    $pris->execute([$medecin_id, $date]);
    $heures_prises = array_column($pris->fetchAll(), 'heure_rdv');

    // Générer créneaux de 30 min
    $slots = [];
    foreach ($plages as $p) {
        $cur  = strtotime($p['heure_debut']);
        $fin  = strtotime($p['heure_fin']);
        while ($cur < $fin) {
            $h = date('H:i', $cur);
            $slots[] = ['heure'=>$h, 'disponible'=>!in_array($h, $heures_prises)];
            $cur += 1800; // +30 min
        }
    }
    jsonResponse(['success'=>true,'date'=>$date,'jour'=>$jour,'slots'=>$slots]);
}

// ── MISE À JOUR PROFIL MÉDECIN ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $action === 'update_profil') {
    authRequired('medecin');
    $data    = json_decode(file_get_contents('php://input'), true) ?? [];
    $fields  = [];
    $params  = [];
    $allowed = ['telephone','adresse','tarif','description','disponible'];
    foreach ($allowed as $f) {
        if (isset($data[$f])) { $fields[] = "$f=?"; $params[] = sanitize($data[$f]); }
    }
    if (empty($fields)) jsonResponse(['success'=>false,'error'=>'Aucun champ à modifier']);
    $params[] = $_SESSION['user_id'];
    $db->prepare("UPDATE medecins SET ".implode(',',$fields)." WHERE id=?")->execute($params);
    jsonResponse(['success'=>true,'message'=>'Profil mis à jour']);
}

// ── RÉCUPÉRER UN MÉDECIN PAR ID ─────────────────────────────
if (isset($_GET['id'])) {
    $id   = intval($_GET['id']);
    $stmt = $db->prepare("SELECT id,prenom,nom,email,telephone,specialite,ville,adresse,tarif,description,note_moyenne,nb_avis,disponible FROM medecins WHERE id=?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) jsonResponse(['success'=>false,'error'=>'Médecin introuvable'], 404);

    // Récupérer ses disponibilités
    $d = $db->prepare("SELECT jour,heure_debut,heure_fin FROM disponibilites WHERE medecin_id=? AND actif=1 ORDER BY FIELD(jour,'lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche')");
    $d->execute([$id]);
    $doc['disponibilites'] = $d->fetchAll();
    jsonResponse(['success'=>true,'medecin'=>$doc]);
}

// ── LISTE / RECHERCHE MÉDECINS ──────────────────────────────
$where  = ['1=1'];
$params = [];

if (!empty($_GET['specialite'])) { $where[] = 'specialite=?'; $params[] = sanitize($_GET['specialite']); }
if (!empty($_GET['ville']))      { $where[] = 'ville=?';      $params[] = sanitize($_GET['ville']); }
if (!empty($_GET['search'])) {
    $s = '%'.sanitize($_GET['search']).'%';
    $where[] = '(prenom LIKE ? OR nom LIKE ? OR specialite LIKE ? OR ville LIKE ?)';
    $params  = array_merge($params, [$s,$s,$s,$s]);
}

$sql  = "SELECT id,prenom,nom,specialite,ville,adresse,tarif,note_moyenne,nb_avis,disponible FROM medecins WHERE ".implode(' AND ',$where)." ORDER BY note_moyenne DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$medecins = $stmt->fetchAll();

jsonResponse(['success'=>true,'count'=>count($medecins),'medecins'=>$medecins]);
