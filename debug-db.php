<?php

declare(strict_types=1);

$pdo = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query('SELECT id, title, status, created_at FROM tasks ORDER BY id DESC');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/plain; charset=utf-8');

if (!$rows) {
    echo "No tasks.\n";
    exit;
}

foreach ($rows as $row) {
    echo sprintf("%d | %s | %s | %s\n", $row['id'], $row['status'], $row['created_at'], $row['title']);
}
