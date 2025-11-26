<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.csautogallery
 * @version     1.5.0
 * @since       5.0
 * @copyright   (C) 2025 Cybersalt Consulting Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use ColdStar\Plugin\Content\CsAutogallery\Extension\CsAutogallery;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config = (array) PluginHelper::getPlugin('content', 'autogallery');
                $subject = $container->get(DispatcherInterface::class);

                $plugin = new CsAutogallery(
                    $subject,
                    $config
                );

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
