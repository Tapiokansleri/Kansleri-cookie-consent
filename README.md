# Kansleri Cookie Consent

Lightweight WordPress cookie consent plugin with full Google Consent Mode v2 support. No bloat, no ads, no build step needed.

## Features

- **Google Consent Mode v2** — outputs consent defaults in `<head>` before GTM
- **GTM dataLayer** — pushes `consent_update` events for tag firing
- **Cookie scanner** — discovers cookies via iframe scan and auto-identifies 40+ known cookies (Google Analytics, Meta, HubSpot, WordPress, Cloudflare, Hotjar, etc.)
- **AI-assisted cookie descriptions** — copy a ready-made prompt, paste into ChatGPT/Claude/Gemini, import the result back as JSON
- **Cookie policy page** — auto-creates a policy page with the `[kcc_cookie_policy]` shortcode
- **Three banner styles** — bottom bar, centered modal, or corner popup
- **Category-based consent** — Necessary, Analytics, Marketing, Preferences
- **Theme or Plugin typography** — inherit site fonts or use strict plugin-defined styling
- **Customizable texts and colors** — all banner text and primary color configurable from the admin
- **Floating re-open button** — lets visitors change their preferences anytime
- **Consent statistics** — daily breakdown of accept/reject/custom choices with Chart.js visualization (no personal data stored)
- **Google Site Kit integration** — auto-detects Site Kit and avoids duplicate consent defaults
- **WP Consent API integration** — shares consent status with other plugins
- **WPML & Polylang support** — translatable banner texts, auto-resolves translated policy pages
- **Finnish translation included** — full `fi` locale with auto-localized defaults
- **Daily cron check** — automatically monitors cookies for missing descriptions
- **Documentations tab** — built-in installation guide and option explanations
- **No build step** — plain PHP, JS, and CSS; just upload and activate

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Upload the `kansleri-cookie-consent` folder to `/wp-content/plugins/`
2. Activate the plugin in **Plugins** > **Installed Plugins**
3. Go to **Settings** > **Cookie Consent** to configure

## Configuration

### General tab
- Banner style (bar / modal / corner)
- Primary color
- Text and font style (theme or plugin)
- All button and heading texts
- Floating settings button toggle

### Cookies tab
- **Scan Cookies** — discovers cookies on your site and auto-fills provider, category, duration, and description for known cookies
- **AI Helper** — appears when unknown cookies are found; copy the prompt to an AI tool, paste back the JSON, and click Import
- Manually add, edit, or remove cookies
- Each cookie has: name, category, provider, duration, description

### Policy Page tab
- Select or create a cookie policy page
- Add the `[kcc_cookie_policy]` shortcode to display a categorized cookie table

### Integration tab
- Enable/disable Google Consent Mode v2
- GTM Container ID reference field
- Auto-detected integration status for Google Site Kit and WP Consent API

### Statistics tab
- Daily consent response breakdown (accept all / reject all / custom)
- Summary cards with totals and percentages
- Stacked bar chart powered by Chart.js
- Filter by 7 / 30 / 90 day periods
- Privacy-friendly: no cookies, no personal data, just server-side aggregates

### Documentations tab
- Installation guide
- Explanation of every plugin option
- Troubleshooting tips

## How the scanner works

The scanner loads your homepage in a hidden iframe, reads all cookies set by the page, and compares them against a built-in database of 40+ known cookie patterns. Recognized cookies are automatically grouped by provider (Google, Meta, WordPress, etc.) with pre-filled category, duration, and description. Unknown cookies are added to the table for categorization — use the AI helper to generate descriptions.

## Google Consent Mode v2

When enabled, the plugin:

1. Outputs `gtag('consent', 'default', {...})` in `<head>` with priority 1 (before GTM)
2. On page load, reads the `kcc_consent` cookie and calls `gtag('consent', 'update', {...})` if preferences exist
3. When the visitor makes a choice, updates consent in real-time and pushes a `consent_update` event to the dataLayer

If Google Site Kit is active and managing consent mode, the plugin skips its own default output to avoid duplicates and only sends consent updates.

### Consent mapping

| Category    | Consent Mode signals                                          |
|-------------|---------------------------------------------------------------|
| Analytics   | `analytics_storage`                                           |
| Marketing   | `ad_storage`, `ad_user_data`, `ad_personalization`            |
| Preferences | `functionality_storage`, `personalization_storage`            |
| Necessary   | `security_storage` (always granted)                           |

## Translations

The plugin ships with a full Finnish (`fi`) translation. When the WordPress site language is set to Finnish, all admin UI and frontend banner texts are displayed in Finnish. Default banner texts are automatically localized on first run.

### WPML
A `wpml-config.xml` file registers all user-editable banner texts for WPML String Translation. Policy page links auto-resolve to the translated page.

### Polylang
Banner texts are registered via `pll_register_string()` and appear in Polylang's Strings translations screen. Policy page links auto-resolve via `pll_get_post()`.

## File structure

```
kansleri-cookie-consent/
├── kansleri-cookie-consent.php   # Main plugin file
├── wpml-config.xml               # WPML string registration
├── includes/
│   ├── class-admin.php           # Admin settings page + statistics
│   ├── class-scanner.php         # Cookie scanner + known cookies DB
│   ├── class-consent.php         # Frontend banner rendering + assets
│   ├── class-consent-mode.php    # Google Consent Mode v2 + Site Kit compat
│   ├── class-policy-page.php     # Policy page + shortcode
│   ├── class-wp-consent-api.php  # WP Consent API bridge
│   └── class-stats.php           # Consent statistics tracking
├── assets/
│   ├── admin.css                 # Admin styles
│   ├── admin.js                  # Admin interactions + scanner UI
│   ├── consent.css               # Frontend banner styles
│   └── consent.js                # Frontend consent logic
├── languages/
│   ├── kansleri-cookie-consent.pot          # Translation template
│   ├── kansleri-cookie-consent-fi.po        # Finnish translation source
│   └── kansleri-cookie-consent-fi.mo        # Finnish translation binary
└── .gitignore
```

## License

GPLv2 or later.
