# Kansleri Cookie Consent

> **This plugin is now available on WordPress.org!**
> Install it directly from your WordPress dashboard: **Plugins > Add New > Search "Kansleri Cookie Consent"**
>
> **[Download from WordPress.org](https://wordpress.org/plugins/kansleri-cookie-consent/)**
>
> Future updates are delivered through WordPress.org. This GitHub repository is maintained as a public mirror for reference and issue tracking.

---

Lightweight WordPress cookie consent plugin with full Google Consent Mode v2 support. No bloat, no ads, no build step needed.

## Features

- **Google Consent Mode v2** — outputs consent defaults in `<head>` before GTM
- **GTM dataLayer** — pushes `consent_update` events for tag firing
- **Cookie scanner** — discovers cookies via iframe scan and auto-identifies 40+ known cookies (Google Analytics, Meta, HubSpot, WordPress, Cloudflare, Hotjar, etc.)
- **AI-assisted cookie descriptions** — copy a ready-made prompt, paste into ChatGPT/Claude/Gemini, import the result back as JSON
- **Cookie policy page** — auto-creates a policy page with the `[kncc_cookie_policy]` shortcode
- **YouTube & Vimeo iframe blocking** — video embeds blocked until marketing cookies accepted, with placeholder and accept button
- **`[kncc_cookie_settings]` shortcode** — add a cookie settings link anywhere (footer, menu, widgets)
- **Three banner styles** — bottom bar, centered modal, or corner popup
- **Category-based consent** — Necessary, Analytics, Marketing, Preferences
- **Theme or Plugin typography** — inherit site fonts or use strict plugin-defined styling
- **Customizable texts and colors** — all banner text and primary color configurable from the admin
- **Floating re-open button** — lets visitors change their preferences anytime
- **Consent statistics** — daily breakdown of accept/reject/custom choices with Chart.js visualization (no personal data stored)
- **Google Site Kit integration** — auto-detects Site Kit and avoids duplicate consent defaults
- **WP Consent API integration** — shares consent status with other plugins
- **WPML & Polylang support** — per-language banner texts, cookie descriptions, banner style, colors, and policy page
- **Finnish translation included** — full `fi` locale with auto-localized defaults
- **Daily cron check** — automatically monitors cookies for missing descriptions
- **Documentations tab** — built-in installation guide and option explanations
- **No build step** — plain PHP, JS, and CSS; just upload and activate

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

The recommended way is to install directly from WordPress.org:

1. In your WordPress admin, go to **Plugins > Add New**
2. Search for **"Kansleri Cookie Consent"**
3. Click **Install Now**, then **Activate**
4. Go to **Settings > Cookie Consent** to configure

### Manual installation

1. Download the latest release from [WordPress.org](https://wordpress.org/plugins/kansleri-cookie-consent/)
2. Upload the `kansleri-cookie-consent` folder to `/wp-content/plugins/`
3. Activate the plugin in **Plugins > Installed Plugins**

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
- Add the `[kncc_cookie_policy]` shortcode to display a categorized cookie table

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

## Multilingual support (WPML & Polylang)

- **Per-language banner texts** — switch language in admin bar and edit General tab settings
- **Per-language cookie descriptions** — cookie structure is shared, only descriptions are translatable
- **Per-language settings** — banner style, colors, and policy page can differ per language
- **AI prompts** — auto-generated in the current admin language

## License

GPLv2 or later.
