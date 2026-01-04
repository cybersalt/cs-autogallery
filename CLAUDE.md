# Claude Code Notes for CS Auto Gallery

## Joomla Development

Use the `/joomla-development` skill for Joomla 5/6 extension development guidance.

### Building Packages

**CRITICAL**: Always use 7-Zip to create ZIP packages, NOT PowerShell's `Compress-Archive`:

```powershell
cd build
& 'C:\Program Files\7-Zip\7z.exe' a -tzip '..\plg_content_csautogallery_vX.X.X.zip' *
```

PowerShell's built-in compression does NOT create proper directory entries, causing Joomla installer failures.

## Project Structure

- `build/` - Build-ready files for packaging (main source)
- `build/csautogallery.php` - Main plugin file
- `build/csautogallery.xml` - Joomla manifest

## Known Issues

### Unicode Character Encoding (Fixed in v1.6.0)

Line ~180 in `slugToFolderName()` originally used literal Unicode curly quotes that could cause syntax errors on some servers. Fixed by using hex escape sequences:

```php
// Old (problematic):
$p = str_replace([''', '`', '´'], "'", $p);

// New (safe):
$p = str_replace(["\xE2\x80\x99", '`', "\xC2\xB4"], "'", $p);
```
