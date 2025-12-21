# Translations for DownTranscoder

This directory contains translation files for the DownTranscoder Nextcloud app.

## How to Contribute Translations

### Using Nextcloud's Translation System

The easiest way to contribute translations is through Nextcloud's official translation platform (Transifex) once the app is published to the Nextcloud App Store.

### Manual Translation

If you want to translate manually:

1. Copy `templates.pot` to create a new language file:
   ```bash
   cp templates.pot fr.po  # For French
   cp templates.pot de.po  # For German
   # etc.
   ```

2. Edit the `.po` file and fill in the `msgstr` fields with translations for your language.

3. Update the header information:
   - Replace `YEAR` with the current year
   - Replace `FULL NAME <EMAIL@ADDRESS>` with your name and email
   - Set the `Language` field to your language code (e.g., `fr`, `de`, `es`)

4. Submit a pull request with your translation file.

## Automatic Compilation

Nextcloud automatically compiles `.po` files to `.json` files when the app is installed or updated. You don't need to manually compile translations.

## Translation Template Generation

To regenerate the translation template after making changes to translatable strings:

```bash
# For PHP files
xgettext --language=PHP --keyword=t --keyword=n:1,2 \
  --from-code=UTF-8 --output=l10n/templates.pot \
  lib/**/*.php templates/**/*.php

# For JavaScript files
xgettext --language=JavaScript --keyword=t --keyword=n:1,2 \
  --from-code=UTF-8 --output=l10n/js-templates.pot \
  js/**/*.js
```

Or use Nextcloud's translation tools if available.

## Current Translation Coverage

- English (en): 100% (source language)
- French (fr): Add your translation!
- German (de): Add your translation!
- Spanish (es): Add your translation!
- And many more...

## Questions?

For questions about translations, please open an issue on GitHub:
https://github.com/francoisnd/jellyfin-downtranscoder/issues
