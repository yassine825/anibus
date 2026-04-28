<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

authRequired();

$db     = getDB();
$role   = $_SESSION['role'];
$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/*==================================================
=            CRÉER UN RENDEZ-VOUS                 =
==================================================*/
if ($method === 'POST') {

    authRequired('patient');

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $medecin_id = intval($data['medecin_id'] ?? 0);
    $date       = sanitize($data['date_rdv'] ?? '');
    $heure      = sanitize($data['heure_rdv'] ?? '');
    $motif      = sanitize($data['motif'] ?? 'Consultation');

    if (!$medecin_id || !$date || !$heure) {
        jsonResponse([
            'success' => false,
            'error'   => 'Médecin, date et heure requis'
        ]);
    }

    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        jsonResponse([
            'success' => false,
            'error'   => 'La date doit être dans le futur'
        ]);
    }

    $check = $db->prepare("
        SELECT id
        FROM rendez_vous
        WHERE medecin_id = ?
        AND date_rdv = ?
        AND heure_rdv = ?
        AND statut != 'annule'
    ");

    $check->execute([$medecin_id, $date, $heure]);

    if ($check->fetch()) {
        jsonResponse([
            'success' => false,
            'error'   => 'Ce créneau est déjà réservé'
        ]);
    }

    try {

        $stmt = $db->prepare("
            INSERT INTO rendez_vous
            (patient_id, medecin_id, date_rdv, heure_rdv, motif, statut)
            VALUES (?, ?, ?, ?, ?, 'en_attente')
        ");

        $stmt->execute([
            $userId,
            $medecin_id,
            $date,
            $heure,
            $motif
        ]);

        $id = $db->lastInsertId();

    } catch (PDOException $e) {

        jsonResponse([
            'success' => false,
            'error'   => "Ce créneau vient d'être réservé, veuillez en choisir un autre"
        ]);
    }

    $rdv = $db->prepare("
        SELECT r.*,
               m.prenom AS med_prenom,
               m.nom AS med_nom,
               m.specialite,
               m.adresse,
               m.telephone AS med_tel
        FROM rendez_vous r
        JOIN medecins m ON r.medecin_id = m.id
        WHERE r.id = ?
    ");

    $rdv->execute([$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Rendez-vous créé avec succès',
        'rdv'     => $rdv->fetch()
    ]);
}

/*==================================================
=            ACTIONS SUR RENDEZ-VOUS             =
==================================================*/
if ($method === 'PUT') {

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = intval($_GET['id'] ?? 0);

    if (!$id) {
        jsonResponse([
            'success' => false,
            'error'   => 'ID requis'
        ]);
    }

    $stmt = $db->prepare("SELECT * FROM rendez_vous WHERE id = ?");
    $stmt->execute([$id]);
    $rdv = $stmt->fetch();

    if (!$rdv) {
        jsonResponse([
            'success' => false,
            'error'   => 'Rendez-vous introuvable'
        ]);
    }

    switch ($action) {

        case 'confirmer':

            authRequired('medecin');

            $db->prepare("
                UPDATE rendez_vous
                SET statut = 'confirme'
                WHERE id = ?
            ")->execute([$id]);

            jsonResponse([
                'success' => true,
                'message' => 'Rendez-vous confirmé'
            ]);

        case 'annuler':

            $db->prepare("
                UPDATE rendez_vous
                SET statut = 'annule'
                WHERE id = ?
            ")->execute([$id]);

            jsonResponse([
                'success' => true,
                'message' => 'Rendez-vous annulé'
            ]);

        case 'terminer':

            authRequired('medecin');

            $db->prepare("
                UPDATE rendez_vous
                SET statut = 'termine'
                WHERE id = ?
            ")->execute([$id]);

            jsonResponse([
                'success' => true,
                'message' => 'Rendez-vous terminé'
            ]);

        case 'notes':

            authRequired('medecin');

            $notes = sanitize($data['notes_medecin'] ?? '');

            $db->prepare("
                UPDATE rendez_vous
                SET notes_medecin = ?
                WHERE id = ?
            ")->execute([$notes, $id]);

            jsonResponse([
                'success' => true,
                'message' => 'Notes enregistrées'
            ]);

        default:

            jsonResponse([
                'success' => false,
                'error'   => 'Action inconnue'
            ]);
    }
}

/*==================================================
=            LISTE DES RENDEZ-VOUS              =
==================================================*/
if ($method === 'GET') {

    $statut = sanitize($_GET['statut'] ?? '');

    $where  = [];
    $params = [];

    if ($role === 'patient') {

        $where[] = 'r.patient_id = ?';
        $params[] = $userId;

        $extra = "
            m.prenom AS med_prenom,
            m.nom AS med_nom,
            m.specialite,
            m.adresse,
            m.telephone AS med_tel
        ";

        $join = "JOIN medecins m ON r.medecin_id = m.id";

    } else {

        $where[] = 'r.medecin_id = ?';
        $params[] = $userId;

        $extra = "
            p.prenom AS pat_prenom,
            p.nom AS pat_nom,
            p.telephone,
            p.email
        ";

        $join = "JOIN patients p ON r.patient_id = p.id";
    }

    if ($statut) {
        $where[] = 'r.statut = ?';
        $params[] = $statut;
    }

    $sql = "
        SELECT r.*, $extra
        FROM rendez_vous r
        $join
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.date_rdv ASC, r.heure_rdv ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    jsonResponse([
        'success' => true,
        'rdvs'    => $stmt->fetchAll()
    ]);
}

/*==================================================
=            MÉTHODE NON SUPPORTÉE              =
==================================================*/
jsonResponse([
    'success' => false,
    'error'   => 'Méthode non supportée'
], 405);
