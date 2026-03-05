(function () {
  'use strict';

  var cfg = window.kccAdmin || {};
  var ajaxUrl = cfg.ajaxUrl;
  var nonce = cfg.nonce;
  var strings = cfg.strings || {};

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
          .then(function (r) { return r.json(); })
          .then(function (res) {
            showNotice(res.success ? strings.saved : (res.data || strings.error), res.success ? 'success' : 'error');
          })
          .catch(function () { showNotice(strings.error, 'error'); });
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
          .then(function (r) { return r.json(); })
          .then(function (res) {
            showNotice(res.success ? strings.saved : (res.data || strings.error), res.success ? 'success' : 'error');
          })
          .catch(function () { showNotice(strings.error, 'error'); });
      });
    }

    if (scanBtn) {
      scanBtn.addEventListener('click', function () { runScan(tbody); });
    }
  }

  function addRow(tbody, name, category, provider, duration, description) {
    var noRow = tbody.querySelector('.kcc-no-cookies');
    if (noRow) noRow.remove();

    var i = cookieIndex++;
    var tr = document.createElement('tr');
    tr.setAttribute('data-index', i);
    var cats = ['necessary', 'analytics', 'marketing', 'preferences'];

    function esc(s) { return (s || '').replace(/"/g, '&quot;'); }

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

  function runScan(tbody) {
    var resultsEl = document.getElementById('kcc-scan-results');
    var iframe = document.getElementById('kcc-scan-iframe');
    if (!resultsEl || !iframe) return;

    resultsEl.innerHTML = '<span class="kcc-scan-spinner"></span> ' + strings.scanning;
    resultsEl.style.display = '';

    iframe.src = cfg.homeUrl;

    iframe.onload = function () {
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
        // cross-origin fallback: read from main document cookies
      }

      // Also scan main document cookies
      if (document.cookie) {
        document.cookie.split(';').forEach(function (pair) {
          var name = pair.split('=')[0].trim();
          if (name && found.indexOf(name) === -1) found.push(name);
        });
      }

      iframe.src = 'about:blank';

      if (found.length === 0) {
        resultsEl.innerHTML = strings.noNew;
        return;
      }

      var body = new URLSearchParams();
      body.append('action', 'kcc_process_scan');
      body.append('nonce', nonce);
      found.forEach(function (n) { body.append('found[]', n); });

      fetch(ajaxUrl, { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.success || !res.data.new_cookies.length) {
            resultsEl.innerHTML = strings.scanDone + ' ' + strings.noNew;
            return;
          }

          var cookies = res.data.new_cookies;
          var groups = {};
          var unknown = [];

          cookies.forEach(function (c) {
            if (c.identified && c.provider) {
              if (!groups[c.provider]) groups[c.provider] = [];
              groups[c.provider].push(c);
            } else {
              unknown.push(c);
            }
          });

          var html = '<h4>' + strings.scanDone + ' ' + cookies.length + ' new cookie(s) found.</h4>';

          var providerKeys = Object.keys(groups).sort();
          providerKeys.forEach(function (provider) {
            var items = groups[provider];
            html += '<div class="kcc-scan-group">';
            html += '<div class="kcc-scan-group__header">';
            html += '<strong>' + provider + '</strong> <span class="kcc-scan-group__count">(' + items.length + ')</span> ';
            html += '<button type="button" class="button button-small kcc-add-group" data-provider="' + provider + '">Add all</button>';
            html += '</div>';
            html += '<table class="kcc-scan-group__table"><tbody>';
            items.forEach(function (c) {
              html += '<tr data-cookie=\'' + JSON.stringify(c).replace(/'/g, '&#39;') + '\'>';
              html += '<td><code>' + c.name + '</code></td>';
              html += '<td class="kcc-scan-cat kcc-scan-cat--' + c.category + '">' + c.category + '</td>';
              html += '<td class="kcc-scan-desc">' + c.description + '</td>';
              html += '<td><button type="button" class="button button-small kcc-add-scanned">Add</button></td>';
              html += '</tr>';
            });
            html += '</tbody></table></div>';
          });

          if (unknown.length) {
            html += '<div class="kcc-scan-group">';
            html += '<div class="kcc-scan-group__header">';
            html += '<strong>Unknown</strong> <span class="kcc-scan-group__count">(' + unknown.length + ')</span> ';
            html += '<button type="button" class="button button-small kcc-add-group" data-provider="__unknown">Add all</button>';
            html += '</div>';
            html += '<table class="kcc-scan-group__table"><tbody>';
            unknown.forEach(function (c) {
              html += '<tr data-cookie=\'' + JSON.stringify(c).replace(/'/g, '&#39;') + '\'>';
              html += '<td><code>' + c.name + '</code></td>';
              html += '<td class="kcc-scan-cat">—</td>';
              html += '<td class="kcc-scan-desc">Not identified</td>';
              html += '<td><button type="button" class="button button-small kcc-add-scanned">Add</button></td>';
              html += '</tr>';
            });
            html += '</tbody></table></div>';
          }

          resultsEl.innerHTML = html;

          resultsEl.addEventListener('click', function (e) {
            if (e.target.classList.contains('kcc-add-scanned')) {
              var row = e.target.closest('tr');
              if (!row) return;
              var c = JSON.parse(row.getAttribute('data-cookie'));
              addRow(tbody, c.name, c.category, c.provider, c.duration, c.description);
              e.target.disabled = true;
              e.target.textContent = 'Added';
            }

            if (e.target.classList.contains('kcc-add-group')) {
              var group = e.target.closest('.kcc-scan-group');
              if (!group) return;
              var rows = group.querySelectorAll('tr[data-cookie]');
              rows.forEach(function (row) {
                var btn = row.querySelector('.kcc-add-scanned');
                if (btn && !btn.disabled) {
                  var c = JSON.parse(row.getAttribute('data-cookie'));
                  addRow(tbody, c.name, c.category, c.provider, c.duration, c.description);
                  btn.disabled = true;
                  btn.textContent = 'Added';
                }
              });
              e.target.disabled = true;
              e.target.textContent = 'All added';
            }
          });
        })
        .catch(function () {
          resultsEl.innerHTML = strings.error;
        });
    };

    iframe.onerror = function () {
      resultsEl.innerHTML = strings.error;
    };
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
