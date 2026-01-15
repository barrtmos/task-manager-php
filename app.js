const list = document.getElementById('task-list');
const form = document.getElementById('task-form');
const input = document.getElementById('task-title');

const AI_DELAY_MS = 650;

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

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
    item.dataset.taskId = task.id;

    const titleWrap = document.createElement('div');
    titleWrap.className = 'task-title';

    const title = document.createElement('span');
    title.className = 'task-name';
    title.textContent = task.title;

    const meta = document.createElement('small');
    meta.textContent = `Создана: ${formatDate(task.created_at)}`;

    const commentsWrap = document.createElement('div');
    commentsWrap.className = 'task-comments';

    const commentsList = document.createElement('ul');
    commentsList.className = 'comment-list';

    titleWrap.appendChild(title);
    titleWrap.appendChild(meta);
    titleWrap.appendChild(commentsWrap);
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

    loadComments(task.id, commentsList);
  });
}

function buildLoadingItem() {
  const li = document.createElement('li');
  li.className = 'comment-item loading';

  const spinner = document.createElement('span');
  spinner.className = 'spinner visible';
  spinner.setAttribute('aria-label', 'Думает');

  const label = document.createElement('span');
  label.className = 'comment-label';
  label.textContent = 'Джонни бля коуч:';

  li.appendChild(spinner);
  li.appendChild(label);
  return li;
}

function setLoadingComments(listEl) {
  listEl.innerHTML = '';
  listEl.appendChild(buildLoadingItem());
}

async function loadComments(taskId, listEl) {
  setLoadingComments(listEl);

  try {
    const res = await fetch(`/tasks/${taskId}/ai-comments`);
    if (!res.ok) {
      listEl.innerHTML = '';
      return;
    }
    const comments = await res.json();
    listEl.innerHTML = '';
    comments.forEach((comment) => {
      const li = document.createElement('li');
      li.className = 'comment-item';

      const label = document.createElement('span');
      label.className = 'comment-label';
      label.textContent = 'Джонни бля коуч:';

      const text = document.createElement('span');
      text.className = 'comment-text';
      text.textContent = ` ${comment.ai_text}`;

      li.appendChild(label);
      li.appendChild(text);
      listEl.appendChild(li);
    });
  } catch (error) {
    listEl.innerHTML = '';
  }
}

async function addTask(title) {
  const pending = document.createElement('li');
  pending.className = 'task-item';

  const titleWrap = document.createElement('div');
  titleWrap.className = 'task-title';

  const name = document.createElement('span');
  name.className = 'task-name';
  name.textContent = title;

  const meta = document.createElement('small');
  meta.textContent = `Создана: ${formatDate(new Date().toISOString())}`;

  const commentsWrap = document.createElement('div');
  commentsWrap.className = 'task-comments';

  const commentsList = document.createElement('ul');
  commentsList.className = 'comment-list';
  setLoadingComments(commentsList);

  titleWrap.appendChild(name);
  titleWrap.appendChild(meta);
  titleWrap.appendChild(commentsWrap);
  commentsWrap.appendChild(commentsList);

  const actions = document.createElement('div');
  actions.className = 'task-actions';

  pending.appendChild(titleWrap);
  pending.appendChild(actions);
  list.prepend(pending);

  try {
    await delay(AI_DELAY_MS);
    const res = await fetch('/tasks', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title })
    });

    if (!res.ok) {
      return;
    }
  } finally {
    pending.remove();
  }

  await fetchTasks();
}

async function markDone(id) {
  const item = list.querySelector(`[data-task-id="${id}"]`);
  if (item) {
    const commentsList = item.querySelector('.comment-list');
    if (commentsList) {
      setLoadingComments(commentsList);
    }
  }
  await delay(AI_DELAY_MS);
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
