<?php
/* Modified for Joomla 5/6 on 2026-09-24: local viewer, gallery path validation, and output escaping. */
/**
 * Simple Image Gallery for Joomla 5 and 6, adapted from JoomlaWorks v4.2.
 * Copyright (c) 2006-2022 JoomlaWorks Ltd. GPL-2.0.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

class PlgContentJw_sig extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'prepareGallery'];
    }

    public function prepareGallery(ContentPrepareEvent $event): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site') || !in_array($app->input->getCmd('format', 'html'), ['html', 'feed'], true)) {
            return;
        }
        $row = $event->getItem();
        if (!is_object($row) || !isset($row->text) || !is_string($row->text) || !str_contains($row->text, '{gallery}')) {
            return;
        }
        $this->loadLanguage();
        $rootName = trim((string) $this->params->get('galleries_rootfolder', 'images'), '/');
        $siteRoot = realpath(JPATH_SITE);
        $root = realpath(JPATH_SITE . '/' . $rootName);
        if (!$siteRoot || !$root || !str_starts_with($root, $siteRoot . DIRECTORY_SEPARATOR)) {
            return;
        }
        $width = max(24, min(2000, (int) $this->params->get('thb_width', 360)));
        $height = max(24, min(2000, (int) $this->params->get('thb_height', 240)));
        $quality = max(1, min(100, (int) $this->params->get('jpg_quality', 90)));
        $ttl = max(0, min(525600, (int) $this->params->get('cache_expire_time', 1440))) * 60;
        require_once __DIR__ . '/jw_sig/includes/helper.php';
        $helper = new SimpleImageGalleryHelper();
        $assetRoot = Uri::root(true) . '/plugins/content/jw_sig/jw_sig';
        $stylesheet = $assetRoot . '/tmpl/Classic/css/template.css?v=5.0.4';
        $script = $assetRoot . '/includes/js/gallery.js?v=5.0.4';
        $cssAdded = false;
        $counter = 0;
        $row->text = preg_replace_callback('~\{gallery\}([^{}]*)\{/gallery\}~i', function (array $match) use ($app, $helper, $root, $rootName, $width, $height, $quality, $ttl, $stylesheet, $script, &$cssAdded, &$counter) {
            $folder = trim($match[1]);
            // Allow nested directories, but reject traversal and absolute paths.
            $parts = explode('/', $folder);
            if ($folder === '' || str_contains($folder, '\\') || str_contains($folder, ':') || str_contains($folder, "\0")
                || in_array('', $parts, true) || in_array('.', $parts, true) || in_array('..', $parts, true)) {
                return $match[0];
            }
            $directory = realpath($root . '/' . $folder);
            if (!$directory || !is_dir($directory) || !str_starts_with($directory, $root . DIRECTORY_SEPARATOR)) {
                return $match[0];
            }
            $gallery = $helper->renderGallery($directory, $rootName, $folder, $width, $height, $quality, $ttl);
            if (!$gallery) {
                return $match[0];
            }
            if (!$cssAdded && $app->input->getCmd('format', 'html') === 'html') {
                $app->getDocument()->addStyleSheet($stylesheet);
                $app->getDocument()->addScript($script, ['defer' => true]);
                $cssAdded = true;
            }
            $id = 'sigFreeId' . substr(hash('sha256', $directory . ':' . $counter++), 0, 12);
            ob_start();
            include __DIR__ . '/jw_sig/tmpl/Classic/default.php';
            return (string) ob_get_clean();
        }, $row->text);
    }
}
