<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

function loadEnvFile(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        if ($line === '' || str_starts_with(ltrim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

class AiCommentService
{
    private PDO $pdo;
    private string $apiKey;
    private string $model;
    private int $taskId;
    private int $timeoutSeconds;

    public function __construct(PDO $pdo, string $apiKey, string $model, int $taskId, int $timeoutSeconds = 8)
    {
        $this->pdo = $pdo;
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->taskId = $taskId;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function generate(string $eventType, string $taskTitle, ?string $taskDescription = null): string
    {
        $prompt = $taskTitle;
        if ($taskDescription) {
            $prompt .= "\n" . $taskDescription;
        }

        $comment = $this->callAi($eventType, $prompt);
        if ($comment === '') {
            $comment = $this->fallbackText($eventType);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO task_ai_comments (task_id, event_type, ai_text, created_at) VALUES (:task_id, :event_type, :ai_text, :created_at)'
        );
        $stmt->execute([
            ':task_id' => $this->taskId,
            ':event_type' => $eventType,
            ':ai_text' => $comment,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $comment;
    }

    private function callAi(string $eventType, string $taskText): string
    {
        if ($this->apiKey === '') {
            return '';
        }

        $systemPrompt = 'Роль: антагонист и скептик. Тон: сухая ирония. 1-2 предложения. На \"ты\". '
            . 'Без вежливости, без эмодзи, без объяснений. Выход: только текст комментария.';
        $userPrompt = ($eventType === 'completed' ? 'Задача выполнена: ' : 'Задача создана: ') . $taskText;

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $userPrompt]],
            ]],
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'timeout' => $this->timeoutSeconds,
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ],
        ]);

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($this->model),
            rawurlencode($this->apiKey)
        );

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            return '';
        }

        $data = json_decode($result, true);
        return (string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    private function fallbackText(string $eventType): string
    {
        $fallbacks = $eventType === 'completed'
            ? [
                'Ну наконец-то. Мир не рухнул.',
                'Отметил. Можно было и быстрее.',
                'Выполнено. А смысл был?',
            ]
            : [
                'Записал. Будешь делать? Сомневаюсь.',
                'Ещё одна задача. Как будто их мало.',
                'Отлично. Ещё один пункт в списке.',
            ];

        return $fallbacks[array_rand($fallbacks)];
    }
}

function fetchLatestAiText(PDO $pdo, int $taskId): ?string
{
    $stmt = $pdo->prepare('SELECT ai_text FROM task_ai_comments WHERE task_id = :task_id ORDER BY id DESC LIMIT 1');
    $stmt->execute([':task_id' => $taskId]);
    $text = $stmt->fetchColumn();
    return $text === false ? null : (string) $text;
}

loadEnvFile(__DIR__ . '/.env');

$pdo = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS tasks (\n"
    . "id INTEGER PRIMARY KEY AUTOINCREMENT,\n"
    . "title TEXT NOT NULL,\n"
    . "status TEXT CHECK(status IN ('todo','done')) NOT NULL DEFAULT 'todo',\n"
    . "created_at DATETIME NOT NULL\n"
    . ")"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS task_ai_comments (\n"
    . "id INTEGER PRIMARY KEY AUTOINCREMENT,\n"
    . "task_id INTEGER,\n"
    . "event_type TEXT CHECK(event_type IN ('created','completed')) NOT NULL,\n"
    . "ai_text TEXT NOT NULL,\n"
    . "created_at DATETIME NOT NULL\n"
    . ")"
);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->get('/', function (Request $request, Response $response) {
    return $response
        ->withHeader('Location', '/index.html')
        ->withStatus(302);
});

$app->get('/tasks', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query(
        'SELECT id, title, status, created_at, '
        . '(SELECT ai_text FROM task_ai_comments WHERE task_id = tasks.id ORDER BY id DESC LIMIT 1) AS ai_text '
        . 'FROM tasks ORDER BY id DESC'
    );
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response->getBody()->write(json_encode($tasks, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/tasks/{id}/ai-comments', function (Request $request, Response $response, array $args) use ($pdo) {
    $id = (int) $args['id'];
    $stmt = $pdo->prepare(
        'SELECT id, event_type, ai_text, created_at FROM task_ai_comments WHERE task_id = :task_id ORDER BY id ASC'
    );
    $stmt->execute([':task_id' => $id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response->getBody()->write(json_encode($comments, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/tasks', function (Request $request, Response $response) use ($pdo) {
    $data = $request->getParsedBody();
    $title = isset($data['title']) ? trim((string) $data['title']) : '';

    if ($title === '') {
        $response->getBody()->write(json_encode(['error' => 'Title is required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO tasks (title, status, created_at) VALUES (:title, :status, :created_at)');
    $stmt->execute([
        ':title' => $title,
        ':status' => 'todo',
        ':created_at' => $now,
    ]);

    $id = (int) $pdo->lastInsertId();
    $apiKey = getenv('GEMINI_API_KEY') ?: '';
    $model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash';
    $aiService = new AiCommentService($pdo, $apiKey, $model, $id);
    $aiText = $aiService->generate('created', $title, null);
    $task = [
        'id' => $id,
        'title' => $title,
        'status' => 'todo',
        'created_at' => $now,
        'ai_text' => $aiText,
    ];

    $response->getBody()->write(json_encode($task, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

$app->put('/tasks/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $id = (int) $args['id'];
    $data = $request->getParsedBody();
    $status = isset($data['status']) ? (string) $data['status'] : 'done';
    $status = $status === 'done' ? 'done' : 'todo';

    $stmt = $pdo->prepare('SELECT id, title, status, created_at FROM tasks WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        $response->getBody()->write(json_encode(['error' => 'Not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $stmt = $pdo->prepare('UPDATE tasks SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $id]);
    $task['status'] = $status;

    $aiText = null;
    if ($status === 'done') {
        $apiKey = getenv('GEMINI_API_KEY') ?: '';
        $model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash';
        $aiService = new AiCommentService($pdo, $apiKey, $model, $id);
        $aiText = $aiService->generate('completed', (string) $task['title'], null);
    } else {
        $aiText = fetchLatestAiText($pdo, $id);
    }

    $task['ai_text'] = $aiText;

    $response->getBody()->write(json_encode($task, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/tasks/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $id = (int) $args['id'];
    $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id');
    $stmt->execute([':id' => $id]);

    $response->getBody()->write(json_encode(['ok' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
