<?php
/**
 * File responsible for defining basic addon class
 *
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core;

use Exception;

defined('ABSPATH') or die('No direct script access allowed.');

class Plugin
{

    /**
     * The rule that disables
     */
    const RULE_DISABLED = 'off';

    /**
     * The rule that do not warning, or block
     */
    const RULE_ONLY_DISPLAY = 'only_display';

    /**
     * The rule that displays a warning
     */
    const RULE_WARNING = 'warning';

    /**
     * The rule that blocks
     */
    const RULE_BLOCK = 'block';

    /**
     * Flag for debug
     *
     * @var boolean
     */
    protected $debug = false;

    /**
     * The method which runs the plugin
     */
    public function init()
    {
        add_action('init', [$this, 'deactivateLegacyPlugin']);

        $this->loadTextDomain();

        // Initialize the API
        \PublishPress\Checklists\Core\API\Bootstrap::init();

        Factory::getLegacyPlugin();

        add_filter('plugin_row_meta', [$this, 'add_plugin_meta'], 10, 2);

        add_filter( 'plugin_action_links_' . plugin_basename(PPCH_FILE), [$this, 'add_action_links']);
    }

    /**
     * Load the text domain.
     */
    public function loadTextDomain()
    {
        $domain = 'publishpress-checklists';
        $locale = determine_locale();
        $mofile = dirname(__DIR__) . '/languages/' . $domain . '-' . $locale . '.mo';

        // Unload any previously loaded translations (e.g., from wp-content/languages/plugins/)
        unload_textdomain($domain);

        // Load from plugin's bundled languages folder
        load_textdomain($domain, $mofile);
    }

    public function deactivateLegacyPlugin()
    {
        try {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $all_plugins = get_plugins();

            // Check if Content Checklist is installed. The folder changes sometimes.
            foreach ($all_plugins as $pluginFile => $data) {
                if (isset($data['TextDomain']) && 'publishpress-content-checklist' === $data['TextDomain'] && is_plugin_active(
                        $pluginFile
                    )) {
                    deactivate_plugins($pluginFile);
                }
            }
        } catch (Exception $e) {
        }
    }

    public function add_plugin_meta($links, $file)
    {
        if ($file == plugin_basename(PPCH_FILE)) {
            $links[] = '<a href="'. esc_url(admin_url('admin.php?page=ppch-checklists')) .'">' . esc_html__('Checklists', 'publishpress-checklists') . '</a>';
            $links[] = '<a href="'. esc_url(admin_url('admin.php?page=ppch-settings')) .'">' . esc_html__('Settings', 'publishpress-checklists') . '</a>';
        }

        return $links;
    }

    public function add_action_links($links)
    {
        $upgrade_link = '<a href="https://publishpress.com/checklists/" target="_blank" style="font-weight: bold; color: #655997;">' . esc_html__('Upgrade to Pro', 'publishpress-checklists') . '</a>';

        array_unshift($links, $upgrade_link);

        return $links;
    }
}
