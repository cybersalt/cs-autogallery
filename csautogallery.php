<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.csautogallery
 * @version     1.6.1
 * @since       5.0
 * @copyright   (C) 2025 Cybersalt Consulting Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class PlgContentCsautogallery extends CMSPlugin
{
    protected $autoloadLanguage = true;
    private static $renderedInstances = [];

    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if (!is_object($article) || empty($article->text) || strpos($article->text, '{auto-gallery') === false) {
            return;
        }

        $regex = '/\{auto-gallery(?P<attrs>[^}]*)\}/u';

        $article->text = preg_replace_callback($regex, function ($m) use ($context, $article) {
            $attrString = trim($m['attrs'] ?? '');
            $attrs = $this->parseAttrs($attrString);

            // 'instance' and 'once' allow preventing duplicates across page
            $instance = isset($attrs['instance']) ? (string) $attrs['instance'] : '';
            $once     = isset($attrs['once']) ? (bool) (int) $attrs['once'] : false;
            if ($once) {
                if ($instance === '') {
                    $instance = 'default';
                }
                if (isset(self::$renderedInstances[$instance])) {
                    return ''; // already rendered this instance
                }
                self::$renderedInstances[$instance] = true;
            }

            // Params with shortcode overrides
            $enableLightbox = (bool) $this->params->get('enable_lightbox', 1);
            $baseFs       = rtrim($this->getParamDefaultPath('base_fs', JPATH_ROOT . '/images/music'), '/');
            $baseUrl      = rtrim($this->params->get('base_url', Uri::root(true) . '/images/music'), '/');
            $extensions   = $this->params->get('extensions', 'jpg,jpeg,png,gif,webp,avif');
            $colClasses   = $this->params->get('col_classes', 'col-6 col-sm-4 col-md-3 col-lg-3');
            $showCaptions = (bool) $this->params->get('show_captions', 1);
            $showEmpty    = (bool) $this->params->get('show_empty_message', 1);
            $showDebug    = (bool) $this->params->get('show_debug_path', 0);
            $filenamePrefix = (string) $this->params->get('filename_prefix', '');

            // Image sizing params
            $imageWidth  = (string) $this->params->get('image_width', '');
            $imageHeight = (string) $this->params->get('image_height', '');

            // Thumbnail sizing params
            $thumbMinW = (string) $this->params->get('thumb_min_w', '');
            $thumbMaxW = (string) $this->params->get('thumb_max_w', '');
            $thumbMinH = (string) $this->params->get('thumb_min_h', '');
            $thumbMaxH = (string) $this->params->get('thumb_max_h', '');

            // Shortcode overrides (per tag)
            $enableLightbox = isset($attrs['lightbox']) ? (bool) (int) $attrs['lightbox'] : $enableLightbox;
            $baseFs       = isset($attrs['base_fs']) ? $this->applyJpathToken($attrs['base_fs']) : $baseFs;
            $baseUrl      = isset($attrs['base_url']) ? rtrim($attrs['base_url'], '/') : $baseUrl;
            $extensions   = $attrs['extensions'] ?? $extensions;
            $colClasses   = $attrs['col_classes'] ?? $colClasses;
            $showCaptions = isset($attrs['show_captions']) ? (bool) (int) $attrs['show_captions'] : $showCaptions;
            $showEmpty    = isset($attrs['show_empty_message']) ? (bool) (int) $attrs['show_empty_message'] : $showEmpty;
            $showDebug    = isset($attrs['show_debug_path']) ? (bool) (int) $attrs['show_debug_path'] : $showDebug;
            $filenamePrefix = isset($attrs['prefix']) ? (string) $attrs['prefix'] : $filenamePrefix;

            // Per-shortcode overrides for image sizing
            $imageWidth  = isset($attrs['width']) ? (string) $attrs['width'] : $imageWidth;
            $imageHeight = isset($attrs['height']) ? (string) $attrs['height'] : $imageHeight;

            // Per-shortcode overrides for thumb sizing
            $thumbMinW = isset($attrs['thumb_min_w']) ? (string) $attrs['thumb_min_w'] : $thumbMinW;
            $thumbMaxW = isset($attrs['thumb_max_w']) ? (string) $attrs['thumb_max_w'] : $thumbMaxW;
            $thumbMinH = isset($attrs['thumb_min_h']) ? (string) $attrs['thumb_min_h'] : $thumbMinH;
            $thumbMaxH = isset($attrs['thumb_max_h']) ? (string) $attrs['thumb_max_h'] : $thumbMaxH;

            $slug = $attrs['slug'] ?? $this->slugFromRequest();
            $folderOverride = $attrs['folder'] ?? '';

            if ($folderOverride !== '') {
                [$dirFs, $dirUrl, $title] = $this->resolveFromFolder($folderOverride, $baseFs, $baseUrl);
            } else {
                [$dirFs, $dirUrl, $title] = $this->mapSlugToFolder($slug, $baseFs, $baseUrl);
            }

            $exts = array_filter(array_map('strtolower', array_map('trim', explode(',', (string) $extensions))));
            if (!$exts) {
                $exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
            }

            $images = $this->findImages($dirFs, $dirUrl, $exts, $baseFs, $filenamePrefix);

            $this->enqueueAssets($enableLightbox, $imageWidth, $imageHeight);

            // Build inline CSS variables for this gallery instance
            $styleVars = [];
            if ($thumbMinW !== '') {
                $styleVars[] = '--thumb-min-w:' . htmlspecialchars($thumbMinW, ENT_QUOTES, 'UTF-8');
            }
            if ($thumbMaxW !== '') {
                $styleVars[] = '--thumb-max-w:' . htmlspecialchars($thumbMaxW, ENT_QUOTES, 'UTF-8');
            }
            if ($thumbMinH !== '') {
                $styleVars[] = '--thumb-min-h:' . htmlspecialchars($thumbMinH, ENT_QUOTES, 'UTF-8');
            }
            if ($thumbMaxH !== '') {
                $styleVars[] = '--thumb-max-h:' . htmlspecialchars($thumbMaxH, ENT_QUOTES, 'UTF-8');
            }
            if ($imageWidth !== '') {
                $styleVars[] = '--img-width:' . htmlspecialchars($imageWidth, ENT_QUOTES, 'UTF-8');
            }
            if ($imageHeight !== '') {
                $styleVars[] = '--img-height:' . htmlspecialchars($imageHeight, ENT_QUOTES, 'UTF-8');
            }
            $styleAttr = $styleVars ? ' style="' . implode(';', $styleVars) . '"' : '';

            $output = '';
            if ($showDebug) {
                $origin = htmlspecialchars((string) $context, ENT_QUOTES, 'UTF-8');
                $sourceId = '';
                if (is_object($article)) {
                    if (isset($article->id)) {
                        $sourceId = ' #' . (int) $article->id;
                    } elseif (isset($article->moduleid)) {
                        $sourceId = ' #' . (int) $article->moduleid;
                    }
                }
                $output .= '<div class="alert alert-secondary cs-auto-gallery">'
                    . 'Looking for images in: <code>' . htmlspecialchars($dirUrl, ENT_QUOTES, 'UTF-8') . '</code>'
                    . '<br><small>Context: ' . $origin . $sourceId . '</small>'
                    . '</div>';
            }
            $output .= $this->renderGalleryHtml($images, $title, $colClasses, $showCaptions, $showEmpty, $enableLightbox, $styleAttr);
            return $output;
        }, $article->text);
    }

    private function parseAttrs($s)
    {
        $out = [];
        if ($s === '') {
            return $out;
        }
        $pattern = '/([a-zA-Z0-9_\-]+)\s*=\s*(\"([^\"]*)\"|\'([^\']*)\'|([^\s]+))/u';
        if (preg_match_all($pattern, $s, $m, PREG_SET_ORDER)) {
            foreach ($m as $a) {
                $key = strtolower($a[1]);
                $val = $a[3] !== '' ? $a[3] : ($a[4] !== '' ? $a[4] : $a[5]);
                $out[$key] = $val;
            }
        }
        return $out;
    }

    private function slugFromRequest()
    {
        $path = Uri::getInstance()->getPath();
        $parts = array_values(array_filter(explode('/', (string) $path), 'strlen'));
        $last = end($parts) ?: '';
        return strtolower(trim(preg_replace('~[^a-z0-9\-]+~u', '-', $last), '-'));
    }

    private function slugToFolderName($slug)
    {
        if ($slug === '') {
            return '';
        }
        $parts = array_filter(explode('-', $slug), 'strlen');
        $pretty = array_map(function ($p) {
            // Replace curly quotes and backticks with straight apostrophe
            $p = str_replace(["\xE2\x80\x99", '`', "\xC2\xB4"], "'", $p);
            return mb_convert_case($p, MB_CASE_TITLE_SIMPLE, 'UTF-8');
        }, $parts);
        return implode('-', $pretty);
    }

    private function mapSlugToFolder($slug, $baseFs, $baseUrl)
    {
        $folderName = $this->slugToFolderName($slug);
        $bucket = $folderName !== '' ? strtoupper(mb_substr($folderName, 0, 1)) : '';
        if ($bucket === '' || !preg_match('~[A-Z]~', $bucket)) {
            $bucket = '0-9';
        }
        $dirFs = $baseFs . '/' . $bucket . '/' . $folderName;
        $dirUrl = $baseUrl . '/' . rawurlencode($bucket) . '/' . rawurlencode($folderName);
        $title  = $folderName !== '' ? $folderName : 'Gallery';
        return [$dirFs, $dirUrl, $title];
    }

    private function resolveFromFolder($folder, $baseFs, $baseUrl)
    {
        $clean = trim($folder, '/');
        $clean = preg_replace('~\.+/~', '', $clean);
        $dirFs = $baseFs . '/' . $clean;
        $dirUrl = $baseUrl . '/' . implode('/', array_map('rawurlencode', explode('/', $clean)));
        $title = str_replace('-', ' ', basename($clean));
        $title = mb_convert_case($title, MB_CASE_TITLE_SIMPLE, 'UTF-8');
        return [$dirFs, $dirUrl, $title];
    }

    private function findImages($dirFs, $dirUrl, $exts, $baseFs, $prefixFilter = '')
    {
        $images = [];
        $realBase = realpath($baseFs);
        $realDir  = is_dir($dirFs) ? realpath($dirFs) : false;
        if (!$realDir || !$realBase || strpos($realDir, $realBase) !== 0) {
            return $images;
        }

        // Parse prefix filter (comma-separated, case-insensitive)
        $prefixes = [];
        if ($prefixFilter !== '') {
            $prefixes = array_filter(array_map('trim', explode(',', $prefixFilter)));
        }

        foreach (glob($realDir . '/*.*') ?: [] as $pathFs) {
            $ext = strtolower(pathinfo($pathFs, PATHINFO_EXTENSION));
            if (!in_array($ext, $exts, true) || !is_file($pathFs)) {
                continue;
            }
            $name = basename($pathFs);

            // Filter by prefix if specified
            if (!empty($prefixes)) {
                $matches = false;
                foreach ($prefixes as $prefix) {
                    if (stripos($name, $prefix) === 0) {
                        $matches = true;
                        break;
                    }
                }
                if (!$matches) {
                    continue;
                }
            }

            $images[] = [
                'url' => $dirUrl . '/' . rawurlencode($name),
                'name' => $name,
            ];
        }
        usort($images, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });
        return $images;
    }

    private function enqueueAssets($enableLightbox, $imageWidth, $imageHeight)
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        // Only load GLightbox if lightbox is enabled
        if ($enableLightbox) {
            if (!$wa->assetExists('style', 'glightbox')) {
                $wa->registerStyle('glightbox', 'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css', [], ['rel' => 'stylesheet']);
            }
            if (!$wa->assetExists('script', 'glightbox')) {
                $wa->registerScript('glightbox', 'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js', [], ['defer' => true]);
            }
            $wa->useStyle('glightbox')->useScript('glightbox');
        }

        static $inited = false;
        if (!$inited) {
            $inited = true;

            if ($enableLightbox) {
                $init = "document.addEventListener('DOMContentLoaded',function(){ if(window.GLightbox){ GLightbox({selector:'.glightbox'}); }});";
                $wa->addInlineScript($init);
            }

            $css = ".cs-auto-gallery .thumb{aspect-ratio:1/1;overflow:hidden;border-radius:.5rem;
  min-width:var(--thumb-min-w, initial);
  max-width:var(--thumb-max-w, none);
  min-height:var(--thumb-min-h, initial);
  max-height:var(--thumb-max-h, none);
}
.cs-auto-gallery img{
  width:var(--img-width, 100%);
  height:var(--img-height, 100%);
  display:block;
  object-fit:cover;
  transition:transform .18s ease;
}
.cs-auto-gallery a:hover img,.cs-auto-gallery .gallery-item:hover img{transform:scale(1.03);}";
            $wa->addInlineStyle($css);
        }
    }

    private function renderGalleryHtml($images, $title, $colClasses, $showCaptions, $showEmpty, $enableLightbox, $styleAttr = '')
    {
        if (empty($images)) {
            if ($showEmpty) {
                return '<div class="alert alert-info cs-auto-gallery">No images found for <strong>'
                    . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                    . '</strong>.</div>';
            }
            return '';
        }

        $html = [];
        $html[] = '<div class="cs-auto-gallery"' . $styleAttr . '><div class="row g-3">';
        foreach ($images as $img) {
            $caption = $this->filenameToCaption($img['name']);
            $imgUrl  = htmlspecialchars($img['url'], ENT_QUOTES, 'UTF-8');
            $capAttr = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8');

            if ($enableLightbox) {
                $html[] = '<div class="' . htmlspecialchars($colClasses, ENT_QUOTES, 'UTF-8') . '">'
                    .   '<a href="' . $imgUrl . '" class="glightbox" data-gallery="cs-auto-gallery"'
                    .       ($showCaptions ? ' data-title="' . $capAttr . '"' : '') . '>'
                    .     '<div class="thumb"><img src="' . $imgUrl . '" alt="' . $capAttr . '" loading="lazy" class="img-fluid"/></div>'
                    .   '</a>'
                    . '</div>';
            } else {
                $html[] = '<div class="' . htmlspecialchars($colClasses, ENT_QUOTES, 'UTF-8') . '">'
                    .   '<div class="gallery-item">'
                    .     '<div class="thumb"><img src="' . $imgUrl . '" alt="' . $capAttr . '" loading="lazy" class="img-fluid"/></div>'
                    .   '</div>'
                    . '</div>';
            }
        }
        $html[] = '</div></div>';
        return implode("\n", $html);
    }

    private function filenameToCaption($filename)
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('~[_\-]+~', ' ', (string) $name);
        $name = preg_replace('~\s+~', ' ', $name);
        $name = trim((string) $name);
        if ($name === '') {
            return 'Image';
        }
        return mb_convert_case($name, MB_CASE_TITLE_SIMPLE, 'UTF-8');
    }

    private function getParamDefaultPath($name, $fallback)
    {
        $val = (string) $this->params->get($name, '');
        if ($val === '' || strpos($val, '{JPATH_ROOT}') !== false) {
            $val = str_replace('{JPATH_ROOT}', JPATH_ROOT, $val ?: $fallback);
        }
        return $val;
    }

    private function applyJpathToken($s)
    {
        return str_replace('{JPATH_ROOT}', JPATH_ROOT, $s);
    }
}
