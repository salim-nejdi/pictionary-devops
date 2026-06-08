<?php
// =============================================================================
// SECTION 1 — CONFIGURATION
// On lit les variables d'environnement injectées par Kubernetes (Secret K8s)
// ou par Docker en local (.env via docker-compose).
// getenv('VAR') retourne false si la variable n'existe pas.
// On NE met PAS de valeur par défaut pour les secrets (pas de ?: 'motdepasse').
// Si une variable manque, on veut le savoir immédiatement — pas un comportement
// silencieux qui se connecte avec un mot de passe par défaut.
// =============================================================================

$required_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
$missing       = [];

foreach ($required_vars as $var) {
    if (getenv($var) === false) {
        $missing[] = $var;
    }
}

if (!empty($missing)) {
    // error_log : écrit dans les logs du conteneur (visibles avec kubectl logs
    // ou docker logs). On n'affiche JAMAIS les détails techniques à l'utilisateur.
    error_log('[Pictionary] Variables manquantes : ' . implode(', ', $missing));

    // Si c'est une requête API (AJAX depuis le JS), on répond en JSON
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['erreur' => 'Erreur de configuration serveur']);
        exit;
    }

    // Sinon page d'erreur HTML sobre — sans exposer les noms des variables
    http_response_code(500);
    die('<h1>Erreur de configuration</h1><p>Contactez l\'administrateur.</p>');
}

$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');

// Version de l'application (injectee dans l'image au build par la CI/CD).
// 'dev' par defaut si non definie (build local sans --build-arg).
$app_version = getenv('APP_VERSION') ?: 'dev';
// On detecte si c'est une version de preprod (contient "preprod")
$is_preprod = str_contains($app_version, 'preprod');


// =============================================================================
// SECTION 2 — CONNEXION BASE DE DONNÉES
// =============================================================================

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[Pictionary] Erreur connexion DB : ' . $e->getMessage());

    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode(['erreur' => 'Base de données indisponible']);
        exit;
    }

    http_response_code(503);
    die('<h1>Service temporairement indisponible</h1>');
}

// Metrique applicative
try {
    $pdo->exec("UPDATE metrics SET value = value + 1 WHERE name = 'requests_total'");
} catch (Exception $e) { /* non bloquant */ }


// =============================================================================
// SECTION 3 — API AJAX
// =============================================================================

if (isset($_GET['action']) && $_GET['action'] === 'get_word') {
    $stmt   = $pdo->query("SELECT mot FROM mots ORDER BY RAND() LIMIT 1");
    $result = $stmt->fetch();

    try {
        $pdo->exec("UPDATE metrics SET value = value + 1 WHERE name = 'words_generated_total'");
    } catch (Exception $e) { /* non bloquant pour le jeu */ }

    header('Content-Type: application/json');
    echo json_encode(['mot' => $result ? $result['mot'] : 'Base vide !']);
    exit;
}


// =============================================================================
// SECTION 4 — PAGE HTML
// =============================================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pictionary !</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 33%, #0072ff 66%, #0250c5 100%);
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .confetti {
            position: fixed;
            border-radius: 2px;
            animation: fall linear infinite;
            pointer-events: none;
        }

        @keyframes fall {
            0%   { transform: translateY(-20px) rotate(0deg);   opacity: 1; }
            100% { transform: translateY(110vh)  rotate(720deg); opacity: 0; }
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 50px 60px;
            box-shadow: 0 12px 0 #0250c5, 0 16px 30px rgba(0,0,0,0.3);
            border: 5px solid #fff;
            outline: 4px solid rgba(255,255,255,0.5);
            max-width: 500px;
            width: 100%;
            text-align: center;
            position: relative;
            animation: cardPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes cardPop {
            from { transform: scale(0.6) rotate(-5deg); opacity: 0; }
            to   { transform: scale(1)   rotate(0deg);  opacity: 1; }
        }

        .badge {
            position: absolute;
            top: -22px;
            left: 50%;
            transform: translateX(-50%);
            background: #f9c74f;
            border: 4px solid white;
            border-radius: 50px;
            padding: 6px 20px;
            font-size: 0.85em;
            font-weight: 900;
            color: #333;
            white-space: nowrap;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        h1 {
            font-family: 'Fredoka One', cursive;
            font-size: 2.8em;
            color: #0072ff;
            text-shadow: 3px 3px 0 #c2e9fb, 5px 5px 0 rgba(0,0,0,0.1);
            margin-bottom: 8px;
        }

        .subtitle { color: #aaa; font-size: 0.95em; margin-bottom: 20px; }

        .mot-container {
            background: linear-gradient(135deg, #f0f8ff, #e6f2ff);
            border: 3px dashed #0072ff;
            border-radius: 16px;
            padding: 28px 20px;
            margin: 15px 0;
            position: relative;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .mot-container::before { content: "✏️"; position: absolute; top: -18px;    left: -18px;  font-size: 2em; }
        .mot-container::after  { content: "🎨"; position: absolute; bottom: -18px; right: -18px; font-size: 2em; }

        #emoji-display {
            font-size: 3.5em;
            line-height: 1;
            transition: opacity 0.2s ease;
        }

        #word-display {
            font-family: 'Fredoka One', cursive;
            font-size: 2.8em;
            color: #0072ff;
            text-shadow: 2px 2px 0 #c2e9fb;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: opacity 0.2s ease;
        }

        .hidden { opacity: 0; }

        @keyframes pop {
            from { transform: scale(0.4) rotate(-10deg); opacity: 0; }
            to   { transform: scale(1)   rotate(0deg);   opacity: 1; }
        }
        .pop { animation: pop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

        .timer-container {
            font-family: 'Fredoka One', cursive;
            font-size: 1.8em;
            color: #577590;
            margin: 15px 0;
            transition: color 0.3s;
        }
        .timer-container.urgent {
            color: #d62828;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1);   }
            50%       { transform: scale(1.1); }
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            color: #ddd;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(to right, transparent, #ddd, transparent);
        }

        button {
            font-family: 'Fredoka One', cursive;
            font-size: 1.4em;
            cursor: pointer;
            background: linear-gradient(145deg, #00f2fe, #0072ff);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px 40px;
            box-shadow: 0 6px 0 #0250c5, 0 8px 15px rgba(0,0,0,0.2);
            transition: transform 0.1s, box-shadow 0.1s;
            letter-spacing: 1px;
        }
        button:hover  { background: linear-gradient(145deg, #4facfe, #0072ff); }
        button:active {
            transform: translateY(4px);
            box-shadow: 0 2px 0 #0250c5;
        }
        button:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .stars { font-size: 1.2em; color: #f9c74f; margin-top: 20px; letter-spacing: 4px; }

        .score-box {
            margin-top: 18px;
            padding: 12px 20px;
            background: rgba(255,255,255,0.15);
            border-radius: 16px;
            font-weight: 700;
            color: white;
        }
        .score-box #count { font-size: 1.6em; color: #f9c74f; }
        .score-msg { font-size: 0.9em; margin-top: 6px; opacity: 0.9; }

        .version-badge {
            position: fixed;
            bottom: 12px;
            right: 12px;
            font-family: 'Nunito', sans-serif;
            font-weight: 700;
            font-size: 0.8em;
            padding: 6px 14px;
            border-radius: 20px;
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
            z-index: 1000;
        }
        .version-prod { background: rgba(0, 114, 255, 0.9); }
        .version-preprod {
            background: #f3722c;
            border: 2px solid white;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

    <script>
        const colors = ['#4facfe','#00f2fe','#0072ff','#f9c74f','#a8dadc','#ffffff'];

        for (let i = 0; i < 18; i++) {
            const el    = document.createElement('div');
            el.className = 'confetti';
            el.style.cssText = `
                left:              ${Math.random() * 100}vw;
                width:             ${6  + Math.random() * 10}px;
                height:            ${6  + Math.random() * 10}px;
                background:        ${colors[Math.floor(Math.random() * colors.length)]};
                animation-duration:${3  + Math.random() * 5}s;
                animation-delay:   ${Math.random() * 5}s;
                border-radius:     ${Math.random() > 0.5 ? '50%' : '2px'};
            `;
            document.body.appendChild(el);
        }
    </script>

    <div class="card">
        <div class="badge">🎲 Tour en cours !</div>
        <h1>Pictionary !</h1>
        <p class="subtitle">Faites deviner le mot à votre équipe ✨</p>

        <div class="mot-container">
            <div id="emoji-display">🎨</div>
            <div id="word-display">?</div>
        </div>

        <div class="timer-container" id="timer-box" style="display:none;">
            <div id="timer">⏳ 60s</div>
        </div>

        <div class="divider">🎴</div>

        <button id="btn" onclick="generateWord()">🎲 Nouveau mot !</button>

        <div class="stars">★ ★ ★ ★ ★</div>

        <div class="score-box">
            🏆 Mots joués : <span id="count">0</span>
            <div class="score-msg" id="score-msg">Lance ton premier mot !</div>
        </div>
    </div>

    <script>
        const emojiMap = {
            'CHAT':'🐱','CHIEN':'🐶','LION':'🦁','TIGRE':'🐯','ÉLÉPHANT':'🐘',
            'GIRAFE':'🦒','SINGE':'🐒','LAPIN':'🐰','COCHON':'🐷','VACHE':'🐮',
            'CHEVAL':'🐴','MOUTON':'🐑','POULE':'🐔','CANARD':'🦆','POISSON':'🐟',
            'REQUIN':'🦈','DAUPHIN':'🐬','PIEUVRE':'🐙','CRABE':'🦀','GRENOUILLE':'🐸',
            'SERPENT':'🐍','CROCODILE':'🐊','TORTUE':'🐢','DINOSAURE':'🦕','DRAGON':'🐉',
            'PAPILLON':'🦋','ABEILLE':'🐝','ARAIGNÉE':'🕷️','HIBOU':'🦉','PINGOUIN':'🐧',
            'FLAMANT':'🦩','PERROQUET':'🦜','AIGLE':'🦅',
            'PIZZA':'🍕','BURGER':'🍔','FRITE':'🍟','HOT DOG':'🌭','SUSHI':'🍣',
            'RAMEN':'🍜','TACO':'🌮','GLACE':'🍦','GÂTEAU':'🎂','COOKIE':'🍪',
            'CHOCOLAT':'🍫','BONBON':'🍬','POMME':'🍎','BANANE':'🍌','FRAISE':'🍓',
            'RAISIN':'🍇','PASTÈQUE':'🍉','ANANAS':'🍍','CITRON':'🍋','CERISE':'🍒',
            'MANGUE':'🥭','CAROTTE':'🥕','MAÏS':'🌽','BROCOLI':'🥦','AUBERGINE':'🍆',
            'CHAMPIGNON':'🍄','FROMAGE':'🧀','PAIN':'🍞','CAFÉ':'☕','THÉ':'🍵',
            'VOITURE':'🚗','CAMION':'🚛','BUS':'🚌','MOTO':'🏍️','VÉLO':'🚲',
            'TROTTINETTE':'🛴','AVION':'✈️','HÉLICOPTÈRE':'🚁','FUSÉE':'🚀','BATEAU':'🚢',
            'TRAIN':'🚂','TAXI':'🚕','AMBULANCE':'🚑','TRACTEUR':'🚜','SKATEBOARD':'🛹',
            'SOLEIL':'☀️','LUNE':'🌙','ÉTOILE':'⭐','NUAGE':'☁️','PLUIE':'🌧️',
            'NEIGE':'❄️','ARC-EN-CIEL':'🌈','ÉCLAIR':'⚡','VOLCAN':'🌋','MONTAGNE':'⛰️',
            'PLAGE':'🏖️','DÉSERT':'🏜️','FORÊT':'🌲','FLEUR':'🌸','CACTUS':'🌵',
            'ARBRE':'🌳','FEUILLE':'🍃','MER':'🌊','FEU':'🔥','VENT':'💨',
            'MAISON':'🏠','CHÂTEAU':'🏰','ÉCOLE':'🏫','HÔPITAL':'🏥','ÉGLISE':'⛪',
            'TENTE':'⛺','TÉLÉPHONE':'📱','ORDINATEUR':'💻','TÉLÉVISION':'📺','CAMÉRA':'📷',
            'LUNETTES':'👓','CLÉ':'🔑','CADENAS':'🔒','LAMPE':'💡','BOUGIE':'🕯️',
            'LIVRE':'📚','CRAYON':'✏️','MARTEAU':'🔨','BALLON':'🎈','CADEAU':'🎁',
            'COURONNE':'👑','DIAMANT':'💎','BAGUE':'💍','CHAPEAU':'🎩','PARAPLUIE':'☂️',
            'FOOTBALL':'⚽','BASKET':'🏀','TENNIS':'🎾','BASEBALL':'⚾','GOLF':'⛳',
            'BOXE':'🥊','KARATÉ':'🥋','SKI':'⛷️','SURF':'🏄','GUITARE':'🎸',
            'ROBOT':'🤖','FANTÔME':'👻','MONSTRE':'👾','ZOMBIE':'🧟','FÉE':'🧚',
            'SORCIÈRE':'🧙','VAMPIRE':'🧛','PIRATE':'🏴‍☠️','COWBOY':'🤠','CLOWN':'🤡',
            'PÈRE NOËL':'🎅','ALIEN':'👽','LICORNE':'🦄','SIRÈNE':'🧜','ASTRONAUTE':'🧑‍🚀',
        };

        const DEFAULT_EMOJI = '🎨';

        function getEmoji(mot) {
            return emojiMap[mot.toUpperCase().trim()] ?? DEFAULT_EMOJI;
        }

        let countdownInterval = null;
        let isTimerRunning    = false;
        const TIME_LIMIT      = 60;

        function startTimer() {
            const timerBox = document.getElementById('timer-box');
            const timerEl  = document.getElementById('timer');
            let timeLeft   = TIME_LIMIT;

            isTimerRunning = true;
            timerBox.style.display = 'block';
            timerBox.classList.remove('urgent');
            timerEl.textContent = `⏳ ${timeLeft}s`;

            if (countdownInterval) clearInterval(countdownInterval);

            countdownInterval = setInterval(() => {
                timeLeft--;
                timerEl.textContent = `⏳ ${timeLeft}s`;

                if (timeLeft <= 10) timerBox.classList.add('urgent');

                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    timerEl.textContent = '⏱️ Temps écoulé !';
                    isTimerRunning = false;
                }
            }, 1000);
        }

        let wordCount = 0;

        async function generateWord() {
            const wordEl   = document.getElementById('word-display');
            const emojiEl  = document.getElementById('emoji-display');
            const btn      = document.getElementById('btn');
            const timerBox = document.getElementById('timer-box');

            btn.disabled = true;

            wordEl.classList.add('hidden');
            emojiEl.classList.add('hidden');
            if (!isTimerRunning) timerBox.style.display = 'none';

            try {
                const response = await fetch('?action=get_word');

                if (!response.ok) {
                    throw new Error(`Erreur HTTP : ${response.status}`);
                }

                const data = await response.json();

                setTimeout(() => {
                    wordEl.textContent  = data.mot;
                    emojiEl.textContent = getEmoji(data.mot);

                    wordCount++;
                    document.getElementById('count').textContent = wordCount;

                    // Liste de mots d'encouragement
                    const encouragements = [
                        "Super ! 🌟", "Génial ! 🎉", "Continue comme ça ! 💪",
                        "Allez ! ⚡", "Tu gères ! 😎", "Trop fort ! 🔥",
                        "C'est parti ! 🚀", "Wow ! 🤩", "Magnifique ! ✨",
                        "Incroyable ! 🎯", "On y croit ! 🏆", "Parfait ! 👌"
                    ];
                    
                    // Tire un message d'encouragement au hasard et l'affiche
                    const randomMsg = encouragements[Math.floor(Math.random() * encouragements.length)];
                    document.getElementById('score-msg').textContent = randomMsg;

                    wordEl.classList.remove('hidden', 'pop');
                    emojiEl.classList.remove('hidden', 'pop');

                    void wordEl.offsetWidth;
                    void emojiEl.offsetWidth;

                    wordEl.classList.add('pop');
                    emojiEl.classList.add('pop');

                    btn.disabled = false;

                    if (!isTimerRunning) startTimer();

                }, 200);

            } catch (error) {
                console.error('[Pictionary] Erreur API :', error);
                wordEl.textContent  = 'Erreur !';
                emojiEl.textContent = '😢';
                wordEl.classList.remove('hidden');
                emojiEl.classList.remove('hidden');
                btn.disabled = false;
            }
        }
    </script>

    <?php if ($is_preprod): ?>
        <div class="version-badge version-preprod">PREPROD &middot; <?= htmlspecialchars($app_version) ?></div>
    <?php else: ?>
        <div class="version-badge version-prod">v<?= htmlspecialchars($app_version) ?></div>
    <?php endif; ?>

</body>
</html>
