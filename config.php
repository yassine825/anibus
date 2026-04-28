<?php
// ============================================================
//  MediRDV — Configuration base de données
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'medirdv');
define('DB_USER', 'root');       // Changez selon votre config
define('DB_PASS', '');           // Changez selon votre config
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL', 'http://localhost/medirdv');
define('SESSION_DURATION', 86400); // 24h

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success'=>false,'error'=>'Erreur BDD: '.$e->getMessage()]));
        }
    }
    return $pdo;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function authRequired($role = null) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success'=>false,'error'=>'Non authentifié'], 401);
    }
    if ($role && $_SESSION['role'] !== $role) {
        jsonResponse(['success'=>false,'error'=>'Accès refusé'], 403);
    }
}

function sanitize($val) {
    return htmlspecialchars(strip_tags(trim((string)$val)));
}
