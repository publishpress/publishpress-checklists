<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core;

use PublishPress\Checklists\Core\Legacy\LegacyPlugin;

\defined('ABSPATH') || exit;

if (!defined('PPCH_LOADED')) {
    require_once __DIR__ . '/../includes.php';
}

/**
 * Class Factory
 */
abstract class Factory
{
    /**
     * @var Container
     */
    protected static $container = null;

    /**
     * @return LegacyPlugin
     */
    public static function getLegacyPlugin()
    {
        $container = self::getContainer();

        return $container['legacy_plugin'];
    }

    /**
     * @return Container
     */
    public static function getContainer()
    {
        if (static::$container === null) {
            $services = new Services();

            static::$container = new Container();
            static::$container->register($services);
        }

        return static::$container;
    }

    /**
     * @return TemplateLoader
     */
    public static function getTemplateLoader()
    {
        $container = self::getContainer();

        return $container['template_loader'];
    }

    public static function getErrorHandler()
    {
        $container = self::getContainer();

        return $container['error_handler'];
    }
}
