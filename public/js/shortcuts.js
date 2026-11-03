(function () {
  // Exempts checkboxes and radios so filter panel arrow nav works
  const isTyping = (e) => {
    const t = e.target;
    if (!t) return false;
    const tag = (t.tagName || '').toLowerCase();
    if (tag === 'input' && (t.type === 'checkbox' || t.type === 'radio')) return false;
    return t.isContentEditable || tag === 'input' || tag === 'textarea' || tag === 'select';
  };

  const goto = (url) => { if (url) window.location.href = url; };

  let seq = '';
  let seqTimer = null;
  let _boardCollapseAll = false;
  const resetSeq = () => { seq = ''; if (seqTimer) { clearTimeout(seqTimer); seqTimer = null; } };

  // Shift+Arrow reorder dispatches a Livewire request, but the DOM doesn't
  // reflect the new order until the response morphs it in. A repeat press
  // before that round-trip finishes would otherwise read the same stale DOM
  // order and recompute the same target index, so rapid presses collapse
  // into a single visible move. Track the last dispatched index per item id
  // so a same-item repeat press continues from there instead of the DOM.
  const pendingMoveIndex = {};

  function pendingIndexFor(id) {
    return Object.prototype.hasOwnProperty.call(pendingMoveIndex, id) ? pendingMoveIndex[id].index : null;
  }

  function setPendingIndex(id, index) {
    if (pendingMoveIndex[id]) clearTimeout(pendingMoveIndex[id].timer);
    pendingMoveIndex[id] = { index, timer: setTimeout(function () { delete pendingMoveIndex[id]; }, 3000) };
  }

  function clearPendingIndex(id) {
    if (pendingMoveIndex[id]) clearTimeout(pendingMoveIndex[id].timer);
    delete pendingMoveIndex[id];
  }

  const routes = window.AppRoutes || {};

  // Flux dropdowns (ui-menu, native [popover]) close themselves on Escape via
  // their own bubble-phase handler, which may run before ours. Capture the
  // "was a popover open" state as early as possible so the Escape-goes-back
  // shortcut below can still see it after the popover has already closed.
  let _popoverWasOpenOnEscape = false;
  // Same for a modal dialog: Escape closes it natively, and that must be all it does.
  let _modalWasOpenOnEscape = false;
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      _popoverWasOpenOnEscape = !!document.querySelector('[popover]:popover-open');
      _modalWasOpenOnEscape = !!document.querySelector('dialog[open]');
    }
  }, true);

  // ── Help overlay ──────────────────────────────────────────────────────────

  const kbdStyle = 'background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px';
  // Rows of [keys, label] as help lines; keys shown with "+" between modifier and key, "/" between alternatives.
  const helpRows = (rows) => rows.map(([keys, label]) => {
    const joiner = keys[0] === 'Shift' || keys[0] === 'Ctrl' ? '+' : ' / ';
    return '<div>' + keys.map((k) => '<kbd style="' + kbdStyle + '">' + k + '</kbd>').join(joiner) + ' &nbsp; ' + label + '</div>';
  }).join('');

  const helpHtml = `
    <div id="kb-help-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;display:flex;align-items:center;justify-content:center;">
      <div style="background:#18181b;color:#e4e4e7;padding:24px;border-radius:12px;max-width:580px;width:92%;max-height:90vh;overflow:auto;border:1px solid #3f3f46">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <h2 style="font-size:16px;font-weight:600;margin:0">Keyboard Shortcuts</h2>
          <button id="kb-help-close" style="border:1px solid #3f3f46;background:#27272a;color:#a1a1aa;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px">Esc</button>
        </div>
        <div style="font-size:13px;line-height:1.9;display:grid;grid-template-columns:1fr 1fr;gap:16px 32px">
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Global</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">g</kbd> → <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">e</kbd> &nbsp; Go to Epics</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">g</kbd> → <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">t</kbd> &nbsp; Go to Templates</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">g</kbd> → <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">d</kbd> &nbsp; Go to Dashboard</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">g</kbd> → <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">s</kbd> &nbsp; Go to Settings</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">?</kbd> &nbsp; This help</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Esc</kbd> &nbsp; Back / close</div>
            <div style="color:#71717a;margin-top:4px"><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">+</kbd> always works anywhere <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">n</kbd> (new) is listed</div>
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Epics list</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↑</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↓</kbd> &nbsp; Select epic</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Shift</kbd>+<kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↑</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↓</kbd> &nbsp; Move epic</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Enter</kbd> &nbsp; Open board</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">e</kbd> &nbsp; Edit selected</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">n</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">+</kbd> &nbsp; New epic</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">f</kbd> &nbsp; Toggle filters</div>
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Epic board</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↑</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↓</kbd> &nbsp; Select item</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Shift</kbd>+<kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↑</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↓</kbd> &nbsp; Move item (board/kanban)</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">←</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">→</kbd> &nbsp; Switch column (kanban)</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Enter</kbd> &nbsp; Open selected item</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Delete</kbd> &nbsp; Delete selected item</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">+</kbd> &nbsp; Add sibling (feature, or task if a task is selected)</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Shift</kbd>+<kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">+</kbd> &nbsp; Add task in selected feature</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">t</kbd> &nbsp; Toggle feature collapse</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Shift</kbd>+<kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">t</kbd> &nbsp; Toggle all features</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">e</kbd> &nbsp; Edit epic</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">1</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">2</kbd> &nbsp; Board / Kanban</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">f</kbd> &nbsp; Toggle filters</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">n</kbd> &nbsp; Add feature</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Ctrl</kbd>+<kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Enter</kbd> &nbsp; Save form</div>
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Filter panel</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↑</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↓</kbd> &nbsp; Navigate options</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Space</kbd> &nbsp; Toggle filter</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Esc</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">f</kbd> &nbsp; Close &amp; return</div>
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Test matrices</div>
            ${helpRows([
              [['↑', '↓'], 'Select matrix'],
              [['Enter'], 'Open matrix'],
              [['n', '+'], 'New matrix'],
              [['Delete'], 'Delete selected matrix'],
              [['m'], 'Test matrices (from the epic board)'],
            ])}
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Test matrix grid</div>
            ${helpRows([
              [['↑', '↓', '←', '→'], 'Select cell (↓ from the last row: comments)'],
              [['Home', 'End'], 'First / last context'],
              [['Space'], 'Cycle status: untested, passed, failed'],
              [['p', 'x', 'u'], 'Mark passed / failed / untested'],
              [['r'], 'Toggle relevant in this context'],
              [['Enter'], 'Open cell (details and comments)'],
              [['c'], 'Comment on the cell'],
              [['e'], 'Edit scenario (comment, when selected)'],
              [['Shift', 'e'], 'Edit context'],
              [['n', '+'], 'Add scenario'],
              [['Shift', 'n'], 'Add context'],
              [['m'], 'Edit matrix'],
              [['Shift', '↑', '↓'], 'Move scenario'],
              [['Shift', '←', '→'], 'Move context'],
              [['Delete'], 'Delete scenario / comment'],
              [['Shift', 'Delete'], 'Delete context'],
              [['Ctrl', 'Enter'], 'Save form'],
              [['Esc'], 'Close dialog'],
            ])}
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Templates</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↑</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↓</kbd> &nbsp; Select template</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Enter</kbd> &nbsp; Open selected</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">e</kbd> &nbsp; Edit selected (list only)</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Delete</kbd> &nbsp; Delete / remove selected</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">n</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">+</kbd> &nbsp; New (feature or epic template, or task/link on a template page)</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">c</kbd> &nbsp; Copy selected feature template</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">x</kbd> &nbsp; Cut selected (epic template page only)</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">v</kbd> &nbsp; Paste into this epic template</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Ctrl</kbd>+<kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Enter</kbd> &nbsp; Save form</div>
          </div>
        </div>
      </div>
    </div>`;

  const showHelp = () => {
    if (document.getElementById('kb-help-overlay')) return;
    const wrap = document.createElement('div');
    wrap.innerHTML = helpHtml;
    document.body.appendChild(wrap.firstElementChild);
    document.getElementById('kb-help-close').addEventListener('click', hideHelp);
  };

  const hideHelp = () => {
    const el = document.getElementById('kb-help-overlay');
    if (el) el.remove();
  };

  // ── Selection helpers ─────────────────────────────────────────────────────

  let lastMatrixKey = null;

  function setActive(el) {
    document.querySelectorAll('[data-selectable].active').forEach((n) => n.classList.remove('active'));
    if (el) {
      el.classList.add('active');
      el.scrollIntoView({ block: 'nearest' });
      // Remembered so a test matrix can pick the same cell or comment out again after it is redrawn.
      lastMatrixKey = el.getAttribute('data-cell') || el.getAttribute('data-comment') || lastMatrixKey;
    }
  }

  function isDomVisible(el) {
    let node = el;
    while (node) {
      if (node.style && node.style.display === 'none') return false;
      node = node.parentElement;
    }
    return true;
  }

  function moveSelection(delta) {
    const items = Array.from(document.querySelectorAll('[data-selectable]')).filter(isDomVisible);
    if (!items.length) return;
    let index = items.findIndex((n) => n.classList.contains('active'));
    if (index < 0) { setActive(items[0]); return; }
    const next = Math.max(0, Math.min(items.length - 1, index + delta));
    setActive(items[next]);
  }

  function currentActive() { return document.querySelector('[data-selectable].active'); }

  function selectFirst(selector) {
    const container = document.querySelector(selector);
    if (!container) return false;
    const items = container.querySelectorAll('[data-selectable]');
    if (!items.length) return false;
    setActive(items[0]);
    return true;
  }

  // Clicking a feature/task/epic row also selects it, so keyboard nav
  // (arrows, Shift+arrows, Enter) continues from wherever the mouse last clicked.
  document.addEventListener('click', function (e) {
    const el = e.target.closest('[data-selectable]');
    if (el) setActive(el);
  });

  // ── View helpers ──────────────────────────────────────────────────────────

  function getView() {
    const el = document.querySelector('[data-view]');
    return el ? el.getAttribute('data-view') : null;
  }

  function getBoardMode() {
    const el = document.querySelector('[data-board-mode]');
    return el ? el.getAttribute('data-board-mode') : null;
  }

  function shortcut(key) {
    const el = document.querySelector('[data-shortcut="' + key + '"]');
    if (el) el.click();
  }

  // ── Kanban column navigation ──────────────────────────────────────────────

  function getKanbanColumns() {
    return Array.from(document.querySelectorAll('[data-kanban-col]'));
  }

  function getActiveColumnIndex() {
    const active = currentActive();
    if (!active) return -1;
    return getKanbanColumns().findIndex((col) => col.contains(active));
  }

  function moveKanbanColumn(delta) {
    const cols = getKanbanColumns();
    if (!cols.length) return;
    let colIdx = getActiveColumnIndex();
    if (colIdx < 0) {
      // Nothing active yet — pick first task in first/last column
      const col = cols[delta > 0 ? 0 : cols.length - 1];
      const tasks = col.querySelectorAll('[data-selectable]');
      if (tasks.length) setActive(tasks[0]);
      return;
    }
    const nextIdx = Math.max(0, Math.min(cols.length - 1, colIdx + delta));
    if (nextIdx === colIdx) return;
    const nextCol = cols[nextIdx];
    const tasks = nextCol.querySelectorAll('[data-selectable]');
    if (tasks.length) setActive(tasks[0]);
  }

  function moveInKanbanColumn(delta) {
    const cols = getKanbanColumns();
    const colIdx = getActiveColumnIndex();
    if (colIdx < 0) { moveSelection(delta); return; }
    const tasks = Array.from(cols[colIdx].querySelectorAll('[data-selectable]'));
    if (!tasks.length) return;
    const active = currentActive();
    const idx = tasks.indexOf(active);
    if (idx < 0) { setActive(tasks[0]); return; }
    setActive(tasks[Math.max(0, Math.min(tasks.length - 1, idx + delta))]);
  }

  function openActiveTask() {
    const active = currentActive();
    if (!active) return;
    const btn = active.querySelector('[data-open-btn]');
    if (btn) btn.click();
  }

  // ── Contextual add / delete on the selected feature or task ──────────────

  function addTaskToActiveFeature() {
    const active = currentActive();
    if (!active) return;
    const card = active.closest('[data-board-feature-card]');
    if (!card) return;
    const btn = card.querySelector('[data-add-task-btn]');
    if (btn) btn.click();
  }

  function addContextual(isSubLevel) {
    const active = currentActive();
    const isFeatureSelected = !!active && active.hasAttribute('data-feature-id');
    const isTaskSelected = !!active && !isFeatureSelected && active.hasAttribute('wire:sort:item');

    if (isSubLevel) {
      // Shift+Plus: add a sub-level element — only a feature has one (a task).
      if (isFeatureSelected) addTaskToActiveFeature();
      return;
    }

    // Plus: add another element of the same type as whatever is selected.
    if (isTaskSelected) addTaskToActiveFeature();
    else shortcut('add-feature');
  }

  function deleteActiveItem() {
    const active = currentActive();
    if (!active) return;
    if (active.hasAttribute('data-feature-id')) {
      const featureId = active.getAttribute('data-feature-id');
      window.dispatchEvent(new CustomEvent('board-delete-feature', { detail: { featureId } }));
      return;
    }
    const taskId = active.getAttribute('wire:sort:item');
    if (taskId) {
      window.dispatchEvent(new CustomEvent('board-delete-task', { detail: { taskId } }));
    }
  }

  // ── Epics list reorder ────────────────────────────────────────────────────

  function moveEpicItem(delta) {
    const active = currentActive();
    if (!active) return;
    const itemId = active.getAttribute('wire:sort:item');
    if (!itemId) return;
    const items = Array.from(document.querySelectorAll('[data-selectable]'));
    const domIndex = items.indexOf(active);
    if (domIndex < 0) return;
    const currentIndex = pendingIndexFor(itemId) ?? domIndex;
    const newIndex = Math.max(0, Math.min(items.length - 1, currentIndex + delta));
    if (newIndex === currentIndex) return;
    setPendingIndex(itemId, newIndex);
    window.dispatchEvent(new CustomEvent('epic-reorder', { detail: { itemId, position: newIndex } }));
  }

  // ── Board reorder ─────────────────────────────────────────────────────────

  function moveBoardItem(delta) {
    const active = currentActive();
    if (!active) return;

    if (active.hasAttribute('data-feature-id')) {
      const featureId = active.getAttribute('data-feature-id');
      const headers = Array.from(document.querySelectorAll('[data-feature-id]'));
      const domIndex = headers.indexOf(active);
      if (domIndex < 0) return;
      const currentIndex = pendingIndexFor(featureId) ?? domIndex;
      const newIndex = Math.max(0, Math.min(headers.length - 1, currentIndex + delta));
      if (newIndex === currentIndex) return;
      setPendingIndex(featureId, newIndex);
      window.dispatchEvent(new CustomEvent('board-feature-reorder', { detail: { featureId, position: newIndex } }));
      return;
    }

    const taskId = active.getAttribute('wire:sort:item');
    const list = active.closest('ul');
    if (taskId && list) {
      const tasks = Array.from(list.querySelectorAll('[data-selectable]'));
      const domIndex = tasks.indexOf(active);
      if (domIndex < 0) return;
      const currentIndex = pendingIndexFor(taskId) ?? domIndex;
      const newIndex = Math.max(0, Math.min(tasks.length - 1, currentIndex + delta));
      if (newIndex === currentIndex) return;
      setPendingIndex(taskId, newIndex);
      window.dispatchEvent(new CustomEvent('board-task-reorder', { detail: { taskId, position: newIndex } }));
    }
  }

  // ── Kanban reorder ────────────────────────────────────────────────────────

  function moveKanbanItem(delta) {
    const active = currentActive();
    if (!active) return;

    if (active.hasAttribute('data-feature-id')) {
      // The feature label div is selectable, but the feature BLOCK (the
      // wire:sort:item) is its parent <li> — find that block's position among
      // its own siblings in this column's feature list.
      const featureId = active.getAttribute('data-feature-id');
      const block = active.closest('li[wire\\:sort\\:item]');
      const list = block ? block.closest('ul') : null;
      if (!block || !list) return;
      const blocks = Array.from(list.children).filter((el) => el.hasAttribute('wire:sort:item'));
      const domIndex = blocks.indexOf(block);
      if (domIndex < 0) return;
      const currentIndex = pendingIndexFor(featureId) ?? domIndex;
      const newIndex = Math.max(0, Math.min(blocks.length - 1, currentIndex + delta));
      if (newIndex === currentIndex) return;
      const statusValue = list.getAttribute('wire:sort:group-id');
      if (!statusValue) return;
      setPendingIndex(featureId, newIndex);
      window.dispatchEvent(new CustomEvent('kanban-feature-reorder', { detail: { featureId, position: newIndex, statusValue } }));
      return;
    }

    const taskId = active.getAttribute('wire:sort:item');
    const list = active.closest('ul');
    if (!taskId || !list) return;
    // Match Livewire's own wire:sort indexing: only elements carrying
    // wire:sort:item count toward position (feature headers don't).
    const tasks = Array.from(list.children).filter((el) => el.hasAttribute('wire:sort:item'));
    const domIndex = tasks.indexOf(active);
    if (domIndex < 0) return;
    const currentIndex = pendingIndexFor(taskId) ?? domIndex;
    const newIndex = Math.max(0, Math.min(tasks.length - 1, currentIndex + delta));
    if (newIndex === currentIndex) return;
    const statusValue = list.getAttribute('wire:sort:group-id');
    if (!statusValue) return;
    setPendingIndex(taskId, newIndex);
    window.dispatchEvent(new CustomEvent('kanban-task-reorder', { detail: { taskId, position: newIndex, statusValue } }));
  }

  function toggleActiveFeatureCollapse() {
    const active = currentActive();
    if (!active) return;
    let featureId = active.getAttribute('data-feature-id');
    const isOnTask = !featureId;
    if (!featureId) {
      const card = active.closest('[data-board-feature-card]');
      const header = card && card.querySelector('[data-feature-id]');
      if (header) featureId = header.getAttribute('data-feature-id');
    }
    if (!featureId) return;
    window.dispatchEvent(new CustomEvent('board-toggle-collapse', { detail: { featureId } }));
    if (isOnTask) {
      // after collapsing, move selection to the feature header so it stays visible
      setTimeout(function () {
        const header = document.querySelector('[data-feature-id="' + featureId + '"]');
        if (header && isDomVisible(header)) setActive(header);
      }, 0);
    }
  }

  window.addEventListener('board-collapse-all', function (e) {
    _boardCollapseAll = e.detail.collapsed;
  });

  document.addEventListener('board-sorted', function (e) {
    const { id, type } = e.detail;
    clearPendingIndex(id);
    setTimeout(function () {
      let el = null;
      if (type === 'task') {
        el = Array.from(document.querySelectorAll('[data-selectable]'))
          .find(function (n) { return n.getAttribute('wire:sort:item') === id; });
      } else {
        el = document.querySelector('[data-feature-id="' + id + '"]');
      }
      if (el) setActive(el);
    }, 0);
  });

  document.addEventListener('epic-sorted', function (e) {
    const { id } = e.detail;
    clearPendingIndex(id);
    setTimeout(function () {
      const el = Array.from(document.querySelectorAll('[data-selectable]'))
        .find(function (n) { return n.getAttribute('wire:sort:item') === id; });
      if (el) setActive(el);
    }, 0);
  });

  // ── Filter panel ──────────────────────────────────────────────────────────

  let savedActiveBeforeFilter = null;

  function isFilterOpen() {
    return !!document.querySelector('[data-filter-panel]');
  }

  function focusFilterPanel() {
    const panel = document.querySelector('[data-filter-panel]');
    if (!panel) return;
    // Focus the first unchecked checkbox, or the first checkbox if all are checked
    const boxes = Array.from(panel.querySelectorAll('input[type="checkbox"]'));
    if (!boxes.length) return;
    const firstUnchecked = boxes.find((b) => !b.checked);
    (firstUnchecked || boxes[0]).focus();
  }

  function openFilter() {
    savedActiveBeforeFilter = currentActive();
    shortcut('toggle-filters');
    // Wait for Livewire to render the panel, then focus
    setTimeout(focusFilterPanel, 80);
  }

  function closeFilter() {
    shortcut('toggle-filters');
    if (savedActiveBeforeFilter) {
      setActive(savedActiveBeforeFilter);
      savedActiveBeforeFilter = null;
    }
  }

  function moveFilterFocus(delta) {
    const panel = document.querySelector('[data-filter-panel]');
    if (!panel) return;
    const boxes = Array.from(panel.querySelectorAll('input[type="checkbox"]'));
    if (!boxes.length) return;
    const idx = boxes.indexOf(document.activeElement);
    if (idx < 0) { boxes[0].focus(); return; }
    boxes[Math.max(0, Math.min(boxes.length - 1, idx + delta))].focus();
  }

  // ── Test matrices ─────────────────────────────────────────────────────────

  const matrixRows = () => Array.from(document.querySelectorAll('[data-grid] tbody tr[data-scenario]'));
  const matrixCellsOf = (row) => Array.from(row.querySelectorAll('[data-cell]'));
  const matrixComments = () => Array.from(document.querySelectorAll('[data-comment]'));

  function testMatricesUrl() {
    const el = document.querySelector('[data-test-matrices-url]');
    return el ? el.getAttribute('data-test-matrices-url') : null;
  }

  // Arrows move over the cells of the grid; down from the last row goes on to the comments, and up from the first one back.
  function moveMatrixSelection(deltaRow, deltaColumn) {
    const rows = matrixRows();
    const comments = matrixComments();
    const active = currentActive();

    if (active && active.hasAttribute('data-comment')) {
      const at = comments.indexOf(active);
      if (deltaRow > 0 && comments[at + 1]) setActive(comments[at + 1]);
      if (deltaRow < 0) {
        if (at > 0) setActive(comments[at - 1]);
        else if (rows.length) { const cells = matrixCellsOf(rows[rows.length - 1]); if (cells.length) setActive(cells[0]); }
      }
      return;
    }

    if (!active || !active.hasAttribute('data-cell')) {
      const first = document.querySelector('[data-grid] [data-cell]') || comments[0];
      if (first) setActive(first);
      return;
    }

    const row = active.closest('tr');
    const rowIndex = rows.indexOf(row);
    const columnIndex = matrixCellsOf(row).indexOf(active);
    if (deltaRow > 0 && rowIndex === rows.length - 1) { if (comments[0]) setActive(comments[0]); return; }

    const nextCells = matrixCellsOf(rows[Math.max(0, Math.min(rows.length - 1, rowIndex + deltaRow))]);
    const target = nextCells[Math.max(0, Math.min(nextCells.length - 1, columnIndex + deltaColumn))];
    if (target) setActive(target);
  }

  // Home and End: the first and the last context in the row.
  function moveMatrixToEdge(last) {
    const active = currentActive();
    if (!active || !active.hasAttribute('data-cell')) return;
    const cells = matrixCellsOf(active.closest('tr'));
    if (cells.length) setActive(cells[last ? cells.length - 1 : 0]);
  }

  // The page is redrawn after almost every action, which drops the selection. It is put back on the same cell or
  // comment (or the first cell when that one is gone).
  function restoreMatrixSelection() {
    if (getView() !== 'test-matrix') return;
    const keep = lastMatrixKey && (document.querySelector('[data-cell="' + lastMatrixKey + '"]') || document.querySelector('[data-comment="' + lastMatrixKey + '"]'));
    const target = keep || document.querySelector('[data-grid] [data-cell]') || document.querySelector('[data-comment]');
    if (target) setActive(target);
  }

  window.addEventListener('test-matrix-refreshed', function () { setTimeout(restoreMatrixSelection, 0); });

  let matrixObserver = null;

  function watchMatrixSelection() {
    if (matrixObserver) { matrixObserver.disconnect(); matrixObserver = null; }
    const root = document.querySelector('[data-view="test-matrix"]');
    if (!root) return;
    let scheduled = false;
    matrixObserver = new MutationObserver(function () {
      if (scheduled) return;
      scheduled = true;
      setTimeout(function () {
        scheduled = false;
        if (!document.querySelector('[data-selectable].active')) restoreMatrixSelection();
      }, 0);
    });
    matrixObserver.observe(root, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
  }

  // ── Per-view handlers ─────────────────────────────────────────────────────

  function handlePerView(e) {
    let handled = false;
    const view = getView();

    if (view === 'epics-index') {
      if (e.key === 'ArrowUp') {
        e.preventDefault(); handled = true;
        if (e.shiftKey) moveEpicItem(-1); else moveSelection(-1);
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault(); handled = true;
        if (e.shiftKey) moveEpicItem(1); else moveSelection(1);
      }
      if (e.key === 'Enter') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a && a.dataset.href) goto(a.dataset.href);
      }
      if (e.key === 'e') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-edit-btn]'); if (btn) btn.click(); }
      }
      if (e.key === 'n' || e.key === '+' || e.code === 'Equal') { e.preventDefault(); handled = true; shortcut('new-epic'); }
      if (e.key === 'f') {
        e.preventDefault(); handled = true;
        if (isFilterOpen()) { closeFilter(); } else { openFilter(); }
      }
    }

    if (view === 'epic-board') {
      const mode = getBoardMode();

      if (mode === 'kanban') {
        if (e.key === 'ArrowUp') {
          e.preventDefault(); handled = true;
          if (e.shiftKey) moveKanbanItem(-1); else moveInKanbanColumn(-1);
        }
        if (e.key === 'ArrowDown') {
          e.preventDefault(); handled = true;
          if (e.shiftKey) moveKanbanItem(1); else moveInKanbanColumn(1);
        }
        if (e.key === 'ArrowLeft')  { e.preventDefault(); handled = true; moveKanbanColumn(-1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); handled = true; moveKanbanColumn(1); }
      } else {
        if (e.key === 'ArrowUp') {
          e.preventDefault(); handled = true;
          if (e.shiftKey && mode === 'board') moveBoardItem(-1);
          else moveSelection(-1);
        }
        if (e.key === 'ArrowDown') {
          e.preventDefault(); handled = true;
          if (e.shiftKey && mode === 'board') moveBoardItem(1);
          else moveSelection(1);
        }
      }

      if (e.key === 'Enter') { e.preventDefault(); handled = true; openActiveTask(); }
      if (e.key === 'Delete') { e.preventDefault(); handled = true; deleteActiveItem(); }
      if (e.key === 't' && mode === 'board' && !e.shiftKey) { e.preventDefault(); handled = true; toggleActiveFeatureCollapse(); }
      if (e.key === 'T' && mode === 'board') {
        e.preventDefault(); handled = true;
        _boardCollapseAll = !_boardCollapseAll;
        window.dispatchEvent(new CustomEvent('board-collapse-all', { detail: { collapsed: _boardCollapseAll } }));
      }
      if (e.key === 'e') { e.preventDefault(); handled = true; shortcut('edit-epic'); }
      if (e.key === '1') { e.preventDefault(); handled = true; shortcut('view-board'); }
      if (e.key === '2') { e.preventDefault(); handled = true; shortcut('view-kanban'); }
      if (e.key === 'n') { e.preventDefault(); handled = true; shortcut('add-feature'); }
      // "+" is Shift+"=" on most layouts, so the physical Equal key (with/without
      // Shift) is used to tell a plain "+" apart from a "Shift + +" press.
      if (e.key === '+' || e.code === 'Equal') {
        e.preventDefault(); handled = true;
        addContextual(e.shiftKey);
      }
      if (e.key === 'f') {
        e.preventDefault(); handled = true;
        if (isFilterOpen()) { closeFilter(); } else { openFilter(); }
      }
    }

    if (view === 'epic-board') {
      if (e.key === 'm') { const url = testMatricesUrl(); if (url) { e.preventDefault(); handled = true; goto(url); } }
    }

    if (view === 'test-matrices-index') {
      if (e.key === 'ArrowUp')   { e.preventDefault(); handled = true; moveSelection(-1); }
      if (e.key === 'ArrowDown') { e.preventDefault(); handled = true; moveSelection(1); }
      if (e.key === 'Enter') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a && a.dataset.href) goto(a.dataset.href);
      }
      if (e.key === 'n' || e.key === '+' || e.code === 'Equal') { e.preventDefault(); handled = true; shortcut('new-test-matrix'); }
      if (e.key === 'Delete') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-delete-btn]'); if (btn) btn.click(); }
      }
    }

    if (view === 'test-matrix') {
      const active = currentActive();
      const cell = active && active.hasAttribute('data-cell') ? active : null;
      const comment = active && active.hasAttribute('data-comment') ? active : null;
      const [scenarioId, contextId] = cell ? cell.getAttribute('data-cell').split('/') : [null, null];
      // The page does the work: it listens for this event and runs the matching action.
      const act = (action) => window.dispatchEvent(new CustomEvent('test-matrix-action', {
        detail: { action, scenarioId, contextId, commentId: comment ? comment.getAttribute('data-comment') : null },
      }));
      const run = (action) => { e.preventDefault(); handled = true; act(action); };

      if (e.key === 'ArrowUp')    { e.preventDefault(); handled = true; if (e.shiftKey) { if (cell) act('move-scenario-up'); } else moveMatrixSelection(-1, 0); }
      if (e.key === 'ArrowDown')  { e.preventDefault(); handled = true; if (e.shiftKey) { if (cell) act('move-scenario-down'); } else moveMatrixSelection(1, 0); }
      if (e.key === 'ArrowLeft')  { e.preventDefault(); handled = true; if (e.shiftKey) { if (cell) act('move-context-left'); } else moveMatrixSelection(0, -1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); handled = true; if (e.shiftKey) { if (cell) act('move-context-right'); } else moveMatrixSelection(0, 1); }
      if (e.key === 'Home') { e.preventDefault(); handled = true; moveMatrixToEdge(false); }
      if (e.key === 'End')  { e.preventDefault(); handled = true; moveMatrixToEdge(true); }

      if (e.key === ' ' && cell) run('cycle-status');
      if (e.key === 'p' && cell) run('status-passed');
      if (e.key === 'x' && cell) run('status-failed');
      if (e.key === 'u' && cell) run('status-untested');
      if (e.key === 'r' && cell) run('toggle-relevant');
      if (e.key === 'c' && cell) run('new-comment');
      if (e.key === 'Enter') { if (cell) run('open-cell'); else if (comment) run('open-comment'); }
      if (e.key === 'e') { if (cell) run('edit-scenario'); else if (comment) run('open-comment'); else run('edit-matrix'); }
      if (e.key === 'E' && cell) run('edit-context');
      if (e.key === 'n' || e.key === '+') run('new-scenario');
      if (e.key === 'N') run('new-context');
      if (e.key === 'm') run('edit-matrix');
      if (e.key === 'Delete') {
        if (comment) run('delete-comment');
        else if (cell) run(e.shiftKey ? 'delete-context' : 'delete-scenario');
      }
    }

    if (view === 'templates-index') {
      if (e.key === 'ArrowUp')   { e.preventDefault(); handled = true; moveSelection(-1); }
      if (e.key === 'ArrowDown') { e.preventDefault(); handled = true; moveSelection(1); }
      if (e.key === 'Enter') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a && a.dataset.href) goto(a.dataset.href);
      }
      if (e.key === 'e') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-edit-btn]'); if (btn) btn.click(); }
      }
      if (e.key === 'Delete') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-delete-btn]'); if (btn) btn.click(); }
      }
      if (e.key === 'n' || e.key === '+' || e.code === 'Equal') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        const inEpicTemplates = !!a && !!a.closest('[data-list="epic-templates"]');
        shortcut(inEpicTemplates ? 'new-epic-template' : 'new-feature-template');
      }
      if (e.key === 'c') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-copy-btn]'); if (btn) btn.click(); }
      }
      if (e.key === 'v') { e.preventDefault(); handled = true; shortcut('paste-feature-template'); }
    }

    if (view === 'feature-template') {
      if (e.key === 'ArrowUp')   { e.preventDefault(); handled = true; moveSelection(-1); }
      if (e.key === 'ArrowDown') { e.preventDefault(); handled = true; moveSelection(1); }
      if (e.key === 'Enter' || e.key === 'e') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-edit-btn]'); if (btn) btn.click(); }
      }
      if (e.key === 'Delete') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-delete-btn]'); if (btn) btn.click(); }
      }
      if (e.key === 'n' || e.key === '+' || e.code === 'Equal') { e.preventDefault(); handled = true; shortcut('add-template-task'); }
    }

    if (view === 'epic-template') {
      if (e.key === 'ArrowUp')   { e.preventDefault(); handled = true; moveSelection(-1); }
      if (e.key === 'ArrowDown') { e.preventDefault(); handled = true; moveSelection(1); }
      if (e.key === 'Enter') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a && a.dataset.href) goto(a.dataset.href);
      }
      if (e.key === 'Delete') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-delete-btn]'); if (btn) btn.click(); }
      }
      if (e.key === 'n' || e.key === '+' || e.code === 'Equal') { e.preventDefault(); handled = true; shortcut('add-feature-template'); }
      if (e.key === 'c') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-copy-btn]'); if (btn) btn.click(); }
      }
      if (e.key === 'x') {
        e.preventDefault(); handled = true;
        const a = currentActive();
        if (a) { const btn = a.querySelector('[data-cut-btn]'); if (btn) btn.click(); }
      }
      if (e.key === 'v') { e.preventDefault(); handled = true; shortcut('paste-feature-template'); }
    }

    return handled;
  }

  // ── Main listener ─────────────────────────────────────────────────────────

  window.addEventListener('keydown', (e) => {
    if (e.defaultPrevented) return;

    if (e.metaKey || e.ctrlKey || e.altKey) return;

    // Filter panel intercepts arrows/escape/f before isTyping check
    if (isFilterOpen()) {
      if (e.key === 'ArrowDown') { e.preventDefault(); moveFilterFocus(1); return; }
      if (e.key === 'ArrowUp')   { e.preventDefault(); moveFilterFocus(-1); return; }
      if (e.key === 'Escape')    { e.preventDefault(); closeFilter(); return; }
      if (e.key === 'f')         { e.preventDefault(); closeFilter(); return; }
      // Allow Space (toggle checkbox) and Tab to propagate; block other shortcuts
      if (e.key !== ' ' && e.key !== 'Tab') return;
      return;
    }

    if (isTyping(e)) return;

    if (e.key === '?') { e.preventDefault(); showHelp(); return; }

    const view = getView();

    if (e.key === 'Escape') {
      const hadHelp = !!document.getElementById('kb-help-overlay');
      hideHelp();
      const hadOverlay = Array.from(document.querySelectorAll('[data-fullscreen-overlay]')).some((el) => el.offsetParent !== null);
      if (!hadHelp && !hadOverlay && !_popoverWasOpenOnEscape && !_modalWasOpenOnEscape && window.history && window.history.length > 1) {
        e.preventDefault();
        window.history.back();
      }
      return;
    }

    // While a dialog is open on the test matrix pages nothing underneath reacts to the keyboard.
    if ((view === 'test-matrix' || view === 'test-matrices-index') && document.querySelector('dialog[open]')) return;

    // Sequences: second key — checked before per-view handlers so "g" then "e"/"s"
    // always navigates, even on views where "e" is also a per-view shortcut (e.g. edit).
    if (seq === 'g' && e.key === 'e') { e.preventDefault(); if (routes.epics) goto(routes.epics); resetSeq(); return; }
    if (seq === 'g' && e.key === 't') { e.preventDefault(); if (routes.templates) goto(routes.templates); resetSeq(); return; }
    if (seq === 'g' && e.key === 'd') { e.preventDefault(); if (routes.dashboard) goto(routes.dashboard); resetSeq(); return; }
    if (seq === 'g' && e.key === 'm' && testMatricesUrl()) { e.preventDefault(); goto(testMatricesUrl()); resetSeq(); return; }
    if (seq === 'g' && e.key === 's') { e.preventDefault(); if (routes.settings) goto(routes.settings); resetSeq(); return; }

    if (handlePerView(e)) return;

    // Sequences: first key
    if (e.key === 'g') {
      seq = 'g';
      if (seqTimer) clearTimeout(seqTimer);
      seqTimer = setTimeout(resetSeq, 500);
      return;
    }
  });

  // ── Init ──────────────────────────────────────────────────────────────────

  function init() {
    const view = getView();
    if (view === 'epics-index') selectFirst('[data-list="epics"]');
    if (view === 'test-matrices-index') selectFirst('[data-list="test-matrices"]');
    if (view === 'test-matrix') { watchMatrixSelection(); const first = document.querySelector('[data-grid] [data-cell]') || document.querySelector('[data-comment]'); if (first) setActive(first); }
    if (view === 'templates-index') selectFirst('[data-list="feature-templates"]') || selectFirst('[data-list="epic-templates"]');
    const highlighted = document.querySelector('[data-selectable][data-highlighted]');
    if (highlighted) setTimeout(() => setActive(highlighted), 0);
  }

  if (document.readyState === 'loading') {
    window.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  window.addEventListener('livewire:navigated', init);
})();
