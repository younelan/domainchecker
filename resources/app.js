(function(){
  // Theme handling: read saved theme or system preference
  function applyTheme(theme){
    theme = (theme === 'light') ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    if (toggleBtn) {
      toggleBtn.setAttribute('data-theme', theme);
      toggleBtn.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
      toggleBtn.textContent = theme === 'light' ? 'Light' : 'Dark';
    }
    localStorage.setItem('domaincheck:theme', theme);
  }

  const stored = localStorage.getItem('domaincheck:theme');
  const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
  const savedTheme = stored || (prefersLight ? 'light' : 'dark');
  let toggleBtn = null;

  // apply saved theme immediately (in case toggle button isn't found yet)
  try { applyTheme(savedTheme); } catch (e) { /* ignore */ }

  // Minimal fetch wrapper
  function api(action, opts = {}){
    const url = new URL(location.href);
    url.searchParams.set('action', action);
    if (opts.qs) Object.keys(opts.qs).forEach(k=>url.searchParams.set(k, opts.qs[k]));

    const fetchOpts = {method: opts.method || 'GET', headers: {'Accept':'application/json'}};
    if (opts.body) {
      fetchOpts.method = 'POST';
      fetchOpts.headers['Content-Type'] = 'application/json';
      fetchOpts.body = JSON.stringify(opts.body);
    }
    return fetch(url.toString(), fetchOpts).then(r => r.json());
  }

  function $(sel){return document.querySelector(sel)}
  function renderResultRow(name, tld){
    const id = 'r-'+name+'-'+tld;
    let el = document.getElementById(id);
    if (!el){
  el = document.createElement('div'); el.id = id; el.className='result';
  // split SLD and TLD for styling: SLD gets .sld (dark red)
  el.innerHTML = `<span class="domain"><span class="sld">${name}</span><span class="tld">.${tld}</span></span> <span class="status status-checking"><span class="icon spinner">` +
    `<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.15" stroke-width="3"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></span><span class="label">checking…</span></span>`;
      $('#results').appendChild(el);
    }
    return el;
  }

  document.getElementById('searchForm').addEventListener('submit', function(e){
    e.preventDefault();
    let name = document.getElementById('q').value.trim();
    // normalize: if user pasted a full domain like example.com, strip known tld
    try {
      const parts = name.replace(/^https?:\/\//i,'').split('/')[0].split('.');
      if (parts.length > 1) {
        const last = parts[parts.length-1].toLowerCase();
        const known = window.KNOWN_TLDS || [];
        if (known.indexOf(last) !== -1) {
          // take the left-most label before tld (sld)
          name = parts.slice(0, parts.length-1).join('.');
        }
      }
    } catch (err) { /* ignore normalization errors */ }
  const tlds = Array.from(document.querySelectorAll('input[name="tlds[]"]:checked')).map(i=>i.value);
  // clear previous results so left column only contains current search
  const resultsNode = document.getElementById('results');
  if (resultsNode) resultsNode.innerHTML = '';
    if (!name || tlds.length===0) return;

    const promises = tlds.map(tld => {
      const row = renderResultRow(name, tld);
  const statusEl = row.querySelector('.status');
  // reset to spinner state
  statusEl.innerHTML = `<span class="icon spinner"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.15" stroke-width="3"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></span><span class="label">checking…</span>`;
  statusEl.className = 'status status-checking';
      return api('check', {qs:{name, tld}}).then(json => {
        const ok = json.result && json.result.available;
        if (ok) {
          statusEl.innerHTML = `<span class="icon checkmark"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg></span><span class="label">available</span>`;
          statusEl.className = 'status status-available';
        } else {
          statusEl.innerHTML = `<span class="icon xmark"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M6 18L18 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg></span><span class="label">taken</span>`;
          statusEl.className = 'status status-taken';
        }
        return {tld, available: !!ok};
      }).catch(err=>{
        statusEl.innerHTML = `<span class="icon xmark"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M6 18L18 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg></span><span class="label">error</span>`;
        statusEl.className = 'status status-error';
        return {tld, available: false};
      });
    });

    Promise.all(promises).then(results => {
      // save summary
      const available = results.filter(r=>r.available).map(r=>r.tld);
      api('save', {body: {query: name, available}}).then(()=>api('history').then(h=>{
        renderHistory(h.history || []);
      }));
    });
  });

  // Toggle select all / clear for TLDs
  const toggleTldsBtn = document.getElementById('toggleTlds');
  if (toggleTldsBtn) {
    toggleTldsBtn.addEventListener('click', function(){
      const boxes = Array.from(document.querySelectorAll('input[name="tlds[]"]'));
      const anyUnchecked = boxes.some(b => !b.checked);
      boxes.forEach(b => b.checked = anyUnchecked);
      toggleTldsBtn.textContent = anyUnchecked ? 'Clear' : 'Select all';
    });
  }

  function renderHistory(list){
    const node = $('#history');
    // preserve existing header if present
    const header = node.querySelector('.history-header');
    node.innerHTML = '';
    if (header) node.appendChild(header);
    if (!list || list.length===0) { node.innerHTML += '<div class="empty">no history</div>'; return; }
    const ul = document.createElement('ul');
    // small color map for known tlds
    const colorMap = { com: '#2563eb', net: '#7c3aed', org: '#16a34a', io: '#f97316', dev: '#e11d48' };
    list.forEach(item=>{
  const li = document.createElement('li'); li.className = 'history-item';
      const right = document.createElement('div');
      right.className = 'badges';

      // SLD pill (domain without tld)
  const sld = document.createElement('span');
  sld.className = 'pill sld';
  sld.textContent = item.query;
      right.appendChild(sld);

      const available = item.available || [];
      const max = 3;
      available.slice(0, max).forEach(t=>{
        const p = document.createElement('span'); p.className='pill'; p.textContent = t;
        const color = colorMap[t] || '#64748b';
        p.style.background = color + '22';
        p.style.color = color;
        p.style.border = '1px solid ' + color + '33';
        right.appendChild(p);
      });

      if (available.length > max) {
        const more = document.createElement('button');
        more.className = 'pill more'; more.textContent = '+' + (available.length - max) + ' more';
        more.style.background = 'transparent';
        more.addEventListener('click', function(){
          right.innerHTML = '';
          right.appendChild(sld);
          available.forEach(t=>{ const p = document.createElement('span'); p.className='pill'; p.textContent = t; const color = colorMap[t] || '#64748b'; p.style.background = color + '22'; p.style.color = color; p.style.border = '1px solid ' + color + '33'; right.appendChild(p); });
        });
        right.appendChild(more);
      }

  li.appendChild(right);
      ul.appendChild(li);
    });
    node.appendChild(ul);
    // clear button wiring (only once)
    const clearBtn = document.getElementById('clearHistory');
    if (clearBtn && !clearBtn._wired) {
      clearBtn.addEventListener('click', function(){
        if (!confirm('Clear history?')) return;
        api('clear', {method: 'POST'}).then(()=>api('history').then(h=>{ renderHistory(h.history); }));
      });
      clearBtn._wired = true;
    }
  }

  // initial history load
  api('history').then(r=>{ renderHistory(r.history); });

  // wire theme toggle
  toggleBtn = document.getElementById('themeToggle');
  if (toggleBtn){
    // ensure button shows current state
    toggleBtn.textContent = (localStorage.getItem('domaincheck:theme') === 'light' || (!localStorage.getItem('domaincheck:theme') && prefersLight)) ? '☀' : '🌙';
    toggleBtn.setAttribute('aria-label', 'Toggle theme');
    toggleBtn.addEventListener('click', function(){
      const current = localStorage.getItem('domaincheck:theme') || (prefersLight ? 'light' : 'dark');
      const next = (current === 'light') ? 'dark' : 'light';
      applyTheme(next);
      toggleBtn.textContent = next === 'light' ? '☀' : '🌙';
    });
  }
})();
