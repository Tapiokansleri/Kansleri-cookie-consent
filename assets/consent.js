(function () {
  'use strict';

  var COOKIE_NAME = 'kncc_consent';
  var COOKIE_DAYS = 365;
  var config = window.knccConfig || {};

  function getConsent() {
    var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_NAME + '=([^;]*)'));
    if (!match) return null;
    try {
      return JSON.parse(decodeURIComponent(match[1]));
    } catch (e) {
      return null;
    }
  }

  function setConsent(consent) {
    consent.timestamp = new Date().toISOString();
    var val = encodeURIComponent(JSON.stringify(consent));
    var expires = new Date(Date.now() + COOKIE_DAYS * 864e5).toUTCString();
    var cookie = COOKIE_NAME + '=' + val + ';expires=' + expires + ';path=/;SameSite=Lax';
    if (location.protocol === 'https:') {
      cookie += ';Secure';
    }
    document.cookie = cookie;
  }

  function dispatchConsentCommand(payload) {
    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', payload);
      return;
    }
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(['consent', 'update', payload]);
  }

  function updateConsentMode(consent) {
    if (!config.consentMode) return;

    dispatchConsentCommand({
      analytics_storage:        consent.analytics ? 'granted' : 'denied',
      ad_storage:               consent.marketing ? 'granted' : 'denied',
      ad_user_data:             consent.marketing ? 'granted' : 'denied',
      ad_personalization:       consent.marketing ? 'granted' : 'denied',
      functionality_storage:    consent.preferences ? 'granted' : 'denied',
      personalization_storage:  consent.preferences ? 'granted' : 'denied',
      security_storage:         'granted',
    });
  }

  function pushDataLayer(consent) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'consent_update',
      consent_necessary: true,
      consent_analytics: !!consent.analytics,
      consent_marketing: !!consent.marketing,
      consent_preferences: !!consent.preferences,
    });
  }

  function recordStat(choice, consent) {
    if (!config.ajaxUrl) return;
    var body = new FormData();
    body.append('action', 'kncc_record_consent');
    body.append('choice', choice);
    body.append('nonce', config.statsNonce || '');
    body.append('categories', JSON.stringify({
      necessary: true,
      analytics: !!consent.analytics,
      marketing: !!consent.marketing,
      preferences: !!consent.preferences,
    }));
    try { navigator.sendBeacon(config.ajaxUrl, body); } catch (e) {
      fetch(config.ajaxUrl, { method: 'POST', body: body }).catch(function () {});
    }
  }

  /* ── Script / iframe activation after consent ── */

  var scriptsActivated = false;

  function activateBlockedScripts(consent) {
    if (scriptsActivated) return;
    scriptsActivated = true;

    var blocked = document.querySelectorAll('script[type="text/plain"][data-kncc-category]');
    blocked.forEach(function (el) {
      var cat = el.getAttribute('data-kncc-category');
      if (!consent[cat]) return;

      var newScript = document.createElement('script');
      newScript.type = 'text/javascript';

      if (el.src) {
        newScript.src = el.src;
        if (el.hasAttribute('async')) newScript.async = true;
        if (el.hasAttribute('defer')) newScript.defer = true;
      } else {
        newScript.textContent = el.textContent;
      }

      el.parentNode.insertBefore(newScript, el.nextSibling);
      el.remove();
    });

    var iframes = document.querySelectorAll('iframe[data-kncc-src][data-kncc-category]');
    iframes.forEach(function (el) {
      var cat = el.getAttribute('data-kncc-category');
      if (!consent[cat]) return;

      el.src = el.getAttribute('data-kncc-src');
      el.removeAttribute('data-kncc-src');
      el.classList.remove('kncc-blocked-iframe');
      var placeholder = el.previousElementSibling;
      if (placeholder && placeholder.classList.contains('kncc-iframe-placeholder')) {
        placeholder.remove();
      }
    });
  }

  function applyConsent(consent, choice) {
    setConsent(consent);
    updateConsentMode(consent);
    pushDataLayer(consent);
    activateBlockedScripts(consent);
    if (choice) recordStat(choice, consent);
    hideBanner();
    showFloatingBtn();
  }

  function acceptAll() {
    applyConsent({ necessary: true, analytics: true, marketing: true, preferences: true }, 'accept');
  }

  function rejectAll() {
    var consent = { necessary: true, analytics: false, marketing: false, preferences: false };
    setConsent(consent);
    updateConsentMode(consent);
    pushDataLayer(consent);
    recordStat('reject', consent);
    // Reload so server-side iframe blocking takes effect with the new cookie.
    location.reload();
  }

  function saveCustom() {
    var consent = { necessary: true };
    var checks = document.querySelectorAll('.kncc-category__check');
    checks.forEach(function (el) {
      var cat = el.getAttribute('data-category');
      if (cat !== 'necessary') {
        consent[cat] = el.checked;
      }
    });
    applyConsent(consent, 'custom');
  }

  function closeBanner() {
    hideBanner();
    showFloatingBtn();
  }

  var banner = null;
  var details = null;
  var floatingBtn = null;

  function showBanner() {
    if (banner) {
      banner.style.display = '';
      banner.removeAttribute('aria-hidden');

      var existing = getConsent();
      if (existing) {
        var checks = banner.querySelectorAll('.kncc-category__check');
        checks.forEach(function (el) {
          var cat = el.getAttribute('data-category');
          if (cat !== 'necessary' && existing[cat] !== undefined) {
            el.checked = existing[cat];
          }
        });
      }
    }
  }

  function hideBanner() {
    if (banner) {
      banner.style.display = 'none';
      banner.setAttribute('aria-hidden', 'true');
    }
    if (details) {
      details.style.display = 'none';
    }
  }

  function showFloatingBtn() {
    if (floatingBtn) {
      floatingBtn.style.display = '';
    }
  }

  function hideFloatingBtn() {
    if (floatingBtn) {
      floatingBtn.style.display = 'none';
    }
  }

  function toggleDetails() {
    if (!details) return;
    var hidden = details.style.display === 'none';
    details.style.display = hidden ? '' : 'none';
  }

  /* ── Inline iframe accept buttons ── */

  function initIframeAcceptButtons() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-kncc-iframe-accept]');
      if (!btn) return;

      var cat = btn.getAttribute('data-kncc-iframe-accept');
      var consent = getConsent() || { necessary: true };
      consent[cat] = true;
      setConsent(consent);
      updateConsentMode(consent);
      pushDataLayer(consent);

      // Activate all iframes for this category
      var iframes = document.querySelectorAll('iframe[data-kncc-src][data-kncc-category="' + cat + '"]');
      iframes.forEach(function (el) {
        el.src = el.getAttribute('data-kncc-src');
        el.removeAttribute('data-kncc-src');
        el.classList.remove('kncc-blocked-iframe');
        var placeholder = el.previousElementSibling;
        if (placeholder && placeholder.classList.contains('kncc-iframe-placeholder')) {
          placeholder.remove();
        }
      });

      // Remove remaining placeholders for this category
      var placeholders = document.querySelectorAll('.kncc-iframe-placeholder[data-kncc-category="' + cat + '"]');
      placeholders.forEach(function (el) { el.remove(); });
    });
  }

  /* ── Global API for reopening consent from policy page or external links ── */

  window.knccEditConsent = function () {
    hideFloatingBtn();
    showBanner();
  };

  function init() {
    banner = document.getElementById('kncc-banner');
    floatingBtn = document.getElementById('kncc-floating-btn');

    if (!banner) return;

    details = banner.querySelector('.kncc-details');

    banner.addEventListener('click', function (e) {
      var target = e.target.closest('[data-kncc]');
      if (!target) return;

      var action = target.getAttribute('data-kncc');
      switch (action) {
        case 'accept':
          acceptAll();
          break;
        case 'reject':
          rejectAll();
          break;
        case 'toggle-details':
          toggleDetails();
          break;
        case 'close':
          closeBanner();
          break;
        case 'save':
          saveCustom();
          break;
      }
    });

    if (floatingBtn) {
      floatingBtn.addEventListener('click', function () {
        hideFloatingBtn();
        showBanner();
      });
    }

    initIframeAcceptButtons();

    var consent = getConsent();
    if (consent) {
      updateConsentMode(consent);
      activateBlockedScripts(consent);
      showFloatingBtn();
    } else {
      showBanner();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
