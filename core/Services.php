<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core;

use PublishPress\Pimple\Container as PimpleContainer;
use PublishPress\Pimple\ServiceProviderInterface;
use PublishPress\Checklists\Core\Legacy\LegacyPlugin;

\defined('ABSPATH') || exit;

/**
 * Class Services
 */
class Services implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param PimpleContainer $pimple A container instance
     *
     * @since 1.3.5
     *
     */
    public function register(PimpleContainer $pimple)
    {
        $pimple['legacy_plugin'] = function ($c) {
            return new LegacyPlugin();
        };

        $pimple['module'] = function ($c) {
            $legacyPlugin = $c['legacy_plugin'];

            return $legacyPlugin->checklists;
        };

        $pimple['template_loader'] = function ($c) {
            return new TemplateLoader();
        };

        $pimple['error_handler'] = function ($c) {
            return new ErrorHandler();
        };
    }
}
