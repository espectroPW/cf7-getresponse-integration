# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin that integrates Contact Form 7 (CF7) with GetResponse email marketing. When a CF7 form is submitted, the plugin sends contact data to GetResponse campaigns via their REST API v3.

## Architecture

This is a single-class WordPress plugin (`CF7_GetResponse_Integration`) in one PHP file:

- **[cf7-getresponse-integration.php](cf7-getresponse-integration.php)** - Entire plugin logic: admin UI (inline HTML), settings persistence (`wp_options`), CF7 submission handling (`wpcf7_mail_sent` hook), and GetResponse API calls (cURL to `https://api.getresponse.com/v3/`)
- **[admin-script.js](admin-script.js)** - jQuery-based admin UI: AJAX campaign loading, dynamic custom field rows, accordion toggle
- **[admin-style.css](admin-style.css)** - Admin settings page styles
- **[languages/](languages/)** - i18n files (POT template, PO/MO for Polish). Text domain: `cf7-getresponse`

### Key Concepts

- **Mappings** - Per-form configurations stored in `wp_options` under key `cf7_gr_mappings`. Each mapping ties a CF7 form ID to a GetResponse campaign, operation mode, field mappings, and API key.
- **Three operation modes**: `always` (send every submission), `checkbox` (send only if acceptance field checked), `dual` (always send to primary list + secondary list on consent)
- **Custom fields** - Map arbitrary CF7 fields to GetResponse custom field IDs (`customFieldValues` in API)
- API responses 201, 202, 409 are all treated as success (409 = contact already exists)

## Development

This plugin runs inside WordPress - there is no build step, test suite, or linter configured. To develop:

1. Symlink or copy the plugin directory into a WordPress install's `wp-content/plugins/`
2. Activate the plugin and Contact Form 7 in WordPress admin
3. Plugin settings page is at **CF7 → GR** in the admin menu

### Compile translations

```bash
cd languages
php compile-translations.php
# Or: msgfmt cf7-getresponse-pl_PL.po -o cf7-getresponse-pl_PL.mo
```

## Conventions

- Plugin UI strings are in Polish (hardcoded in HTML) with translatable strings using `__()` / `esc_html__()` with text domain `cf7-getresponse`
- Debug logging uses `error_log()` with prefix `CF7→GR [Form {id}]:`
- Uses raw cURL (not `wp_remote_post`) for GetResponse API calls
- WordPress requires PHP 7.0+, WP 5.0+
