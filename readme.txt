=== Kansleri Cookie Consent ===
Contributors: kansleri
Tags: cookie consent, gdpr, google consent mode, cookie banner, privacy
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight cookie consent banner with Google Consent Mode v2, GTM dataLayer integration, cookie scanning, AI-assisted descriptions, consent statistics, and an auto-generated policy page.

== Description ==

Kansleri Cookie Consent is a lightweight, developer-friendly WordPress plugin that provides a fully customizable cookie consent banner with built-in support for Google Consent Mode v2.

**Features:**

* Three banner styles: bottom bar, centered modal, corner popup
* Customizable colors, texts, and button labels
* Theme or Plugin typography mode for consistent banner styling
* Four consent categories: Necessary, Analytics, Marketing, Preferences
* Google Consent Mode v2 integration with automatic default/update signals
* GTM dataLayer push on consent changes
* Built-in cookie scanner that auto-identifies common cookies (Google Analytics, Meta Pixel, WordPress core, and more)
* AI-assisted cookie descriptions — copy a prompt, paste into an AI tool, import the result
* Auto-generated cookie policy page with a dynamic shortcode
* Floating settings button for visitors to update preferences
* Consent statistics dashboard with Chart.js charts (no personal data stored)
* Google Site Kit integration — auto-detects and avoids duplicate consent signals
* WP Consent API integration — shares consent status with other plugins
* WPML and Polylang support for multilingual sites
* Finnish translation included with auto-localized defaults
* Daily cron check for missing cookie descriptions with admin notifications
* Built-in documentations tab with installation guide and option explanations
* Clean uninstall — removes all data when deleted

== Installation ==

1. Upload the `kansleri-cookie-consent` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress plugin screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Settings > Cookie Consent** to configure the banner.
4. Use the **Cookies** tab to scan your site and register cookies.
5. Publish the auto-created cookie policy page or add the `[kcc_cookie_policy]` shortcode to any page.

== Frequently Asked Questions ==

= Does this plugin support Google Consent Mode v2? =

Yes. When enabled under the Integration tab, the plugin outputs consent defaults in `<head>` before any other scripts, and sends `consent update` signals whenever a visitor changes their preferences. If Google Site Kit is active, duplicate defaults are automatically avoided.

= How does the cookie scanner work? =

The scanner loads your homepage in a hidden iframe, reads all cookies, and matches them against a built-in database of known cookie patterns (Google, Meta, WordPress, etc.). Known cookies are added automatically; unknown cookies are presented for categorization with an AI-assisted workflow.

= Does it work with WPML or Polylang? =

Yes. All banner texts are registered for translation with both WPML (via wpml-config.xml) and Polylang (via pll_register_string). Policy page links automatically resolve to the translated version.

= Does it work with Google Site Kit? =

Yes. The plugin detects Site Kit automatically. If Site Kit is managing consent mode defaults, this plugin skips its own default output and only sends consent updates when visitors make a choice. Consent Mode v2 is forced on when Site Kit is active.

= Does it work with WP Consent API? =

Yes. When the WP Consent API plugin is active, consent status is automatically shared with other plugins that support the API.

= How are consent statistics tracked? =

Statistics are tracked server-side via lightweight AJAX calls when visitors make a consent choice. No personal data, IP addresses, or tracking cookies are used. Only daily aggregates (accept/reject/custom counts) are stored.

= What happens when the plugin is uninstalled? =

All plugin options (`kcc_settings`, `kcc_cookies`, `kcc_consent_stats`, etc.) are removed from the database. The auto-created policy page is not deleted to prevent accidental content loss.

== Changelog ==

= 1.1.0 =
* Added consent statistics dashboard with Chart.js charts (7/30/90 day periods)
* Added Google Site Kit integration — auto-detects and skips duplicate consent defaults
* Added WP Consent API integration — shares consent status with other plugins
* Added WPML support via wpml-config.xml for translatable banner texts
* Added Polylang support with pll_register_string and translated policy page links
* Added full Finnish (fi) translation with auto-localized defaults
* Added AI-assisted cookie description workflow (copy prompt, paste AI response, import JSON)
* Added Theme/Plugin typography mode with strict style overrides
* Added daily cron job to check for cookies missing descriptions
* Added admin notification for incomplete cookie descriptions
* Added plugin activation notice with setup guidance
* Added Settings link in the plugin list
* Added Documentations tab with installation guide and per-option explanations
* Added "All systems are good" status label with cron check info
* Added cron error reporting on the settings page
* Improved floating settings button visibility (larger, border, stronger shadow, white icon)
* Improved consent update dispatch for robust GTM compatibility
* Fixed Close button opening settings instead of closing the banner
* Fixed consent mode forced on when Site Kit is active
* Floating settings button now off by default

= 1.0.3 =
* Hardened all XSS vectors in admin JavaScript
* Added server-side input validation (banner_style allowlist, hex color validation)
* Added Secure flag to consent cookie on HTTPS
* Replaced raw inline scripts with WordPress script APIs
* Added uninstall.php for clean data removal
* Added function_exists guards to all global functions
* Improved darken_color robustness
* Deduplicated category definitions into shared helper
* Fixed toggleDetails button text toggle
* Fixed event listener leak in scan results
* Set autoload to false for plugin options

= 1.0.2 =
* Bulletproofed cookie scanner with timeout, error handling, and clear messages
* Added @ini_set display_errors suppression in AJAX handlers
* Added Content-Type validation in JavaScript fetch calls

= 1.0.1 =
* Auto-grouping of known cookies in scan results
* Expanded known cookie database

= 1.0.0 =
* Initial release
