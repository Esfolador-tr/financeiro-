<?php
declare(strict_types=1);

/*
 * Configuração do Controle Financeiro.
 * Ao publicar na InfinityFree, coloque a pasta "private" FORA de htdocs.
 * Se seu provedor usar outra estrutura, altere DB_PATH para um caminho
 * absoluto fora da pasta pública.
 */
define('APP_NAME', 'Meu Financeiro');
define('DB_PATH', dirname(__DIR__) . '/private/financeiro.sqlite');
define('SESSION_NAME', 'meu_financeiro_session');

date_default_timezone_set('America/Sao_Paulo');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException(
            'A pasta privada do banco não existe ou não permite gravação. Confira DB_PATH em config.php.'
        );
    }
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('A hospedagem não habilitou PDO SQLite. Consulte o suporte da hospedagem.');
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    initialize_database($pdo);
    return $pdo;
}

function initialize_database(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        kind TEXT NOT NULL CHECK(kind IN ('income','expense','both')),
        color TEXT NOT NULL DEFAULT '#64748b',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, name, kind),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        category_id INTEGER,
        description TEXT NOT NULL,
        amount_cents INTEGER NOT NULL CHECK(amount_cents > 0),
        kind TEXT NOT NULL CHECK(kind IN ('income','expense')),
        transaction_date TEXT NOT NULL,
        payment_method TEXT NOT NULL DEFAULT '',
        notes TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS goals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        target_cents INTEGER NOT NULL CHECK(target_cents > 0),
        saved_cents INTEGER NOT NULL DEFAULT 0 CHECK(saved_cents >= 0),
        target_date TEXT,
        notes TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS budgets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        category_id INTEGER NOT NULL,
        month TEXT NOT NULL,
        limit_cents INTEGER NOT NULL CHECK(limit_cents > 0),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, category_id, month),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_transactions_user_date ON transactions(user_id, transaction_date DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_transactions_user_category ON transactions(user_id, category_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_budgets_user_month ON budgets(user_id, month)");
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        json_response(['error' => 'Método não permitido.'], 405);
    }
}

function request_data(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$sent || !hash_equals(csrf_token(), $sent)) {
        json_response(['error' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 419);
    }
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_auth(): int {
    $id = current_user_id();
    if (!$id) json_response(['error' => 'Faça login para continuar.'], 401);
    return $id;
}

function valid_date(string $date): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function cents_from_value(mixed $value): int {
    if (!is_numeric($value)) throw new InvalidArgumentException('Informe um valor válido.');
    $amount = (float)$value;
    if (!is_finite($amount) || $amount <= 0 || $amount > 999999999.99) {
        throw new InvalidArgumentException('O valor deve ser maior que zero e estar dentro do limite permitido.');
    }
    return (int)round($amount * 100);
}

function owned_category(PDO $pdo, int $categoryId, int $userId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE id = ? AND user_id = ?');
    $stmt->execute([$categoryId, $userId]);
    return (bool)$stmt->fetchColumn();
}
