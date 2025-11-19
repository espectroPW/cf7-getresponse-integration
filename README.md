# CF7 GetResponse Integration

![Version](https://img.shields.io/badge/version-3.1.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.0+-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v2-red.svg)

Professional WordPress plugin for seamless integration between Contact Form 7 and GetResponse email marketing platform.

## 🚀 Features

### Core Functionality
- **Automatic Campaign Loading** - Fetch and select GetResponse campaigns directly from the admin panel
- **Dual-List Support** - Send contacts to two different lists based on user consent
- **Custom Fields Mapping** - Map CF7 form fields to GetResponse custom fields
- **Three Operating Modes**:
  - **Always** - Send every form submission to GetResponse
  - **Checkbox** - Send only when user checks a specific acceptance field
  - **Dual** - Send to primary list always + secondary list on consent

### Technical Features
- **AJAX Campaign Loading** - No manual ID copying required
- **Security First** - Nonce verification, capability checks, data sanitization
- **Error Handling** - Comprehensive logging and error management
- **Responsive UI** - Mobile-friendly admin interface
- **WordPress Standards** - Follows WordPress coding standards and best practices

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- Contact Form 7 plugin
- GetResponse account with API access

## 🔧 Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/cf7-getresponse-integration/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **CF7 → GR** in the admin menu

## ⚙️ Configuration

### 1. Get Your GetResponse API Key

1. Log in to your GetResponse account
2. Go to **Menu → Integrations and API → API**
3. Generate or copy your API Key

### 2. Configure Plugin Settings

1. Go to **CF7 → GR** in WordPress admin
2. Find your Contact Form 7 form
3. Enable the toggle switch
4. Paste your API Key
5. Click **"Load Lists"** button
6. Select your campaign(s) from dropdown
7. Choose operation mode
8. Map form fields (email, name, custom fields)
9. Save settings

### Operation Modes

#### Always Mode
Sends every form submission to GetResponse automatically.

```
User submits form → GetResponse (Primary List)
```

#### Checkbox Mode
Only sends to GetResponse when user checks the acceptance field.

```
User submits form + checkbox ✓ → GetResponse (Primary List)
User submits form + checkbox ✗ → Nothing sent
```

#### Dual Mode
Sends to primary list always, sends to secondary list only with consent.

```
User submits form + checkbox ✓ → GetResponse (Primary + Secondary Lists)
User submits form + checkbox ✗ → GetResponse (Primary List only)
```

**Example Use Case:**
- Primary List: "Contact Requests" (always)
- Secondary List: "Newsletter Subscribers" (on consent)

## 🎯 Custom Fields Mapping

Map any CF7 field to GetResponse custom fields:

1. Click **"Add Field"**
2. Select CF7 field (e.g., `your-phone`)
3. Enter GetResponse Custom Field ID (e.g., `pqRst`)
4. Add description for reference
5. Repeat for additional fields

### Finding Custom Field ID in GetResponse

1. Go to **Contacts → Custom Fields**
2. Find or create your custom field
3. Copy the field ID (e.g., `pqRst`)

## 📝 Example Configuration

### Contact Form 7 Setup
```
<label> Your Name
    [text* your-name] </label>

<label> Your Email
    [email* your-email] </label>

<label> Phone Number
    [tel your-phone] </label>

<label> [acceptance newsletter "Subscribe to newsletter"]
</label>

[submit "Send"]
```

### Plugin Configuration
- **Mode**: Dual
- **Primary List**: "Contact Requests"
- **Secondary List**: "Newsletter Subscribers"
- **Acceptance Field**: `newsletter`
- **Email Field**: `your-email`
- **Name Field**: `your-name`
- **Custom Fields**:
  - `your-phone` → `pqRst` (Phone Number)

## 🔍 Debugging

All operations are logged using WordPress `error_log()`:

```php
// Success
CF7→GR [Form 123]: ✅ Email 'user@example.com' added to primary list (VaxYZ)

// Checkbox not checked
CF7→GR [Form 123]: ℹ️ Checkbox not checked - skipping secondary list

// Error
CF7→GR [Form 123]: ❌ Error adding 'user@example.com'
```

View logs in your WordPress debug.log file.

## 🛡️ Security

- ✅ Nonce verification on all forms
- ✅ Capability checks (`manage_options`)
- ✅ Data sanitization (`sanitize_text_field()`, `esc_html()`, `esc_attr()`)
- ✅ Email validation (`is_email()`)
- ✅ AJAX nonce verification
- ✅ cURL SSL verification enabled
- ✅ Timeout protection (10 seconds)

## 🌍 Translations

The plugin is fully translation-ready and comes with:

- **English (en_US)** - Default
- **Polish (pl_PL)** - Complete translation included

### Adding New Language

1. Navigate to `/languages/` directory
2. Copy `cf7-getresponse.pot` to `cf7-getresponse-{locale}.po`
3. Translate using Poedit or text editor
4. Compile to `.mo` format:
   ```bash
   cd languages
   php compile-translations.php
   ```
5. Or use [Poedit](https://poedit.net/) to automatically generate `.mo` file

See `/languages/README.md` for detailed instructions.

### Changing Plugin Language

1. Go to **WordPress → Settings → General**
2. Change **Site Language** to your preferred language
3. Plugin interface will automatically switch

## 🎨 Customization

### Hooks

The plugin doesn't currently provide custom hooks, but you can extend it by modifying the class.

### Text Domain

All strings use text domain `cf7-getresponse` for translations.

## 🐛 Troubleshooting

### Lists not loading
- Verify API Key is correct
- Check WordPress debug.log for cURL errors
- Ensure server can make external HTTPS requests

### Contacts not being sent
- Check operation mode configuration
- Verify acceptance field is configured (checkbox/dual modes)
- Review debug logs for specific error messages
- Confirm Campaign ID is valid

### Custom fields not appearing
- Verify Custom Field ID matches GetResponse exactly
- Ensure custom field exists in GetResponse account
- Check that CF7 field name is correct

## 📜 Changelog

### Version 3.1.0
- ✨ Added PHPDoc documentation
- ✨ Improved code structure and standards
- ✨ Enhanced security with proper escaping
- ✨ Added comprehensive inline comments
- 🔧 Updated to use dynamic versioning
- 🌍 Full multilingual support (English & Polish)
- 📝 Translation-ready with POT/PO/MO files
- 🔧 Added translation compilation script

### Version 3.0.0
- ✨ Added dual-list support
- ✨ Automatic campaign loading via AJAX
- ✨ Three operation modes (always/checkbox/dual)
- ✨ Custom fields mapping
- ✨ Responsive admin interface
- 🐛 Security improvements
- 🐛 Enhanced error handling

## 👨‍💻 Author

**IQLevel vel Espectro**
- Website: [https://iql.pl](https://iql.pl)

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## 🤝 Support

For bug reports and feature requests, please use the GitHub issue tracker.

## 🙏 Credits

Built with:
- [Contact Form 7](https://contactform7.com/)
- [GetResponse API v3](https://apidocs.getresponse.com/)
- [WordPress](https://wordpress.org/)

---

Made with ❤️ by IQLevel vel Espectro
