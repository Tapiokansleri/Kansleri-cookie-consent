(function () {
  'use strict';

  var cfg = window.kccAdmin || {};
  var ajaxUrl = cfg.ajaxUrl;
  var nonce = cfg.nonce;
  var strings = cfg.strings || {};

  function escHtml(s) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(s || ''));
    return div.innerHTML;
  }

  function showNotice(msg, type) {
    var el = document.getElementById('kcc-notice');
    if (!el) return;
    el.textContent = msg;
    el.className = 'kcc-notice kcc-notice--' + (type || 'success');
    el.style.display = '';
    setTimeout(function () { el.style.display = 'none'; }, 4000);
  }

  function serializeForm(form) {
    var data = new FormData(form);
    return new URLSearchParams(data);
  }

  /* ── Settings forms (General, Policy, Integration) ── */

  function initSettingsForms() {
    var forms = document.querySelectorAll('#kcc-general-form, #kcc-policy-form, #kcc-integration-form');
    forms.forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = serializeForm(form);
        body.append('action', 'kcc_save_settings');
        body.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: body })
          .then(function (r) {
            var ct = r.headers.get('content-type') || '';
            if (!r.ok || ct.indexOf('application/json') === -1) {
              return r.text().then(function (txt) { throw new Error(txt.substring(0, 200)); });
            }
            return r.json();
          })
          .then(function (res) {
            showNotice(res.success ? strings.saved : (res.data || strings.error), res.success ? 'success' : 'error');
          })
          .catch(function (err) { showNotice(err.message || strings.error, 'error'); });
      });
    });
  }

  /* ── Cookies CRUD ── */

  var cookieIndex = 0;

  function initCookiesTab() {
    var tbody = document.getElementById('kcc-cookie-tbody');
    var form = document.getElementById('kcc-cookies-form');
    var addBtn = document.getElementById('kcc-add-cookie-btn');
    var scanBtn = document.getElementById('kcc-scan-btn');
    var copyPromptBtn = document.getElementById('kcc-copy-ai-prompt-btn');
    var aiHelper = document.getElementById('kcc-ai-helper');
    var importArea = document.getElementById('kcc-import-json-area');
    var importTextarea = document.getElementById('kcc-import-json-textarea');
    var importBtn = document.getElementById('kcc-import-json-btn');

    if (!tbody) return;

    var rows = tbody.querySelectorAll('tr[data-index]');
    rows.forEach(function (r) {
      var idx = parseInt(r.getAttribute('data-index'), 10);
      if (idx >= cookieIndex) cookieIndex = idx + 1;
    });

    if (addBtn) {
      addBtn.addEventListener('click', function () { addRow(tbody); });
    }

    tbody.addEventListener('click', function (e) {
      if (e.target.classList.contains('kcc-remove-cookie')) {
        var row = e.target.closest('tr');
        if (row) row.remove();
        if (!tbody.querySelector('tr[data-index]')) {
          tbody.innerHTML = '<tr class="kcc-no-cookies"><td colspan="6">No cookies registered yet.</td></tr>';
        }
      }
    });

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = serializeForm(form);
        body.append('action', 'kcc_save_cookies');
        body.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: body })
          .then(function (r) {
            var ct = r.headers.get('content-type') || '';
            if (!r.ok || ct.indexOf('application/json') === -1) {
              return r.text().then(function (txt) { throw new Error(txt.substring(0, 200)); });
            }
            return r.json();
          })
          .then(function (res) {
            showNotice(res.success ? strings.saved : (res.data || strings.error), res.success ? 'success' : 'error');
          })
          .catch(function (err) { showNotice(err.message || strings.error, 'error'); });
      });
    }

    if (scanBtn) {
      scanBtn.addEventListener('click', function () { runScan(tbody); });
    }

    if (copyPromptBtn) {
      copyPromptBtn.addEventListener('click', function () {
        var cookies = collectCookiesFromTable(tbody);
        if (!cookies.length) {
          showNotice(strings.exportEmpty || strings.error, 'error');
          return;
        }

        var promptText = buildAiPrompt(cookies);
        var copied = false;

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(promptText)
            .then(function () {
              showNotice(strings.promptCopied || strings.saved, 'success');
            })
            .catch(function () {
              window.prompt('Copy this AI prompt:', promptText);
            });
          copied = true;
        }

        if (!copied) {
          window.prompt('Copy this AI prompt:', promptText);
        }

        if (importArea) {
          importArea.style.display = '';
        }
      });
    }

    if (importBtn && importTextarea) {
      importBtn.addEventListener('click', function () {
        var raw = (importTextarea.value || '').trim();
        if (!raw) {
          showNotice(strings.invalidJson || strings.error, 'error');
          return;
        }

        try {
          var parsed = JSON.parse(raw);
          var rawCookies = Array.isArray(parsed) ? parsed : (Array.isArray(parsed.cookies) ? parsed.cookies : null);
          if (!rawCookies) {
            showNotice(strings.invalidJson || strings.error, 'error');
            return;
          }

          var normalized = [];
          rawCookies.forEach(function (entry) {
            var item = normalizeCookie(entry);
            if (item) normalized.push(item);
          });

          if (!normalized.length) {
            showNotice(strings.importEmpty || strings.error, 'error');
            return;
          }

          replaceRowsFromCookies(tbody, normalized);
          importTextarea.value = '';

          var saveBody = serializeForm(form);
          saveBody.append('action', 'kcc_save_cookies');
          saveBody.append('nonce', nonce);
          fetch(ajaxUrl, { method: 'POST', body: saveBody })
            .then(function (r) { return r.json(); })
            .then(function (res) {
              if (res.success) {
                window.location.reload();
                return;
              }
              showNotice(res.data || strings.error, 'error');
            })
            .catch(function () {
              showNotice(strings.error, 'error');
            });
        } catch (err) {
          showNotice(strings.invalidJson || strings.error, 'error');
        }
      });
    }
  }

  function normalizeCookie(entry) {
    if (!entry || typeof entry !== 'object') return null;

    var categories = ['necessary', 'analytics', 'marketing', 'preferences'];
    var name = String(entry.name || '').trim();
    if (!name) return null;

    var category = String(entry.category || 'necessary').trim().toLowerCase();
    if (categories.indexOf(category) === -1) category = 'necessary';

    return {
      name: name,
      category: category,
      provider: String(entry.provider || '').trim(),
      duration: String(entry.duration || '').trim(),
      description: String(entry.description || '').trim()
    };
  }

  function collectCookiesFromTable(tbody) {
    var rows = tbody.querySelectorAll('tr[data-index]');
    var result = [];
    rows.forEach(function (row) {
      var nameInput = row.querySelector('input[name*="[name]"]');
      if (!nameInput) return;
      var entry = normalizeCookie({
        name: nameInput.value,
        category: (row.querySelector('select[name*="[category]"]') || {}).value,
        provider: (row.querySelector('input[name*="[provider]"]') || {}).value,
        duration: (row.querySelector('input[name*="[duration]"]') || {}).value,
        description: (row.querySelector('input[name*="[description]"]') || {}).value
      });
      if (entry) result.push(entry);
    });
    return result;
  }

  function replaceRowsFromCookies(tbody, cookies) {
    tbody.innerHTML = '';
    cookieIndex = 0;
    cookies.forEach(function (entry) {
      addRow(tbody, entry.name, entry.category, entry.provider, entry.duration, entry.description);
    });
    if (!cookies.length) {
      tbody.innerHTML = '<tr class="kcc-no-cookies"><td colspan="6">No cookies registered yet.</td></tr>';
    }
  }

  function downloadJson(filename, content) {
    var blob = new Blob([content], { type: 'application/json;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function buildAiPrompt(cookies) {
    var payload = JSON.stringify(cookies, null, 2);
    return [
      'Improve cookie descriptions in this JSON array.',
      '',
      'Rules:',
      '- Keep the JSON array format identical.',
      '- Do not rename or remove any cookies.',
      '- Keep name, category, provider, and duration unchanged unless empty and obvious.',
      '- Rewrite only the "description" fields in clear plain language for website visitors.',
      '- Keep each description concise (around 8-20 words), factual, and non-legalistic.',
      '- Return only valid JSON, no markdown and no extra commentary.',
      '',
      'Cookie JSON:',
      payload
    ].join('\n');
  }

  function addRow(tbody, name, category, provider, duration, description) {
    var noRow = tbody.querySelector('.kcc-no-cookies');
    if (noRow) noRow.remove();

    var i = cookieIndex++;
    var tr = document.createElement('tr');
    tr.setAttribute('data-index', i);
    var cats = ['necessary', 'analytics', 'marketing', 'preferences'];

    function esc(s) { return escHtml(s).replace(/"/g, '&quot;'); }

    tr.innerHTML =
      '<td><input type="text" name="cookies[' + i + '][name]" value="' + esc(name) + '" class="regular-text" /></td>' +
      '<td><select name="cookies[' + i + '][category]">' +
        cats.map(function (c) {
          return '<option value="' + c + '"' + (c === (category || 'necessary') ? ' selected' : '') + '>' + c.charAt(0).toUpperCase() + c.slice(1) + '</option>';
        }).join('') +
      '</select></td>' +
      '<td><input type="text" name="cookies[' + i + '][provider]" value="' + esc(provider) + '" /></td>' +
      '<td><input type="text" name="cookies[' + i + '][duration]" value="' + esc(duration) + '" style="width:100px;" /></td>' +
      '<td><input type="text" name="cookies[' + i + '][description]" value="' + esc(description) + '" class="large-text" /></td>' +
      '<td><button type="button" class="button kcc-remove-cookie" title="Remove">&times;</button></td>';

    tbody.appendChild(tr);
  }

  /* ── Cookie Scanner ── */

  var scanInProgress = false;
  var SCAN_TIMEOUT_MS = 15000;

  function scanCleanup(iframe, scanBtn) {
    scanInProgress = false;
    iframe.onload = null;
    iframe.onerror = null;
    try { iframe.src = 'about:blank'; } catch (e) {}
    if (scanBtn) {
      scanBtn.disabled = false;
      scanBtn.textContent = strings.scanBtn || 'Scan Cookies';
    }
  }

  function showScanError(resultsEl, message) {
    resultsEl.innerHTML =
      '<div style="color:#991b1b;background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;padding:12px 16px;">' +
      '<strong>Scan failed:</strong> ' + escHtml(message) + '</div>';
  }

  function runScan(tbody) {
    if (scanInProgress) return;

    var resultsEl = document.getElementById('kcc-scan-results');
    var iframe = document.getElementById('kcc-scan-iframe');
    var scanBtn = document.getElementById('kcc-scan-btn');
    if (!resultsEl || !iframe) return;

    scanInProgress = true;
    if (scanBtn) {
      scanBtn.disabled = true;
      scanBtn.textContent = strings.scanning || 'Scanning...';
    }
    resultsEl.innerHTML = '<span class="kcc-scan-spinner"></span> ' + strings.scanning;
    resultsEl.style.display = '';

    var timer = setTimeout(function () {
      scanCleanup(iframe, scanBtn);
      showScanError(resultsEl, 'Scan timed out after ' + (SCAN_TIMEOUT_MS / 1000) + ' seconds. Your site may be blocking iframes or taking too long to load.');
    }, SCAN_TIMEOUT_MS);

    iframe.onerror = function () {
      clearTimeout(timer);
      scanCleanup(iframe, scanBtn);
      showScanError(resultsEl, 'Could not load the site for scanning. Check that your homepage is accessible.');
    };

    iframe.onload = function () {
      clearTimeout(timer);

      iframe.onload = null;
      iframe.onerror = null;

      var found = [];

      try {
        var doc = iframe.contentDocument || iframe.contentWindow.document;
        var raw = doc.cookie || '';
        if (raw) {
          raw.split(';').forEach(function (pair) {
            var name = pair.split('=')[0].trim();
            if (name) found.push(name);
          });
        }
      } catch (e) {
        // cross-origin — fall through to document.cookie
      }

      if (document.cookie) {
        document.cookie.split(';').forEach(function (pair) {
          var name = pair.split('=')[0].trim();
          if (name && found.indexOf(name) === -1) found.push(name);
        });
      }

      try { iframe.src = 'about:blank'; } catch (e) {}

      if (found.length === 0) {
        scanCleanup(iframe, scanBtn);
        resultsEl.innerHTML = strings.scanDone + ' ' + strings.noNew;
        return;
      }

      var body = new URLSearchParams();
      body.append('action', 'kcc_process_scan');
      body.append('nonce', nonce);
      found.forEach(function (n) { body.append('found[]', n); });

      fetch(ajaxUrl, { method: 'POST', body: body })
        .then(function (r) {
          var ct = r.headers.get('content-type') || '';
          if (!r.ok || ct.indexOf('application/json') === -1) {
            return r.text().then(function (txt) {
              throw new Error('Server returned HTTP ' + r.status + '. Response: ' + txt.substring(0, 300));
            });
          }
          return r.json();
        })
        .then(function (res) {
          scanCleanup(iframe, scanBtn);

          if (!res.success) {
            showScanError(resultsEl, res.data || strings.error);
            return;
          }

          var autoAdded = res.data.auto_added || [];
          var unknown = res.data.unknown || [];

          if (!autoAdded.length && !unknown.length) {
            resultsEl.innerHTML = '<div class="kcc-scan-alldone"><span class="dashicons dashicons-yes-alt" style="color:#059669;margin-right:4px;"></span> ' + (strings.allDone || 'All cookies are done! No new or incomplete cookies found.') + '</div>';
            return;
          }

          var html = '<h4>' + strings.scanDone + '</h4>';

          if (autoAdded.length) {
            var groups = {};
            autoAdded.forEach(function (c) {
              var key = c.provider || 'Other';
              if (!groups[key]) groups[key] = [];
              groups[key].push(c);
            });

            html += '<div class="kcc-scan-auto">';
            html += '<p><strong>' + autoAdded.length + ' known cookie(s) added automatically:</strong></p>';
            Object.keys(groups).sort().forEach(function (provider) {
              var items = groups[provider];
              html += '<div class="kcc-scan-group kcc-scan-group--auto">';
              html += '<div class="kcc-scan-group__header">';
              html += '<span class="dashicons dashicons-yes-alt" style="color:#059669;margin-right:4px;"></span> ';
              html += '<strong>' + escHtml(provider) + '</strong> <span class="kcc-scan-group__count">(' + items.length + ')</span>';
              html += '</div>';
              html += '<table class="kcc-scan-group__table"><tbody>';
              items.forEach(function (c) {
                html += '<tr>';
                html += '<td><code>' + escHtml(c.name) + '</code></td>';
                html += '<td class="kcc-scan-cat kcc-scan-cat--' + escHtml(c.category) + '">' + escHtml(c.category) + '</td>';
                html += '<td class="kcc-scan-desc">' + escHtml(c.description) + '</td>';
                html += '</tr>';
              });
              html += '</tbody></table></div>';
            });
            html += '</div>';

            autoAdded.forEach(function (c) {
              addRow(tbody, c.name, c.category, c.provider, c.duration, c.description);
            });
          }

          if (unknown.length) {
            html += '<div class="kcc-scan-unknown">';
            html += '<p><strong>' + unknown.length + ' unknown cookie(s) added &mdash; please set their category and description below.</strong></p>';
            html += '<div class="kcc-scan-group">';
            html += '<div class="kcc-scan-group__header">';
            html += '<span class="dashicons dashicons-warning" style="color:#d97706;margin-right:4px;"></span> ';
            html += '<strong>Unknown</strong> <span class="kcc-scan-group__count">(' + unknown.length + ')</span>';
            html += '</div>';
            html += '<table class="kcc-scan-group__table"><tbody>';
            unknown.forEach(function (c) {
              html += '<tr>';
              html += '<td><code>' + escHtml(c.name) + '</code></td>';
              html += '<td class="kcc-scan-desc">Added &mdash; categorize and describe in the table below</td>';
              html += '</tr>';
            });
            html += '</tbody></table></div>';
            html += '</div>';

            unknown.forEach(function (c) {
              addRow(tbody, c.name, c.category, c.provider, c.duration, c.description);
            });
          }

          if (!unknown.length) {
            var allHaveDesc = true;
            var allRows = tbody.querySelectorAll('tr[data-index]');
            allRows.forEach(function (row) {
              var descInput = row.querySelector('input[name*="[description]"]');
              if (descInput && !descInput.value.trim()) allHaveDesc = false;
            });
            if (allHaveDesc) {
              html += '<div class="kcc-scan-alldone"><span class="dashicons dashicons-yes-alt" style="color:#059669;margin-right:4px;"></span> ' + (strings.allDone || 'All cookies are done! Every cookie has a description.') + '</div>';
            }
          }

          resultsEl.innerHTML = html;

          if (unknown.length) {
            var helperEl = document.getElementById('kcc-ai-helper');
            if (helperEl) helperEl.style.display = '';
          }
        })
        .catch(function (err) {
          scanCleanup(iframe, scanBtn);
          showScanError(resultsEl, err.message || strings.error);
        });
    };

    iframe.src = cfg.homeUrl;
  }

  /* ── Init ── */

  function init() {
    initSettingsForms();
    initCookiesTab();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
