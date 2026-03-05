# Kansleri Cookie Consent

Lightweight WordPress cookie consent plugin with full Google Consent Mode v2 support. No bloat, no ads, no build step needed.

## Features

- **Google Consent Mode v2** — outputs consent defaults in `<head>` before GTM
- **GTM dataLayer** — pushes `consent_update` events for tag firing
- **Cookie scanner** — discovers cookies via iframe scan and auto-identifies 40+ known cookies (Google Analytics, Meta, HubSpot, WordPress, Cloudflare, Hotjar, etc.)
- **Cookie policy page** — auto-creates a policy page with the `[kcc_cookie_policy]` shortcode
- **Three banner styles** — bottom bar, centered modal, or corner popup
- **Category-based consent** — Necessary, Analytics, Marketing, Preferences
- **Customizable texts and colors** — all banner text and primary color configurable from the admin
- **Floating re-open button** — lets visitors change their preferences anytime
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
- All button and heading texts
- Floating settings button toggle

### Cookies tab
- **Scan Cookies** — discovers cookies on your site and auto-fills provider, category, duration, and description for known cookies
- Manually add, edit, or remove cookies
- Each cookie has: name, category, provider, duration, description

### Policy Page tab
- Select or create a cookie policy page
- Add the `[kcc_cookie_policy]` shortcode to display a categorized cookie table

### Integration tab
- Enable/disable Google Consent Mode v2
- GTM Container ID reference field

## How the scanner works

The scanner loads your homepage in a hidden iframe, reads all cookies set by the page, and compares them against a built-in database of 40+ known cookie patterns. Recognized cookies are automatically grouped by provider (Google, Meta, WordPress, etc.) with pre-filled category, duration, and description. Unknown cookies are listed separately for manual categorization.

## Google Consent Mode v2

When enabled, the plugin:

1. Outputs `gtag('consent', 'default', {...})` in `<head>` with priority 1 (before GTM)
2. On page load, reads the `kcc_consent` cookie and calls `gtag('consent', 'update', {...})` if preferences exist
3. When the visitor makes a choice, updates consent in real-time and pushes a `consent_update` event to the dataLayer

### Consent mapping

| Category    | Consent Mode signals                                          |
|-------------|---------------------------------------------------------------|
| Analytics   | `analytics_storage`                                           |
| Marketing   | `ad_storage`, `ad_user_data`, `ad_personalization`            |
| Preferences | `functionality_storage`, `personalization_storage`            |
| Necessary   | `security_storage` (always granted)                           |

## File structure

```
kansleri-cookie-consent/
├── kansleri-cookie-consent.php   # Main plugin file
├── includes/
│   ├── class-admin.php           # Admin settings page
│   ├── class-scanner.php         # Cookie scanner + known cookies DB
│   ├── class-consent.php         # Frontend banner rendering + assets
│   ├── class-consent-mode.php    # Google Consent Mode v2 output
│   └── class-policy-page.php     # Policy page + shortcode
├── assets/
│   ├── admin.css                 # Admin styles
│   ├── admin.js                  # Admin interactions + scanner UI
│   ├── consent.css               # Frontend banner styles
│   └── consent.js                # Frontend consent logic
└── .gitignore
```

## License

GPLv2 or later.
