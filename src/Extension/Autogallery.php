<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.autogallery
 * @version     1.5.0
 * @since       5.0
 * @license     GNU General Public License version 2 or later
 */

namespace ColdStar\Plugin\Content\Autogallery\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Event\Content\ContentPrepareEvent;

/**
 * Auto Gallery Content Plugin
 *
 * Bootstrap 5 gallery with GLightbox. Auto-scans folder mapped from URL or shortcode.
 * Supports thumbnail sizing, duplicate guard, and debug context.
 *
 * @since  5.0
 */
final class Autogallery extends CMSPlugin implements SubscriberInterface
{
    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  5.0
     */
    protected $autoloadLanguage = true;

    /**
     * Tracks which gallery instances have been rendered to prevent duplicates.
     *
     * @var    array
     * @since  5.0
     */
    private static array $renderedInstances = [];

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   5.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    /**
     * Plugin method to process {auto-gallery} shortcodes in content.
     *
     * @param   ContentPrepareEvent  $event  The content prepare event.
     *
     * @return  void
     *
     * @since   5.0
     */
    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        // Extract arguments from the event
        $context = $event->getContext();
        $article = $event->getItem();
        $params  = $event->getParams();

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
            $baseFs       = rtrim($this->getParamDefaultPath('base_fs', JPATH_ROOT . '/images/music'), '/');
            $baseUrl      = rtrim($this->params->get('base_url', Uri::root(true) . '/images/music'), '/');
            $extensions   = $this->params->get('extensions', 'jpg,jpeg,png,gif,webp,avif');
            $colClasses   = $this->params->get('col_classes', 'col-6 col-sm-4 col-md-3 col-lg-3');
            $showCaptions = (bool) $this->params->get('show_captions', 1);
            $showEmpty    = (bool) $this->params->get('show_empty_message', 1);
            $showDebug    = (bool) $this->params->get('show_debug_path', 0);

            // Thumbnail sizing params
            $thumbMinW = (string) $this->params->get('thumb_min_w', '');
            $thumbMaxW = (string) $this->params->get('thumb_max_w', '');
            $thumbMinH = (string) $this->params->get('thumb_min_h', '');
            $thumbMaxH = (string) $this->params->get('thumb_max_h', '');

            // Shortcode overrides (per tag)
            $baseFs       = isset($attrs['base_fs']) ? $this->applyJpathToken($attrs['base_fs']) : $baseFs;
            $baseUrl      = isset($attrs['base_url']) ? rtrim($attrs['base_url'], '/') : $baseUrl;
            $extensions   = $attrs['extensions'] ?? $extensions;
            $colClasses   = $attrs['col_classes'] ?? $colClasses;
            $showCaptions = isset($attrs['show_captions']) ? (bool) (int) $attrs['show_captions'] : $showCaptions;
            $showEmpty    = isset($attrs['show_empty_message']) ? (bool) (int) $attrs['show_empty_message'] : $showEmpty;
            $showDebug    = isset($attrs['show_debug_path']) ? (bool) (int) $attrs['show_debug_path'] : $showDebug;

            // Per-shortcode overrides for thumb sizing (accepts CSS lengths like 120px, 8rem, 100%, auto)
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

            $images = $this->findImages($dirFs, $dirUrl, $exts, $baseFs);

            $this->enqueueAssets($showCaptions);

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
                $output .= '<div class="alert alert-secondary auto-gallery">'
                    . 'Looking for images in: <code>' . htmlspecialchars($dirUrl, ENT_QUOTES, 'UTF-8') . '</code>'
                    . '<br><small>Context: ' . $origin . $sourceId . '</small>'
                    . '</div>';
            }
            $output .= $this->renderGalleryHtml($images, $title, $colClasses, $showCaptions, $showEmpty, $styleAttr);
            return $output;
        }, $article->text);
    }

    /**
     * Parse shortcode attributes from a string.
     *
     * @param   string  $s  The attribute string.
     *
     * @return  array
     *
     * @since   5.0
     */
    private function parseAttrs(string $s): array
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

    /**
     * Extract slug from the current request URI.
     *
     * @return  string
     *
     * @since   5.0
     */
    private function slugFromRequest(): string
    {
        $path = Uri::getInstance()->getPath();
        $parts = array_values(array_filter(explode('/', (string) $path), 'strlen'));
        $last = end($parts) ?: '';
        return strtolower(trim(preg_replace('~[^a-z0-9\-]+~u', '-', $last), '-'));
    }

    /**
     * Convert a URL slug to a properly cased folder name.
     *
     * @param   string  $slug  The URL slug.
     *
     * @return  string
     *
     * @since   5.0
     */
    private function slugToFolderName(string $slug): string
    {
        if ($slug === '') {
            return '';
        }
        $parts = array_filter(explode('-', $slug), 'strlen');
        $pretty = array_map(function ($p) {
            $p = str_replace([''', '`', '´'], "'", $p);
            return mb_convert_case($p, MB_CASE_TITLE_SIMPLE, 'UTF-8');
        }, $parts);
        return implode('-', $pretty);
    }

    /**
     * Map a slug to filesystem and URL paths using alphabetical bucketing.
     *
     * @param   string  $slug     The URL slug.
     * @param   string  $baseFs   Base filesystem path.
     * @param   string  $baseUrl  Base URL path.
     *
     * @return  array  Array containing [dirFs, dirUrl, title]
     *
     * @since   5.0
     */
    private function mapSlugToFolder(string $slug, string $baseFs, string $baseUrl): array
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

    /**
     * Resolve a folder override to filesystem and URL paths.
     *
     * @param   string  $folder   The folder path override.
     * @param   string  $baseFs   Base filesystem path.
     * @param   string  $baseUrl  Base URL path.
     *
     * @return  array  Array containing [dirFs, dirUrl, title]
     *
     * @since   5.0
     */
    private function resolveFromFolder(string $folder, string $baseFs, string $baseUrl): array
    {
        $clean = trim($folder, '/');
        $clean = preg_replace('~\.+/~', '', $clean);
        $dirFs = $baseFs . '/' . $clean;
        $dirUrl = $baseUrl . '/' . implode('/', array_map('rawurlencode', explode('/', $clean)));
        $title = str_replace('-', ' ', basename($clean));
        $title = mb_convert_case($title, MB_CASE_TITLE_SIMPLE, 'UTF-8');
        return [$dirFs, $dirUrl, $title];
    }

    /**
     * Find images in a directory with security validation.
     *
     * @param   string  $dirFs   Filesystem directory path.
     * @param   string  $dirUrl  URL directory path.
     * @param   array   $exts    Allowed file extensions.
     * @param   string  $baseFs  Base filesystem path for validation.
     *
     * @return  array
     *
     * @since   5.0
     */
    private function findImages(string $dirFs, string $dirUrl, array $exts, string $baseFs): array
    {
        $images = [];
        $realBase = realpath($baseFs);
        $realDir  = is_dir($dirFs) ? realpath($dirFs) : false;
        if (!$realDir || !$realBase || strpos($realDir, $realBase) !== 0) {
            return $images;
        }
        foreach (glob($realDir . '/*.*') ?: [] as $pathFs) {
            $ext = strtolower(pathinfo($pathFs, PATHINFO_EXTENSION));
            if (!in_array($ext, $exts, true) || !is_file($pathFs)) {
                continue;
            }
            $name = basename($pathFs);
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

    /**
     * Register and enqueue CSS/JS assets for GLightbox.
     *
     * @param   bool  $showCaptions  Whether captions are enabled.
     *
     * @return  void
     *
     * @since   5.0
     */
    private function enqueueAssets(bool $showCaptions): void
    {
        $wa = $this->getApplication()->getDocument()->getWebAssetManager();
        if (!$wa->assetExists('style', 'glightbox')) {
            $wa->registerStyle('glightbox', 'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css', [], ['rel' => 'stylesheet']);
        }
        if (!$wa->assetExists('script', 'glightbox')) {
            $wa->registerScript('glightbox', 'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js', [], ['defer' => true]);
        }
        $wa->useStyle('glightbox')->useScript('glightbox');

        static $inited = false;
        if (!$inited) {
            $inited = true;
            $init = "document.addEventListener('DOMContentLoaded',function(){ if(window.GLightbox){ GLightbox({selector:'.glightbox'}); }});";
            $wa->addInlineScript($init);
            $css = ".auto-gallery .thumb{aspect-ratio:1/1;overflow:hidden;border-radius:.5rem;
  min-width:var(--thumb-min-w, initial);
  max-width:var(--thumb-max-w, none);
  min-height:var(--thumb-min-h, initial);
  max-height:var(--thumb-max-h, none);
}
.auto-gallery img{width:100%;height:100%;display:block;object-fit:cover;transition:transform .18s ease;}
.auto-gallery a:hover img{transform:scale(1.03);}";
            $wa->addInlineStyle($css);
        }
    }

    /**
     * Render the gallery HTML output.
     *
     * @param   array   $images        Array of image data.
     * @param   string  $title         Gallery title.
     * @param   string  $colClasses    Bootstrap column classes.
     * @param   bool    $showCaptions  Whether to show captions.
     * @param   bool    $showEmpty     Whether to show empty message.
     * @param   string  $styleAttr     Inline style attribute.
     *
     * @return  string
     *
     * @since   5.0
     */
    private function renderGalleryHtml(array $images, string $title, string $colClasses, bool $showCaptions, bool $showEmpty, string $styleAttr = ''): string
    {
        if (empty($images)) {
            if ($showEmpty) {
                return '<div class="alert alert-info auto-gallery">No images found for <strong>'
                    . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                    . '</strong>.</div>';
            }
            return '';
        }

        $html = [];
        $html[] = '<div class="auto-gallery"' . $styleAttr . '><div class="row g-3">';
        foreach ($images as $img) {
            $caption = $this->filenameToCaption($img['name']);
            $imgUrl  = htmlspecialchars($img['url'], ENT_QUOTES, 'UTF-8');
            $capAttr = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8');
            $html[] = '<div class="' . htmlspecialchars($colClasses, ENT_QUOTES, 'UTF-8') . '">'
                .   '<a href="' . $imgUrl . '" class="glightbox" data-gallery="auto-gallery"'
                .       ($showCaptions ? ' data-title="' . $capAttr . '"' : '') . '>'
                .     '<div class="thumb"><img src="' . $imgUrl . '" alt="' . $capAttr . '" loading="lazy" class="img-fluid"/></div>'
                .   '</a>'
                . '</div>';
        }
        $html[] = '</div></div>';
        return implode("\n", $html);
    }

    /**
     * Convert a filename to a prettified caption.
     *
     * @param   string  $filename  The filename.
     *
     * @return  string
     *
     * @since   5.0
     */
    private function filenameToCaption(string $filename): string
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

    /**
     * Get a parameter value with JPATH_ROOT token support.
     *
     * @param   string  $name      Parameter name.
     * @param   string  $fallback  Fallback value.
     *
     * @return  string
     *
     * @since   5.0
     */
    private function getParamDefaultPath(string $name, string $fallback): string
    {
        $val = (string) $this->params->get($name, '');
        if ($val === '' || strpos($val, '{JPATH_ROOT}') !== false) {
            $val = str_replace('{JPATH_ROOT}', JPATH_ROOT, $val ?: $fallback);
        }
        return $val;
    }

    /**
     * Replace JPATH_ROOT token in a string.
     *
     * @param   string  $s  The input string.
     *
     * @return  string
     *
     * @since   5.0
     */
    private function applyJpathToken(string $s): string
    {
        return str_replace('{JPATH_ROOT}', JPATH_ROOT, $s);
    }
}
