CREATE TABLE IF NOT EXISTS tasks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  status TEXT CHECK(status IN ('todo','done')) NOT NULL DEFAULT 'todo',
  created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS task_ai_comments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id INTEGER,
  event_type TEXT CHECK(event_type IN ('created','completed')) NOT NULL,
  ai_text TEXT NOT NULL,
  created_at DATETIME NOT NULL
);
