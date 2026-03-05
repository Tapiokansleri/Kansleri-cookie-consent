(function () {
  'use strict';

  var COOKIE_NAME = 'kcc_consent';
  var COOKIE_DAYS = 365;
  var config = window.kccConfig || {};

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
    document.cookie = COOKIE_NAME + '=' + val + ';expires=' + expires + ';path=/;SameSite=Lax';
  }

  function gtag() {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(arguments);
  }

  function updateConsentMode(consent) {
    if (!config.consentMode) return;

    gtag('consent', 'update', {
      analytics_storage:        consent.analytics ? 'granted' : 'denied',
      ad_storage:               consent.marketing ? 'granted' : 'denied',
      ad_user_data:             consent.marketing ? 'granted' : 'denied',
      ad_personalization:       consent.marketing ? 'granted' : 'denied',
      functionality_storage:    consent.preferences ? 'granted' : 'denied',
      personalization_storage:  consent.preferences ? 'granted' : 'denied',
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

  function applyConsent(consent) {
    setConsent(consent);
    updateConsentMode(consent);
    pushDataLayer(consent);
    hideBanner();
    showFloatingBtn();
  }

  function acceptAll() {
    applyConsent({ necessary: true, analytics: true, marketing: true, preferences: true });
  }

  function rejectAll() {
    applyConsent({ necessary: true, analytics: false, marketing: false, preferences: false });
  }

  function saveCustom() {
    var consent = { necessary: true };
    var checks = document.querySelectorAll('.kcc-category__check');
    checks.forEach(function (el) {
      var cat = el.getAttribute('data-category');
      if (cat !== 'necessary') {
        consent[cat] = el.checked;
      }
    });
    applyConsent(consent);
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
        var checks = banner.querySelectorAll('.kcc-category__check');
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

    var btn = banner.querySelector('[data-kcc="toggle-details"]');
    if (btn) {
      btn.textContent = hidden ? (btn.getAttribute('data-close-text') || btn.textContent) : btn.textContent;
    }
  }

  function init() {
    banner = document.getElementById('kcc-banner');
    floatingBtn = document.getElementById('kcc-floating-btn');

    if (!banner) return;

    details = banner.querySelector('.kcc-details');

    banner.addEventListener('click', function (e) {
      var target = e.target.closest('[data-kcc]');
      if (!target) return;

      var action = target.getAttribute('data-kcc');
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

    var consent = getConsent();
    if (consent) {
      updateConsentMode(consent);
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
