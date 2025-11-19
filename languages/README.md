# Translations / Tłumaczenia

This directory contains translation files for CF7 GetResponse Integration plugin.

## Available Languages / Dostępne języki

- **English (en_US)** - Default / Domyślny
- **Polish (pl_PL)** - Complete / Kompletny

## How to Compile Translations / Jak skompilować tłumaczenia

### Method 1: Using Poedit (Recommended)

1. Download and install [Poedit](https://poedit.net/)
2. Open `cf7-getresponse-pl_PL.po` in Poedit
3. Click **File → Save** (Poedit will automatically generate .mo file)

### Method 2: Using msgfmt (Command Line)

```bash
# For Polish
msgfmt cf7-getresponse-pl_PL.po -o cf7-getresponse-pl_PL.mo

# For other languages
msgfmt cf7-getresponse-{locale}.po -o cf7-getresponse-{locale}.mo
```

### Method 3: Online Tool

1. Go to https://po2mo.net/
2. Upload `cf7-getresponse-pl_PL.po`
3. Download generated `.mo` file
4. Place it in this directory

## Creating New Translation / Tworzenie nowego tłumaczenia

1. Copy `cf7-getresponse.pot` to `cf7-getresponse-{locale}.po`
   Example: `cf7-getresponse-de_DE.po` for German

2. Open the file in Poedit or text editor

3. Fill in the header information:
   ```
   "Language: de_DE\n"
   "Language-Team: German\n"
   ```

4. Translate all `msgstr` strings

5. Save and compile to `.mo` format

6. Place both `.po` and `.mo` files in this directory

## File Structure / Struktura plików

```
languages/
├── README.md                          # This file
├── cf7-getresponse.pot               # Template (for creating new translations)
├── cf7-getresponse-pl_PL.po          # Polish translation source
└── cf7-getresponse-pl_PL.mo          # Polish translation binary (after compilation)
```

## Testing Translations / Testowanie tłumaczeń

1. Make sure `.mo` file exists
2. Go to WordPress → Settings → General
3. Change **Site Language** to your language (e.g., Polski)
4. Visit the plugin settings page (CF7 → GR)
5. Interface should display in selected language

## Contributing Translations / Wkład w tłumaczenia

If you want to contribute a translation in your language:

1. Create translation using the template file
2. Test it on your WordPress installation
3. Submit a pull request or contact us at https://iql.pl

---

**Note:** After updating plugin code, remember to update `.pot` file and sync all `.po` files!

**Uwaga:** Po aktualizacji kodu wtyczki, pamiętaj o aktualizacji pliku `.pot` i synchronizacji wszystkich plików `.po`!
