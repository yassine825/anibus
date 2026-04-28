<?php
// ============================================================
//  MediRDV — API Authentification
//  POST /php/auth.php?action=register_patient
//  POST /php/auth.php?action=register_medecin
//  POST /php/auth.php?action=login
//  POST /php/auth.php?action=logout
//  GET  /php/auth.php?action=session
// ============================================================
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$action = $_GET['action'] ?? '';
$data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($action) {

    // ── INSCRIPTION PATIENT ─────────────────────────────────
    case 'register_patient':
        $prenom    = sanitize($data['prenom'] ?? '');
        $nom       = sanitize($data['nom'] ?? '');
        $email     = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $tel       = sanitize($data['telephone'] ?? '');
        $pwd       = $data['mot_de_passe'] ?? '';
        $naissance = sanitize($data['date_naissance'] ?? '');
        $sexe      = sanitize($data['sexe'] ?? '');

        if (!$prenom || !$nom || !$email || !$pwd)
            jsonResponse(['success'=>false,'error'=>'Champs obligatoires manquants']);
        if (strlen($pwd) < 6)
            jsonResponse(['success'=>false,'error'=>'Mot de passe trop court (min 6 caractères)']);

        $db = getDB();
        $exists = $db->prepare("SELECT id FROM patients WHERE email=?");
        $exists->execute([$email]);
        if ($exists->fetch())
            jsonResponse(['success'=>false,'error'=>'Cet email est déjà utilisé']);

        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO patients (prenom,nom,email,mot_de_passe,telephone,date_naissance,sexe) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$prenom,$nom,$email,$hash,$tel,$naissance?:null,$sexe?:null]);
        $id = $db->lastInsertId();

        $_SESSION['user_id'] = $id;
        $_SESSION['role']    = 'patient';
        $_SESSION['prenom']  = $prenom;
        $_SESSION['nom']     = $nom;
        jsonResponse(['success'=>true,'role'=>'patient','user'=>['id'=>$id,'prenom'=>$prenom,'nom'=>$nom,'email'=>$email,'telephone'=>$tel]]);

    // ── INSCRIPTION MÉDECIN ─────────────────────────────────
    case 'register_medecin':
        $prenom     = sanitize($data['prenom'] ?? '');
        $nom        = sanitize($data['nom'] ?? '');
        $email      = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $tel        = sanitize($data['telephone'] ?? '');
        $pwd        = $data['mot_de_passe'] ?? '';
        $spec       = sanitize($data['specialite'] ?? '');
        $ville      = sanitize($data['ville'] ?? '');
        $adresse    = sanitize($data['adresse'] ?? '');
        $tarif      = floatval($data['tarif'] ?? 0);
        $desc       = sanitize($data['description'] ?? '');

        if (!$prenom || !$nom || !$email || !$pwd || !$spec || !$ville)
            jsonResponse(['success'=>false,'error'=>'Champs obligatoires manquants']);
        if (strlen($pwd) < 6)
            jsonResponse(['success'=>false,'error'=>'Mot de passe trop court (min 6 caractères)']);

        $db = getDB();
        $exists = $db->prepare("SELECT id FROM medecins WHERE email=?");
        $exists->execute([$email]);
        if ($exists->fetch())
            jsonResponse(['success'=>false,'error'=>'Cet email est déjà utilisé']);

        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO medecins (prenom,nom,email,mot_de_passe,telephone,specialite,ville,adresse,tarif,description) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$prenom,$nom,$email,$hash,$tel,$spec,$ville,$adresse,$tarif,$desc]);
        $id = $db->lastInsertId();

        // Disponibilités par défaut Lun-Ven 08h-12h et 14h-18h
        $jours = ['lundi','mardi','mercredi','jeudi','vendredi'];
        $sd = $db->prepare("INSERT INTO disponibilites (medecin_id,jour,heure_debut,heure_fin) VALUES (?,?,?,?)");
        foreach ($jours as $j) {
            $sd->execute([$id,$j,'08:00:00','12:00:00']);
            $sd->execute([$id,$j,'14:00:00','18:00:00']);
        }

        $_SESSION['user_id'] = $id;
        $_SESSION['role']    = 'medecin';
        $_SESSION['prenom']  = $prenom;
        $_SESSION['nom']     = $nom;
        jsonResponse(['success'=>true,'role'=>'medecin','user'=>['id'=>$id,'prenom'=>$prenom,'nom'=>$nom,'email'=>$email,'telephone'=>$tel,'specialite'=>$spec,'ville'=>$ville,'adresse'=>$adresse,'tarif'=>$tarif,'description'=>$desc]]);

    // ── CONNEXION ───────────────────────────────────────────
    case 'login':
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $pwd   = $data['mot_de_passe'] ?? '';
        $role  = sanitize($data['role'] ?? 'patient');

        if (!$email || !$pwd)
            jsonResponse(['success'=>false,'error'=>'Email et mot de passe requis']);

        $db    = getDB();
        $table = ($role === 'medecin') ? 'medecins' : 'patients';
        $stmt  = $db->prepare("SELECT * FROM $table WHERE email=?");
        $stmt->execute([$email]);
        $user  = $stmt->fetch();

        if (!$user || !password_verify($pwd, $user['mot_de_passe']))
            jsonResponse(['success'=>false,'error'=>'Email ou mot de passe incorrect']);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $role;
        $_SESSION['prenom']  = $user['prenom'];
        $_SESSION['nom']     = $user['nom'];

        unset($user['mot_de_passe']);
        jsonResponse(['success'=>true,'role'=>$role,'user'=>$user]);

    // ── DÉCONNEXION ─────────────────────────────────────────
    case 'logout':
        $_SESSION = [];
        session_destroy();
        jsonResponse(['success'=>true]);

    // ── VÉRIFIER SESSION ────────────────────────────────────
    case 'session':
        if (empty($_SESSION['user_id'])) {
            jsonResponse(['success'=>false,'logged'=>false]);
        }
        $db    = getDB();
        $table = ($_SESSION['role'] === 'medecin') ? 'medecins' : 'patients';
        $stmt  = $db->prepare("SELECT * FROM $table WHERE id=?");
        $stmt->execute([$_SESSION['user_id']]);
        $user  = $stmt->fetch();
        if (!$user) { session_destroy(); jsonResponse(['success'=>false,'logged'=>false]); }
        unset($user['mot_de_passe']);
        jsonResponse(['success'=>true,'logged'=>true,'role'=>$_SESSION['role'],'user'=>$user]);

    default:
        jsonResponse(['success'=>false,'error'=>'Action inconnue'], 404);
}
