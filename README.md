# Task Manager (PHP + Slim + SQLite)

Минимальный с фичей "ии-комментатор антикоуч". REST API на Slim, простой UI на HTML/CSS/JS. Данные хранятся в SQLite, комментарии генерируются через Gemini при наличии ключа.

## Стек
- PHP 8+
- Slim Framework
- SQLite
- Vanilla JS, HTML, CSS

## Локальный запуск
Нужны PHP 8+ и Composer.

```bash
composer install
```

```bash
php -S localhost:8080 index.php
```

Открой `http://localhost:8080/`.

## Env
Скопируй `.env.example` в `.env` и укажи `GEMINI_API_KEY` (опционально `GEMINI_MODEL`).

## Просмотр БД
После запуска сервера открой `http://localhost:8080/debug-db.php`.

## Как расширять
- Новые поля/статусы задач: `index.php` и таблица `tasks`.
- Новые эндпоинты: `index.php`.
- Правки UI: `index.html`, `app.js`, `styles.css`.

## Preview
![Preview](docs/screenshot.png)
