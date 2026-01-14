const list = document.getElementById('task-list');
const form = document.getElementById('task-form');
const input = document.getElementById('task-title');

async function fetchTasks() {
  const res = await fetch('/tasks');
  const tasks = await res.json();
  renderTasks(tasks);
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(value.replace(' ', 'T'));
  return date.toLocaleString('ru-RU');
}

function renderTasks(tasks) {
  list.innerHTML = '';
  if (!tasks.length) {
    const empty = document.createElement('li');
    empty.textContent = 'Пока нет задач.';
    empty.className = 'task-item';
    list.appendChild(empty);
    return;
  }

  tasks.forEach((task) => {
    const item = document.createElement('li');
    item.className = `task-item${task.status === 'done' ? ' done' : ''}`;

    const titleWrap = document.createElement('div');
    titleWrap.className = 'task-title';

    const title = document.createElement('span');
    title.textContent = task.title;

    const meta = document.createElement('small');
    meta.textContent = `Создана: ${formatDate(task.created_at)}`;

    const commentsWrap = document.createElement('div');
    commentsWrap.className = 'task-comments';

    const spinner = document.createElement('span');
    spinner.className = 'spinner';
    spinner.setAttribute('aria-label', 'Думает');

    const commentsList = document.createElement('ul');
    commentsList.className = 'comment-list';

    titleWrap.appendChild(title);
    titleWrap.appendChild(meta);
    titleWrap.appendChild(commentsWrap);

    commentsWrap.appendChild(spinner);
    commentsWrap.appendChild(commentsList);

    const actions = document.createElement('div');
    actions.className = 'task-actions';

    const doneBtn = document.createElement('button');
    doneBtn.textContent = 'Готово';
    doneBtn.className = 'done';
    doneBtn.disabled = task.status === 'done';
    doneBtn.addEventListener('click', () => markDone(task.id));

    const delBtn = document.createElement('button');
    delBtn.textContent = 'Удалить';
    delBtn.className = 'delete';
    delBtn.addEventListener('click', () => deleteTask(task.id));

    actions.appendChild(doneBtn);
    actions.appendChild(delBtn);

    item.appendChild(titleWrap);
    item.appendChild(actions);
    list.appendChild(item);

    loadComments(task.id, commentsList, spinner);
  });
}

async function loadComments(taskId, listEl, spinnerEl) {
  spinnerEl.classList.add('visible');
  try {
    const res = await fetch(`/tasks/${taskId}/ai-comments`);
    if (!res.ok) {
      return;
    }
    const comments = await res.json();
    listEl.innerHTML = '';
    comments.forEach((comment) => {
      const li = document.createElement('li');
      li.textContent = `Джонни бля коуч: ${comment.ai_text}`;
      listEl.appendChild(li);
    });
  } finally {
    spinnerEl.classList.remove('visible');
  }
}

async function addTask(title) {
  const res = await fetch('/tasks', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title })
  });

  if (!res.ok) {
    return;
  }

  await fetchTasks();
}

async function markDone(id) {
  await fetch(`/tasks/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status: 'done' })
  });
  await fetchTasks();
}

async function deleteTask(id) {
  await fetch(`/tasks/${id}`, { method: 'DELETE' });
  await fetchTasks();
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const title = input.value.trim();
  if (!title) return;
  input.value = '';
  await addTask(title);
});

fetchTasks();
