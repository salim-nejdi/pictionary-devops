<?php
// metrics.php — Endpoint expose au format Prometheus.
// Scrape par Prometheus pour collecter les metriques applicatives de Pictionary.
// Accessible via /metrics.php

header('Content-Type: text/plain; version=0.0.4');

$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');

// Valeurs par defaut si la DB est injoignable
$requests_total = 0;
$words_total    = 0;
$db_up          = 0;

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
    );
    $db_up = 1;

    // Lecture des compteurs stockes en base (partages entre les replicas)
    $rows = $pdo->query("SELECT name, value FROM metrics")->fetchAll(PDO::FETCH_KEY_PAIR);
    $requests_total = (int)($rows['requests_total'] ?? 0);
    $words_total    = (int)($rows['words_generated_total'] ?? 0);
} catch (Exception $e) {
    // DB injoignable : db_up reste a 0, les compteurs restent a 0
    $db_up = 0;
}

// Sortie au format texte Prometheus (# HELP + # TYPE + metrique valeur)
echo "# HELP pictionary_requests_total Nombre total de requetes HTTP recues.\n";
echo "# TYPE pictionary_requests_total counter\n";
echo "pictionary_requests_total {$requests_total}\n";

echo "# HELP pictionary_words_generated_total Nombre total de mots generes par l'API.\n";
echo "# TYPE pictionary_words_generated_total counter\n";
echo "pictionary_words_generated_total {$words_total}\n";

echo "# HELP pictionary_db_up Statut de la connexion a la base de donnees (1 = up, 0 = down).\n";
echo "# TYPE pictionary_db_up gauge\n";
echo "pictionary_db_up {$db_up}\n";
