# Claude Code Notes for CS Auto Gallery

## Joomla Brain Reference

**IMPORTANT**: This project uses the Joomla Brain as a git submodule located at `.joomla-brain/`.

The Joomla Brain is the **main shared repository** at `https://github.com/cybersalt/Joomla-Brain.git` - all Joomla projects should reference this same repository as a submodule, NOT a copy.

### Key Resources in Joomla Brain

- **Build Notes**: `.joomla-brain/PACKAGE-BUILD-NOTES.md` - Critical ZIP creation info (use 7-Zip!)
- **Joomla 5 Checklist**: `.joomla-brain/JOOMLA5-CHECKLIST.md`
- **Best Practices**: `.joomla-brain/README.md`
- **File Encoding**: `.joomla-brain/FILE-CORRUPTION-FIX.md`

### Building Packages

**CRITICAL**: Always use 7-Zip to create ZIP packages, NOT PowerShell's `Compress-Archive`:

```powershell
cd build
& 'C:\Program Files\7-Zip\7z.exe' a -tzip '..\plg_content_csautogallery.zip' *
```

PowerShell's built-in compression does NOT create proper directory entries, causing Joomla installer failures.

### Updating Joomla Brain

```bash
git submodule update --remote .joomla-brain
git add .joomla-brain
git commit -m "Update Joomla Brain submodule"
```

## Project Structure

- `csautogallery.php` - Main legacy plugin file (root)
- `build/` - Build-ready files for packaging
- `src/Extension/` - Joomla 5 native plugin (for future use)
- `verify/` - Test/verification copy

## Known Issues

### Unicode Character Encoding (Fixed)

Line ~180 in `slugToFolderName()` originally used literal Unicode curly quotes that could cause syntax errors on some servers. Fixed by using hex escape sequences:

```php
// Old (problematic):
$p = str_replace([''', '`', '´'], "'", $p);

// New (safe):
$p = str_replace(["\xE2\x80\x99", '`', "\xC2\xB4"], "'", $p);
```
