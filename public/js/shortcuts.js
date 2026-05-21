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
  const resetSeq = () => { seq = ''; if (seqTimer) { clearTimeout(seqTimer); seqTimer = null; } };

  const routes = window.AppRoutes || {};

  // ── Help overlay ──────────────────────────────────────────────────────────

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
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">g</kbd> → <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">s</kbd> &nbsp; Go to Settings</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">?</kbd> &nbsp; This help</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Esc</kbd> &nbsp; Back / close</div>
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Epics list</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↑</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↓</kbd> &nbsp; Select epic</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Enter</kbd> &nbsp; Open board</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">e</kbd> &nbsp; Edit selected</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">n</kbd> &nbsp; New epic</div>
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Epic board</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↑</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↓</kbd> &nbsp; Select task</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">←</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">→</kbd> &nbsp; Switch column (kanban)</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Enter</kbd> &nbsp; Open selected task</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">1</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">2</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">3</kbd> &nbsp; Board / Kanban / Queue</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">f</kbd> &nbsp; Toggle filters</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">n</kbd> &nbsp; Add feature</div>
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Filter panel</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↑</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">↓</kbd> &nbsp; Navigate options</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Space</kbd> &nbsp; Toggle filter</div>
            <div><kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">Esc</kbd> / <kbd style="background:#27272a;border:1px solid #52525b;border-radius:4px;padding:1px 5px">f</kbd> &nbsp; Close &amp; return</div>
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

  function setActive(el) {
    document.querySelectorAll('[data-selectable].active').forEach((n) => n.classList.remove('active'));
    if (el) { el.classList.add('active'); el.scrollIntoView({ block: 'nearest' }); }
  }

  function moveSelection(delta) {
    const items = Array.from(document.querySelectorAll('[data-selectable]'));
    if (!items.length) return;
    let index = items.findIndex((n) => n.classList.contains('active'));
    if (index < 0) { setActive(items[delta < 0 ? 0 : 0]); return; }
    const next = Math.max(0, Math.min(items.length - 1, index + delta));
    setActive(items[next]);
  }

  function currentActive() { return document.querySelector('[data-selectable].active'); }

  function selectFirst(selector) {
    const container = document.querySelector(selector);
    if (!container) return;
    const items = container.querySelectorAll('[data-selectable]');
    if (items.length) setActive(items[0]);
  }

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

  // ── Per-view handlers ─────────────────────────────────────────────────────

  function handlePerView(e) {
    let handled = false;
    const view = getView();

    if (view === 'epics-index') {
      if (e.key === 'ArrowUp') { e.preventDefault(); handled = true; moveSelection(-1); }
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
      if (e.key === 'n') { e.preventDefault(); handled = true; shortcut('new-epic'); }
    }

    if (view === 'epic-board') {
      const mode = getBoardMode();

      if (mode === 'kanban') {
        if (e.key === 'ArrowUp')    { e.preventDefault(); handled = true; moveInKanbanColumn(-1); }
        if (e.key === 'ArrowDown')  { e.preventDefault(); handled = true; moveInKanbanColumn(1); }
        if (e.key === 'ArrowLeft')  { e.preventDefault(); handled = true; moveKanbanColumn(-1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); handled = true; moveKanbanColumn(1); }
      } else {
        if (e.key === 'ArrowUp')   { e.preventDefault(); handled = true; moveSelection(-1); }
        if (e.key === 'ArrowDown') { e.preventDefault(); handled = true; moveSelection(1); }
      }

      if (e.key === 'Enter') { e.preventDefault(); handled = true; openActiveTask(); }
      if (e.key === '1') { e.preventDefault(); handled = true; shortcut('view-board'); }
      if (e.key === '2') { e.preventDefault(); handled = true; shortcut('view-kanban'); }
      if (e.key === '3') { e.preventDefault(); handled = true; shortcut('view-sort'); }
      if (e.key === 'n') { e.preventDefault(); handled = true; shortcut('add-feature'); }
      if (e.key === 'f') {
        e.preventDefault(); handled = true;
        if (isFilterOpen()) { closeFilter(); } else { openFilter(); }
      }
    }

    return handled;
  }

  // ── Main listener ─────────────────────────────────────────────────────────

  window.addEventListener('keydown', (e) => {
    if (e.defaultPrevented) return;
    if (e.metaKey || e.ctrlKey || e.altKey) return;

    // Filter panel intercepts arrows/escape/f before isTyping check
    if (getView() === 'epic-board' && isFilterOpen()) {
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

    if (e.key === 'Escape') {
      const hadHelp = !!document.getElementById('kb-help-overlay');
      hideHelp();
      if (!hadHelp && window.history && window.history.length > 1) {
        e.preventDefault();
        window.history.back();
      }
      return;
    }

    if (handlePerView(e)) return;

    // Sequences: second key
    if (seq === 'g' && e.key === 'e') { e.preventDefault(); if (routes.epics) goto(routes.epics); resetSeq(); return; }
    if (seq === 'g' && e.key === 's') { e.preventDefault(); if (routes.settings) goto(routes.settings); resetSeq(); return; }

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
  }

  if (document.readyState === 'loading') {
    window.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  window.addEventListener('livewire:navigated', init);
})();
