(function () {
  const isTyping = (e) => {
    const t = e.target;
    if (!t) return false;
    const tag = (t.tagName || '').toLowerCase();
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
      <div style="background:#18181b;color:#e4e4e7;padding:24px;border-radius:12px;max-width:560px;width:92%;max-height:90vh;overflow:auto;border:1px solid #3f3f46">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <h2 style="font-size:16px;font-weight:600;margin:0">Keyboard Shortcuts</h2>
          <button id="kb-help-close" style="border:1px solid #3f3f46;background:#27272a;color:#a1a1aa;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px">Esc</button>
        </div>
        <div style="font-size:13px;line-height:1.7;display:grid;grid-template-columns:1fr 1fr;gap:16px 24px">
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Global</div>
            <div><kbd>g</kbd> → <kbd>e</kbd> &nbsp; Go to Epics</div>
            <div><kbd>g</kbd> → <kbd>s</kbd> &nbsp; Go to Settings</div>
            <div><kbd>?</kbd> &nbsp; This help</div>
            <div><kbd>Esc</kbd> &nbsp; Back / close</div>
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Epics list</div>
            <div><kbd>↑</kbd> / <kbd>↓</kbd> &nbsp; Select epic</div>
            <div><kbd>Enter</kbd> &nbsp; Open board</div>
            <div><kbd>e</kbd> &nbsp; Edit selected</div>
            <div><kbd>n</kbd> &nbsp; New epic</div>
          </div>
          <div>
            <div style="font-weight:600;color:#a1a1aa;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Epic board</div>
            <div><kbd>1</kbd> &nbsp; Board view</div>
            <div><kbd>2</kbd> &nbsp; Kanban view</div>
            <div><kbd>3</kbd> &nbsp; AI Queue</div>
            <div><kbd>f</kbd> &nbsp; Toggle filters</div>
            <div><kbd>n</kbd> &nbsp; Add feature</div>
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
    if (index < 0) index = 0;
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

  function getView() {
    const el = document.querySelector('[data-view]');
    return el ? el.getAttribute('data-view') : null;
  }

  function shortcut(key) {
    const el = document.querySelector('[data-shortcut="' + key + '"]');
    if (el) el.click();
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
      if (e.key === '1') { e.preventDefault(); handled = true; shortcut('view-board'); }
      if (e.key === '2') { e.preventDefault(); handled = true; shortcut('view-kanban'); }
      if (e.key === '3') { e.preventDefault(); handled = true; shortcut('view-sort'); }
      if (e.key === 'f') { e.preventDefault(); handled = true; shortcut('toggle-filters'); }
      if (e.key === 'n') { e.preventDefault(); handled = true; shortcut('add-feature'); }
    }

    return handled;
  }

  // ── Main listener ─────────────────────────────────────────────────────────

  window.addEventListener('keydown', (e) => {
    if (e.defaultPrevented) return;
    if (e.metaKey || e.ctrlKey || e.altKey) return;
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

    // Sequence: second key
    if (seq === 'g' && e.key === 'e') { e.preventDefault(); if (routes.epics) goto(routes.epics); resetSeq(); return; }
    if (seq === 'g' && e.key === 's') { e.preventDefault(); if (routes.settings) goto(routes.settings); resetSeq(); return; }

    // Sequence: first key
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
