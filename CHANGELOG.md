# Changelog

All notable changes to CS Auto Gallery will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.0] - 2025-11-27

### 🔧 Improvements
- **Simplified Settings**: Replaced 6 confusing size fields with single "Thumbnail Size" setting
- **Better Documentation**: Added hint to use `col-auto` for fixed-size thumbnails in Grid Column Classes description

### 🐛 Bug Fixes
- **Unicode Encoding Fix**: Fixed PHP syntax error on line 180 caused by Unicode curly quotes corrupting during file transfer. Now uses hex escape sequences (`\xE2\x80\x99`) instead of literal Unicode characters.

## [1.5.0] - 2025-11-26

### 🚀 New Features
- **Enable Lightbox Toggle**: Added setting to turn GLightbox popup on/off
- **Image Sizing Options**: Added width/height settings for thumbnails
- **Auto URL Detection**: Automatically maps page URL slug to image folder (e.g., `/artists/bob-marley` → `B/Bob-Marley/`)

### 🔧 Improvements
- **Alphabetical Bucketing**: Images organized in A-Z folders for better organization
- **Bootstrap 5 Grid**: Responsive gallery using Bootstrap column classes
- **GLightbox Integration**: Modern lightbox with smooth animations

## [1.0.0] - 2025-11-25

### 🚀 Initial Release
- Bootstrap 5 responsive image gallery
- GLightbox integration for image popups
- Shortcode support: `{auto-gallery}`
- Configurable base path for image folders
- Caption generation from filenames
- Debug mode for troubleshooting paths
