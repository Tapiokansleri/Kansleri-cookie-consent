(function () {
  'use strict';

  var cfg = window.knccAdmin || {};
  var ajaxUrl = cfg.ajaxUrl;
  var nonce = cfg.nonce;
  var strings = cfg.strings || {};

  function escHtml(s) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(s || ''));
    return div.innerHTML;
  }

  function showNotice(msg, type) {
    var el = document.getElementById('kncc-notice');
    if (!el) return;
    el.textContent = msg;
    el.className = 'kncc-notice kncc-notice--' + (type || 'success');
    el.style.display = '';
    setTimeout(function () { el.style.display = 'none'; }, 4000);
  }

  function serializeForm(form) {
    var data = new FormData(form);
    return new URLSearchParams(data);
  }

  /* ── Settings forms (General, Policy, Integration) ── */

  function initSettingsForms() {
    var forms = document.querySelectorAll('#kncc-general-form, #kncc-policy-form, #kncc-integration-form');
    forms.forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = serializeForm(form);
        body.append('action', 'kncc_save_settings');
        body.append('nonce', nonce);
        body.append('kncc_lang', strings.knccLang || '');

        fetch(ajaxUrl, { method: 'POST', body: body })
          .then(function (r) {
            var ct = r.headers.get('content-type') || '';
            if (!r.ok || ct.indexOf('application/json') === -1) {
              return r.text().then(function (txt) { throw new Error(txt.substring(0, 200)); });
            }
            return r.json();
          })
          .then(function (res) {
            if (res.success) {
              location.reload();
            } else {
              showNotice(res.data || strings.error, 'error');
            }
          })
          .catch(function (err) { showNotice(err.message || strings.error, 'error'); });
      });
    });
  }

  /* ── Cookies CRUD ── */

  var cookieIndex = 0;

  function initCookiesTab() {
    var tbody = document.getElementById('kncc-cookie-tbody');
    var form = document.getElementById('kncc-cookies-form');
    var addBtn = document.getElementById('kncc-add-cookie-btn');
    var scanBtn = document.getElementById('kncc-scan-btn');
    var copyPromptBtn = document.getElementById('kncc-copy-ai-prompt-btn');
    var aiHelper = document.getElementById('kncc-ai-helper');
    var importArea = document.getElementById('kncc-import-json-area');
    var importTextarea = document.getElementById('kncc-import-json-textarea');
    var importBtn = document.getElementById('kncc-import-json-btn');

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
      if (e.target.classList.contains('kncc-remove-cookie')) {
        var row = e.target.closest('tr');
        if (row) row.remove();
        if (!tbody.querySelector('tr[data-index]')) {
          tbody.innerHTML = '<tr class="kncc-no-cookies"><td colspan="6">' + escHtml(strings.noCookies || 'No cookies registered yet.') + '</td></tr>';
        }
      }
    });

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = serializeForm(form);
        body.append('action', 'kncc_save_cookies');
        body.append('nonce', nonce);
        body.append('kncc_lang', strings.knccLang || '');

        fetch(ajaxUrl, { method: 'POST', body: body })
          .then(function (r) {
            var ct = r.headers.get('content-type') || '';
            if (!r.ok || ct.indexOf('application/json') === -1) {
              return r.text().then(function (txt) { throw new Error(txt.substring(0, 200)); });
            }
            return r.json();
          })
          .then(function (res) {
            if (res.success) {
              location.reload();
            } else {
              showNotice(res.data || strings.error, 'error');
            }
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
          saveBody.append('action', 'kncc_save_cookies');
          saveBody.append('nonce', nonce);
          saveBody.append('kncc_lang', strings.knccLang || '');
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
      tbody.innerHTML = '<tr class="kncc-no-cookies"><td colspan="6">' + escHtml(strings.noCookies || 'No cookies registered yet.') + '</td></tr>';
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
    var locale = strings.locale || 'en_US';
    var payload = JSON.stringify(cookies, null, 2);
    return [
      'Improve cookie descriptions in this JSON array.',
      'Write all descriptions in the language matching this locale: ' + locale + '.',
      '',
      'Rules:',
      '- Keep the JSON array format identical.',
      '- Do not rename or remove any cookies.',
      '- Keep name, category, provider, and duration unchanged unless empty and obvious.',
      '- Rewrite only the "description" fields in clear plain language for website visitors.',
      '- Keep each description concise (around 8-20 words), factual, and non-legalistic.',
      '- Write descriptions in the language matching the locale (' + locale + ').',
      '- Return only valid JSON, no markdown and no extra commentary.',
      '',
      'Cookie JSON:',
      payload
    ].join('\n');
  }

  function addRow(tbody, name, category, provider, duration, description) {
    var noRow = tbody.querySelector('.kncc-no-cookies');
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
      '<td><button type="button" class="button kncc-remove-cookie" title="' + escHtml(strings.removeTitle || 'Remove') + '">&times;</button></td>';

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

    var resultsEl = document.getElementById('kncc-scan-results');
    var iframe = document.getElementById('kncc-scan-iframe');
    var scanBtn = document.getElementById('kncc-scan-btn');
    if (!resultsEl || !iframe) return;

    scanInProgress = true;
    if (scanBtn) {
      scanBtn.disabled = true;
      scanBtn.textContent = strings.scanning || 'Scanning...';
    }
    resultsEl.innerHTML = '<span class="kncc-scan-spinner"></span> ' + strings.scanning;
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
      body.append('action', 'kncc_process_scan');
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
            resultsEl.innerHTML = '<div class="kncc-scan-alldone"><span class="dashicons dashicons-yes-alt" style="color:#059669;margin-right:4px;"></span> ' + (strings.allDone || 'All cookies are done! No new or incomplete cookies found.') + '</div>';
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

            html += '<div class="kncc-scan-auto">';
            html += '<p><strong>' + (strings.knownAdded || '%d known cookie(s) added automatically:').replace('%d', autoAdded.length) + '</strong></p>';
            Object.keys(groups).sort().forEach(function (provider) {
              var items = groups[provider];
              html += '<div class="kncc-scan-group kncc-scan-group--auto">';
              html += '<div class="kncc-scan-group__header">';
              html += '<span class="dashicons dashicons-yes-alt" style="color:#059669;margin-right:4px;"></span> ';
              html += '<strong>' + escHtml(provider) + '</strong> <span class="kncc-scan-group__count">(' + items.length + ')</span>';
              html += '</div>';
              html += '<table class="kncc-scan-group__table"><tbody>';
              items.forEach(function (c) {
                html += '<tr>';
                html += '<td><code>' + escHtml(c.name) + '</code></td>';
                html += '<td class="kncc-scan-cat kncc-scan-cat--' + escHtml(c.category) + '">' + escHtml(c.category) + '</td>';
                html += '<td class="kncc-scan-desc">' + escHtml(c.description) + '</td>';
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
            html += '<div class="kncc-scan-unknown">';
            html += '<p><strong>' + (strings.unknownAdded || '%d unknown cookie(s) added — please set their category and description below.').replace('%d', unknown.length) + '</strong></p>';
            html += '<div class="kncc-scan-group">';
            html += '<div class="kncc-scan-group__header">';
            html += '<span class="dashicons dashicons-warning" style="color:#d97706;margin-right:4px;"></span> ';
            html += '<strong>' + (strings.unknownLabel || 'Unknown') + '</strong> <span class="kncc-scan-group__count">(' + unknown.length + ')</span>';
            html += '</div>';
            html += '<table class="kncc-scan-group__table"><tbody>';
            unknown.forEach(function (c) {
              html += '<tr>';
              html += '<td><code>' + escHtml(c.name) + '</code></td>';
              html += '<td class="kncc-scan-desc">' + (strings.unknownDesc || 'Added — categorize and describe in the table below') + '</td>';
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
              html += '<div class="kncc-scan-alldone"><span class="dashicons dashicons-yes-alt" style="color:#059669;margin-right:4px;"></span> ' + (strings.allDone || 'All cookies are done! Every cookie has a description.') + '</div>';
            }
          }

          resultsEl.innerHTML = html;

          if (unknown.length) {
            var helperEl = document.getElementById('kncc-ai-helper');
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

  /* ── Consent Log export/purge ── */

  function initConsentLog() {
    var exportBtn = document.getElementById('kncc-export-log-btn');
    var purgeBtn = document.getElementById('kncc-purge-log-btn');

    if (exportBtn) {
      exportBtn.addEventListener('click', function () {
        var body = new URLSearchParams();
        body.append('action', 'kncc_export_consent_log');
        body.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: body })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (!res.success) {
              showNotice(res.data || strings.error, 'error');
              return;
            }
            var blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'consent-log-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
          })
          .catch(function (err) { showNotice(err.message || strings.error, 'error'); });
      });
    }

    if (purgeBtn) {
      purgeBtn.addEventListener('click', function () {
        if (!confirm(strings.confirmPurge || 'This will permanently delete all consent log records and statistics. Continue?')) {
          return;
        }
        var body = new URLSearchParams();
        body.append('action', 'kncc_purge_consent_log');
        body.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: body })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.success) {
              showNotice(strings.purged || 'Purged.', 'success');
              window.location.reload();
            } else {
              showNotice(res.data || strings.error, 'error');
            }
          })
          .catch(function (err) { showNotice(err.message || strings.error, 'error'); });
      });
    }
  }

  /* ── AI Prompt for Policy Page Content ── */

  function initPolicyAiHelper() {
    var copyBtn = document.getElementById('kncc-copy-policy-prompt-btn');
    if (!copyBtn) return;

    copyBtn.addEventListener('click', function () {
      var data = window.knccPolicyPromptData || {};
      var prompt = buildPolicyPrompt(data);

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(prompt)
          .then(function () {
            showNotice(strings.policyCopied || 'Prompt copied!', 'success');
          })
          .catch(function () {
            window.prompt('Copy this prompt:', prompt);
          });
      } else {
        window.prompt('Copy this prompt:', prompt);
      }
    });
  }

  function buildPolicyPrompt(data) {
    var lines = [
      'Generate a complete cookie and privacy policy page for a website.',
      'Write everything in the language matching the locale below.',
      '',
      'Site name: ' + (data.siteName || 'Unknown'),
      'Site URL: ' + (data.siteUrl || ''),
      'Admin email: ' + (data.adminEmail || ''),
      'Locale: ' + (data.locale || 'en_US'),
      ''
    ];

    if (data.categories && data.categories.length) {
      lines.push('Cookie categories used on this site:');
      data.categories.forEach(function (c) { lines.push('- ' + c); });
      lines.push('');
    }

    lines.push('The page should include the following sections:');
    lines.push('1. Register and data protection notice — what kind of register this is and under what law (e.g. GDPR)');
    lines.push('2. Data controller — company/person name, contact email');
    lines.push('3. Register name — what the register is called');
    lines.push('4. Legal basis and purpose of processing personal data — explain why data is collected');
    lines.push('5. Data content — what kind of data is stored (name, email, IP, etc.)');
    lines.push('6. Data sources — where the data comes from (website forms, cookies, analytics, etc.)');
    lines.push('7. Data retention — how long data is stored');
    lines.push('8. Data disclosure and transfer — whether data is shared with third parties');
    lines.push('9. Rights of the data subject — right to access, rectify, delete, restrict processing, object, and data portability');
    lines.push('10. Cookies — DO NOT list individual cookies. Instead, include this exact WordPress shortcode on its own line: [kncc_cookie_policy]');
    lines.push('    This shortcode will automatically render the full cookie declaration table.');
    lines.push('11. Consent management — explain that this site uses a consent banner and visitors can change their preferences at any time');
    lines.push('');
    lines.push('Rules:');
    lines.push('- Write in the language matching the locale (' + (data.locale || 'en_US') + ').');
    lines.push('- Tone: professional, clear, not overly legalistic.');
    lines.push('- DO NOT use placeholders like [Company name] or [Email]. If you need information that is not provided above (such as the company name, contact person, business ID, or address), ASK me for it before generating the page. Do not guess or leave blanks.');
    lines.push('- In the Cookies section (section 10), output ONLY the shortcode [kncc_cookie_policy] — the plugin generates the cookie table automatically.');
    lines.push('- Output plain HTML that can be pasted directly into a WordPress page editor.');
    lines.push('- Do NOT wrap the output in markdown code fences or add any commentary outside the HTML.');

    return lines.join('\n');
  }

  /* ── Init ── */

  function init() {
    initSettingsForms();
    initCookiesTab();
    initConsentLog();
    initPolicyAiHelper();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
