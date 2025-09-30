<?php

/**
 * @package PublishPress
 * @author  PublishPress
 *
 * copyright (C) 2019 PublishPress
 *
 * ------------------------------------------------------------------------------
 * Based on Edit Flow
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c) 2009-2016 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

use PublishPress\Checklists\Core\Factory;
use PublishPress\Checklists\Core\Legacy\Module;
use PublishPress\Checklists\Core\Legacy\Util;
use PublishPress\Checklists\Core\Requirement\Base_requirement;

if (!class_exists('PPCH_Settings')) {
    #[\AllowDynamicProperties]
    class PPCH_Settings extends Module
    {
        const SETTINGS_SLUG = 'ppch-settings';

        /**
         * @var string
         */
        const MENU_SLUG = 'ppch-settings';

        public $module;

        /**
         * Register the module with PublishPress but don't do anything else
         */
        public function __construct()
        {
            $legacyPlugin = Factory::getLegacyPlugin();

            // Register the module with PublishPress
            $this->module_url = $this->getModuleUrl(__FILE__);
            $args             = [
                'title'                => apply_filters(
                    'publishpress_checklists_settings_title',
                    esc_html__('Checklists Settings', 'publishpress-checklists')
                ),
                'extended_description' => false,
                'module_url'           => $this->module_url,
                'icon_class'           => 'dashicons dashicons-admin-settings',
                'slug'                 => 'settings',
                'settings_slug'        => self::SETTINGS_SLUG,
                'default_options'      => [
                    'enabled'                  => 'on',
                    'post_types'               => [
                        'post' => 'on',
                    ],
                    'disable_publish_button'   => Base_requirement::VALUE_NO,
                    'show_warning_icon_submit' => Base_requirement::VALUE_YES,
                    'openai_api_key'           => '',
                    'delete_data_on_uninstall' => 'off',
                    'who_can_ignore_option'      => Base_requirement::VALUE_YES
                ],
                'autoload'             => true,
                'add_menu'             => true,
            ];

            $this->module = $legacyPlugin->register_module('settings', $args);
        }

        /**
         * Initialize the rest of the stuff in the class if the module is active
         */
        public function init()
        {
            add_action('admin_init', [$this, 'helper_settings_validate_and_save'], 100);
            add_action('admin_init', [$this, 'register_settings']);

            add_action('publishpress_checklists_admin_submenu', [$this, 'action_admin_submenu'], 990);

            add_action('admin_head-edit.php', [$this, 'remove_quick_edit_status_row']);
            add_action('admin_head-edit.php', [$this, 'remove_quick_edit_row']);
            add_action('admin_print_styles', [$this, 'action_admin_print_styles']);
            add_action('admin_print_scripts', [$this, 'action_admin_print_scripts']);
            add_action('admin_enqueue_scripts', [$this, 'action_admin_enqueue_scripts']);
            add_filter('publishpress_checklists_validate_module_settings', [$this, 'validate_module_settings'], 10, 2);
            add_filter('publishpress_checklists_settings_tabs', [$this, 'settings_tab']);
        }

        /**
         * Add necessary things to the admin menu
         */
        public function action_admin_submenu()
        {
            $legacyPlugin = Factory::getLegacyPlugin();

            // Main Menu
            add_submenu_page(
                $legacyPlugin->getMenuSlug(),
                apply_filters(
                    'publishpress_checklists_settings_title',
                    esc_html__('Checklists Settings', 'publishpress-checklists')
                ),
                esc_html__('Settings', 'publishpress-checklists'),
                apply_filters('publishpress_checklists_view_settings_cap', 'manage_options'),
                self::MENU_SLUG,
                [$this, 'options_page_controller']
            );
        }

        public function action_admin_enqueue_scripts()
        {
            if ($this->isWhitelistedSettingsView()) {
                if (isset($_GET['page']) && $_GET['page'] === 'ppch-settings') {
                    wp_enqueue_script(
                        'ppch-settings',
                        $this->module_url . 'lib/settings.js',
                        ['jquery'],
                        PPCH_VERSION
                    );
                }
            }
        }

        /**
         * Add settings styles to the settings page
         */
        public function action_admin_print_styles()
        {
            if ($this->isWhitelistedSettingsView()) {
                wp_enqueue_style(
                    'publishpress-settings-css',
                    $this->module_url . 'lib/settings.css',
                    false,
                    PPCH_VERSION
                );
            }

            if (isset($_GET['page']) && $_GET['page'] === 'ppch-settings') {
                wp_enqueue_script('jquery-ui-core');
                wp_enqueue_script('jquery-ui-tabs');
            }
        }

        /**
         * Remove the status field row in quick edit for enabled post types.
         */
        public function remove_quick_edit_status_row()
        {

            $status = isset($this->module->options->disable_quick_edit_publish) ? $this->module->options->disable_quick_edit_publish : 'yes';
            if ($status == 'yes') :
                $post_type = (!empty($_GET['post_type'])) ? sanitize_text_field($_GET['post_type']) : 'post';
                $post_types = array_keys($this->get_post_types());
                if (in_array($post_type, $post_types)) :
?>
                    <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            $('label.inline-edit-status').each(function() {
                                $(this).remove();
                            });
                        });
                    </script>
                <?php
                endif;
            endif;
        }

        /**
         * Remove quick edit option.
         */
        public function remove_quick_edit_row()
        {
            // If the current user can manage options, don't remove Quick Edit
            if (current_user_can('manage_options')) {
                return;
            }

            $status = isset($this->module->options->disable_quick_edit_completely) ? $this->module->options->disable_quick_edit_completely : 'yes';
            if ($status == 'yes') :
                $post_type = (!empty($_GET['post_type'])) ? sanitize_text_field($_GET['post_type']) : 'post';
                $post_types = array_keys($this->get_post_types());
                if (in_array($post_type, $post_types)) :
                ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            $('span.inline').remove();
                        });
                    </script>
            <?php
                endif;
            endif;
        }

        /**
         * Extra data we need on the page for transitions, etc.
         *
         * @since 0.7
         */
        public function action_admin_print_scripts()
        {
            ?>
            <script type="text/javascript">
                var ma_admin_url = '<?php echo esc_url(get_admin_url()); ?>';
            </script>
        <?php
        }

        /**
         * Adds Settings page.
         */
        public function print_default_settings()
        {
            $legacyPlugin = Factory::getLegacyPlugin();

        ?>

            <div class="pp-columns-wrapper<?php echo (!Util::isChecklistsProActive()) ? ' pp-enable-sidebar' : '' ?>">
                <div class="pp-column-left">
                    <form class="basic-settings"
                        action="<?php echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">

                        <?php
                        /**
                         * @param array $tabs
                         *
                         * @return array
                         */
                        $tabs = apply_filters('publishpress_checklists_settings_tabs', []);
                        if (!empty($tabs)) {
                            echo '<ul id="publishpress-checklists-settings-tabs" class="nav-tab-wrapper">';
                            $i = 0;
                            foreach ($tabs as $tabLink => $tabLabel) {
                                echo '<li class="nav-tab ' . ($i === 0 ? 'nav-tab-active' : '') . '">';
                                echo '<a href="' . esc_url($tabLink) . '">' . esc_html($tabLabel) . '</a>';
                                echo '</li>';
                                $i++;
                            }
                            echo '</ul>';
                        }
                        ?>

                        <?php settings_fields($this->module->options_group_name); ?>
                        <?php do_settings_sections($this->module->options_group_name); ?>

                        <?php
                        foreach ($legacyPlugin->class_names as $slug => $class_name) {
                            $mod_data = $legacyPlugin->$slug->module;

                            if (
                                $mod_data->autoload
                                || $mod_data->slug === $this->module->slug
                                || !isset($mod_data->general_options)
                                || $mod_data->options->enabled != 'on'
                            ) {
                                continue;
                            }

                            echo sprintf('<h3>%s</h3>', esc_html($mod_data->title));
                            echo sprintf('<p>%s</p>', esc_html($mod_data->short_description));

                            echo '<input name="checklists_module_name[]" type="hidden" value="' . esc_attr(
                                $mod_data->name
                            ) . '" />';

                            $legacyPlugin->$slug->print_configure_view();
                        }

                        // Check if we have any feature user can toggle.
                        $featuresCount = 0;

                        foreach ($legacyPlugin->modules as $mod_name => $mod_data) {
                            if (!$mod_data->autoload && $mod_data->slug !== $this->module->slug) {
                                $featuresCount++;
                            }
                        }
                        ?>

                        <?php if ($featuresCount > 0) : ?>
                            <div id="modules-wrapper">
                                <h3><?php echo esc_html__('Features', 'publishpress-checklists'); ?></h3>
                                <p><?php echo esc_html__(
                                        'Feel free to select only the features you need.',
                                        'publishpress-checklists'
                                    ); ?></p>

                                <table class="form-table">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><?php echo esc_html__(
                                                                'Enabled features',
                                                                'publishpress-checklists'
                                                            ); ?></th>
                                            <td>
                                                <?php foreach ($legacyPlugin->modules as $mod_name => $mod_data) : ?>

                                                    <?php if ($mod_data->autoload || $mod_data->slug === $this->module->slug) {
                                                        continue;
                                                    } ?>

                                                    <label for="feature-<?php echo esc_attr($mod_data->slug); ?>">
                                                        <input id="feature-<?php echo esc_attr($mod_data->slug); ?>"
                                                            name="publishpress_checklists_settings_options[features][<?php echo esc_attr(
                                                                                                                            $mod_data->slug
                                                                                                                        ); ?>]" <?php echo ($mod_data->options->enabled == 'on') ? "checked=\"checked\"" : ""; ?>
                                                            type="checkbox">
                                                        &nbsp;&nbsp;&nbsp;<?php echo esc_html($mod_data->title); ?>
                                                    </label>
                                                    <br>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <?php echo '<input name="checklists_module_name[]" type="hidden" value="' . esc_attr(
                                    $this->module->name
                                ) . '" />'; ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        wp_nonce_field('edit-publishpress-settings');

                        submit_button(null, 'primary', 'submit', false); ?>
                    </form>
                </div><!-- .pp-column-left -->
                <?php if (!Util::isChecklistsProActive()) :  ?>
                    <div class="pp-column-right">
                        <?php Util::ppch_pro_sidebar(); ?>
                    </div><!-- .pp-column-right -->
                <?php endif; ?>
            </div><!-- .pp-columns-wrapper -->
            <div class="publishpress-modules">
                <?php $this->print_modules(); ?>
            </div>
            <?php
        }

        public function print_modules()
        {
            $legacyPlugin = Factory::getLegacyPlugin();

            if (empty($legacyPlugin->modules)) {
                echo '<div class="message error">' . esc_html__(
                    'There are no PublishPress modules registered',
                    'publishpress-checklists'
                ) . '</div>';
            } else {
                foreach ($legacyPlugin->modules as $mod_name => $mod_data) {
                    $add_menu = isset($mod_data->add_menu) && $mod_data->add_menu === true;

                    if ($mod_data->autoload || !$add_menu) {
                        continue;
                    }

                    if ($mod_data->options->enabled !== 'off') {
                        $url = '';

                        if ($mod_data->configure_page_cb && (!isset($mod_data->show_configure_btn) || $mod_data->show_configure_btn === true)) {
                            $url = add_query_arg('page', $mod_data->settings_slug, get_admin_url(null, 'admin.php'));
                        } elseif ($mod_data->page_link) {
                            $url = $mod_data->page_link;
                        }

                        $templateLoader = Factory::getTemplateLoader();

                        $templateLoader->load(
                            'settings',
                            'module',
                            [
                                'has_config_link' => isset($mod_data->configure_page_cb) && !empty($mod_data->configure_page_cb),
                                'slug'            => $mod_data->slug,
                                'icon_class'      => isset($mod_data->icon_class) ? $mod_data->icon_class : false,
                                'form_action'     => get_admin_url(null, 'options.php'),
                                'title'           => $mod_data->title,
                                'description'     => wp_kses($mod_data->short_description, 'a'),
                                'url'             => $url,
                            ],
                            false,
                            false
                        );
                    }
                }
            }
        }

        /**
         * Given a form field and a description, prints either the error associated with the field or the description.
         *
         * @param string $field The form field for which to check for an error
         * @param string $description Unlocalized string to display if there was no error with the given field
         *
         * @since 0.7
         *
         */
        public function helper_print_error_or_description($field, $description)
        {
            if (isset($_REQUEST['form-errors'][$field])): ?>
                <div class="form-error">
                    <p><?php echo esc_html(sanitize_text_field($_REQUEST['form-errors'][$field])); ?></p>
                </div>
            <?php else: ?>
                <p class="description"><?php echo esc_html($description); ?></p>
<?php endif;
        }

        /**
         * Generate an option field to turn post type support on/off for a given module
         *
         * @param object $module PublishPress module we're generating the option field for
         * @param array $post_types If empty, we consider all post types
         *
         * @since 0.7
         */
        public function helper_option_custom_post_type($module, $post_types = [])
        {
            if (empty($post_types)) {
                $post_types        = [];
                $custom_post_types = $this->getSupportedPostTypesForModule();
                if (count($custom_post_types)) {
                    foreach ($custom_post_types as $custom_post_type => $args) {
                        $post_types[$custom_post_type] = $args->label;
                    }
                }
            }

            foreach ($post_types as $post_type => $title) {
                echo '<label for="' . esc_attr($post_type) . '-' . esc_attr($module->slug) . '">';
                echo '<input id="' . esc_attr($post_type) . '-' . esc_attr($module->slug) . '" name="'
                    . esc_attr($module->options_group_name) . '[post_types][' . esc_attr($post_type) . ']"';
                if (isset($module->options->post_types[$post_type])) {
                    checked($module->options->post_types[$post_type], 'on');
                }
                // Defining post_type_supports in the functions.php file or similar should disable the checkbox
                disabled(post_type_supports($post_type, $module->post_type_support), true);
                echo ' type="checkbox" value="on" />&nbsp;&nbsp;&nbsp;' . esc_html($title) . '</label>';
                // Leave a note to the admin as a reminder that add_post_type_support has been used somewhere in their code
                if (post_type_supports($post_type, $module->post_type_support)) {
                    echo '&nbsp&nbsp;&nbsp;<span class="description">' . sprintf(
                        esc_html__(
                            'Disabled because add_post_type_support(\'%1$s\', \'%2$s\') is included in a loaded file.',
                            'publishpress-checklists'
                        ),
                        esc_html($post_type),
                        esc_html($module->post_type_support)
                    ) . '</span>';
                }
                echo '<br />';
            }
        }

        /**
         * Validation and sanitization on the settings field
         * This method is called automatically/ doesn't need to be registered anywhere
         *
         * @since 0.7
         */
        public function helper_settings_validate_and_save()
        {
            if (!isset($_POST['action'], $_POST['_wpnonce'], $_POST['option_page'], $_POST['_wp_http_referer'], $_POST['submit']) || !is_admin()) {
                return false;
            }

            if (
                $_POST['action'] != 'update'
                || !isset($_GET['page'])
                || (isset($_GET['page']) && $_GET['page'] != 'ppch-settings')
            ) {
                return false;
            }

            if (!current_user_can('manage_options') || !wp_verify_nonce(
                sanitize_key($_POST['_wpnonce']),
                'edit-publishpress-settings'
            )) {
                wp_die(esc_html__('Cheatin&#8217; uh?', 'publishpress-checklists'));
            }

            if (!isset($_POST['publishpress_checklists_settings_options'])) {
                return true;
            }

            $legacyPlugin = Factory::getLegacyPlugin();

            if (isset($_POST['publishpress_checklists_settings_options']['features'])) {
                $enabledFeatures = sanitize_text_field($_POST['publishpress_checklists_settings_options']['features']);

                // Run through all the modules updating their statuses
                foreach ($legacyPlugin->modules as $mod_data) {
                    if (
                        $mod_data->autoload
                        || $mod_data->slug === $this->module->slug
                    ) {
                        continue;
                    }

                    $status = array_key_exists($mod_data->slug, $enabledFeatures) ? 'on' : 'off';
                    $legacyPlugin->update_module_option($mod_data->name, 'enabled', $status);
                }
            }

            $modules = ['settings'];

            foreach ($modules as $moduleSlug) {
                $module_name = sanitize_key(Util::sanitizeModuleName($moduleSlug));

                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $new_options = (isset($_POST[$legacyPlugin->$module_name->module->options_group_name])) ? $this->sanitize_module_options($_POST[$legacyPlugin->$module_name->module->options_group_name]) : [];

                /**
                 * Legacy way to validate the settings. Hook to the filter
                 * publishpress_checklists_validate_module_settings instead.
                 *
                 * @deprecated
                 */
                if (method_exists($legacyPlugin->$module_name, 'settings_validate')) {
                    $new_options = $legacyPlugin->$module_name->settings_validate($new_options);
                }

                // New way to validate settings
                $new_options = apply_filters(
                    'publishpress_checklists_validate_module_settings',
                    $new_options,
                    $module_name
                );

                // Cast our object and save the data.
                $new_options = (object)array_merge((array)$legacyPlugin->$module_name->module->options, $new_options);
                $legacyPlugin->update_all_module_options($legacyPlugin->$module_name->module->name, $new_options);

                // Check if the module has a custom save method
                if (method_exists($legacyPlugin->$module_name, 'settings_save')) {
                    $legacyPlugin->$module_name->settings_save($new_options);
                }
            }


            // Redirect back to the settings page that was submitted without any previous messages
            $goback = add_query_arg('message', 'settings-updated', remove_query_arg(['message'], wp_get_referer()));
            wp_safe_redirect($goback);

            exit;
        }


        /**
         * Sanitize module options.
         *
         * @param mixed $module_options
         * 
         * @return mixed $sanitized_options
         */
        protected function sanitize_module_options($module_options)
        {
            $sanitized_options = [];

            foreach ($module_options as $key => $value) {
                $key = sanitize_text_field($key);

                if (is_array($value)) {
                    $sanitized_options[$key] = $this->sanitize_module_options($value);
                } else {
                    $sanitized_options[$key] = sanitize_text_field($value);
                }
            }

            return $sanitized_options;
        }

        /**
         * Check if array is an associative array.
         *
         * @param array $array
         * 
         * @return bool
         */
        protected function is_associative_array($array)
        {
            if (!is_array($array)) {
                return false;
            }

            if (array() === $array) {
                return false;
            }
            return array_keys($array) !== range(0, count($array) - 1);
        }


        public function validate_module_settings($new_options)
        {
            if (!isset($new_options['enabled'])) {
                $new_options['enabled'] = 'on';
            }

            if (!isset($new_options['show_warning_icon_submit'])) {
                $new_options['show_warning_icon_submit'] = Base_requirement::VALUE_NO;
            }

            if (!isset($new_options['who_can_ignore_option'])) {
                $new_options['who_can_ignore_option'] = Base_requirement::VALUE_NO;
            }

            if (!isset($new_options['disable_quick_edit_publish'])) {
                $new_options['disable_quick_edit_publish'] = Base_requirement::VALUE_NO;
            }

            if (!isset($new_options['disable_quick_edit_completely'])) {
                $new_options['disable_quick_edit_completely'] = Base_requirement::VALUE_NO;
            }

            if (!isset($new_options['disable_publish_button'])) {
                $new_options['disable_publish_button'] = Base_requirement::VALUE_NO;
            }

            if (!isset($new_options['delete_data_on_uninstall'])) {
                $new_options['delete_data_on_uninstall'] = 'off';
            }
            $new_options['delete_data_on_uninstall'] = $new_options['delete_data_on_uninstall'] === 'on' ? 'on' : 'off';

            return $new_options;
        }

        public function options_page_controller()
        {
            $legacyPlugin = Factory::getLegacyPlugin();

            $module_settings_slug = isset($_GET['module']) && !empty($_GET['module']) ? sanitize_text_field($_GET['module']) : PPCH_Settings::SETTINGS_SLUG;
            $requested_module     = $legacyPlugin->getModuleBy('settings_slug', $module_settings_slug);
            $display_text         = '';

            // If there's been a message, let's display it
            if (isset($_GET['message'])) {
                $message = sanitize_text_field($_GET['message']);
            } elseif (isset($_REQUEST['message'])) {
                $message = sanitize_text_field($_REQUEST['message']);
            } elseif (isset($_POST['message'])) {
                $message = sanitize_text_field($_POST['message']);
            } else {
                $message = false;
            }
            if ($message && isset($requested_module->messages[$message])) {
                $display_text .= '<div class="is-dismissible notice notice-info"><p>' . esc_html(
                    $requested_module->messages[$message]
                ) . '</p></div>';
            }

            // If there's been an error, let's display it
            if (isset($_GET['error'])) {
                $error = sanitize_text_field($_GET['error']);
            } elseif (isset($_REQUEST['error'])) {
                $error = sanitize_text_field($_REQUEST['error']);
            } elseif (isset($_POST['error'])) {
                $error = sanitize_text_field($_POST['error']);
            } else {
                $error = false;
            }
            if ($error && isset($requested_module->messages[$error])) {
                $display_text .= '<div class="is-dismissible notice notice-error"><p>' . esc_html(
                    $requested_module->messages[$error]
                ) . '</p></div>';
            }

            $this->printDefaultHeader($requested_module);

            // Get module output
            ob_start();
            $configure_callback = $requested_module->configure_page_cb;

            $module_output = '';

            if (!empty($configure_callback)) {
                $requested_module_name = $requested_module->name;

                $legacyPlugin->$requested_module_name->$configure_callback();
                $module_output = ob_get_clean();
            }

            /*
             * Check if we have more than one tab to display.
             */
            $show_tabs = false;
            foreach ($legacyPlugin->modules as $module) {
                if (!empty($module->options_page) && $module->options->enabled == 'on') {
                    $show_tabs = true;
                }
            }

            $this->print_default_settings();

            $templateLoader = Factory::getTemplateLoader();

            $templateLoader->load(
                'settings',
                'settings',
                [
                    'modules'        => (array)$legacyPlugin->modules,
                    'settings_slug'  => $module_settings_slug,
                    'slug'           => PPCH_Settings::SETTINGS_SLUG,
                    'module_output'  => $module_output,
                    'sidebar_output' => '',
                    'text'           => $display_text,
                    'show_sidebar'   => false,
                    'show_tabs'      => $show_tabs,
                ]
            );

            if (apply_filters('publishpress_checklist_display_branding', true)) {
                $this->printDefaultFooter($this->module);
            }
        }

        /**
         * Register settings for notifications so we can partially use the Settings API
         * (We use the Settings API for form generation, but not saving)
         */
        public function register_settings()
        {
            /**
             *
             * General
             */


            add_settings_section(
                $this->module->options_group_name . '_general',
                __return_false(),
                [$this, 'settings_section_general'],
                $this->module->options_group_name
            );

            do_action('publishpress_checklists_register_settings_before');

            add_settings_field(
                'show_who_can_ignore',
                __('Enable Who Can Ignore:', 'publishpress-checklists'),
                [$this, 'settings_who_can_ignore_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'show_warning_icon_submit',
                __('Show warning icon:', 'publishpress-checklists'),
                [$this, 'settings_show_warning_icon_submit_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'delete_data_on_uninstall',
                __('Delete data on uninstall:', 'publishpress-checklists'),
                [$this, 'settings_delete_data_on_uninstall_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_tools'
            );

            if (!Util::isChecklistsProActive()) {
                add_settings_field(
                    'status_filter_settings',
                    __('Enable Status Filter:', 'publishpress-checklists'),
                    [$this, 'settings_status_filter_option'],
                    $this->module->options_group_name,
                    $this->module->options_group_name . '_general'
                );
            }

            if (!Util::isChecklistsProActive()) {
                add_settings_field(
                    'show_checklists_settings',
                    'duplicate_checklist_settings',
                    __('Enable Duplicate Checklists:', 'publishpress-checklists'),
                    [$this, 'settings_duplicate_checklist_option'],
                    $this->module->options_group_name,
                    $this->module->options_group_name . '_general'
                );
            }

            if (!Util::isChecklistsProActive()) {
                add_settings_field(
                    'show_checklists_column',
                    __('Show Checklists column in post lists:', 'publishpress-checklists'),
                    [$this, 'settings_show_checklists_option'],
                    $this->module->options_group_name,
                    $this->module->options_group_name . '_general'
                );
            }

            /**
             * Publishing Options
             */
            add_settings_section(
                $this->module->options_group_name . '_publishing_options',
                __return_false(),
                [$this, 'settings_section_publishing_options'],
                $this->module->options_group_name
            );

            add_settings_field(
                'disable_quick_edit_publish',
                __('Disable the "Status" option when using "Quick Edit":', 'publishpress-checklists'),
                [$this, 'settings_disable_quick_edit_publish_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_publishing_options'
            );

            add_settings_field(
                'disable_quick_edit_completely',
                __('Disable "Quick Edit" completely:', 'publishpress-checklists'),
                [$this, 'settings_disable_quick_edit_completely_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_publishing_options'
            );

            add_settings_field(
                'disable_publish_button',
                __('Disable "Publish" button:', 'publishpress-checklists'),
                [$this, 'settings_disable_publish_button_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_publishing_options'
            );

            /**
             * Integration
             */
            add_settings_section(
                $this->module->options_group_name . '_integration',
                __return_false(),
                [$this, 'settings_section_integration'],
                $this->module->options_group_name
            );

            add_settings_field(
                'openai_api_key',
                __('OpenAI API Key:', 'publishpress-checklists'),
                [$this, 'settings_openai_api_key_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_integration'
            );

            add_settings_section(
                $this->module->options_group_name . '_tools',
                __return_false(),
                [$this, 'settings_section_tools'],
                $this->module->options_group_name
            );

            /**
             * Post Types
             */

            add_settings_section(
                $this->module->options_group_name . '_post_types',
                __return_false(),
                [$this, 'settings_section_post_types'],
                $this->module->options_group_name
            );

            add_settings_field(
                'post_types',
                __('Add to these post types:', 'publishpress-checklists'),
                [$this, 'settings_post_types_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_post_types'
            );

            do_action('publishpress_checklists_register_settings_after');
        }

        /**
         * Displays the field to allow select the post types for checklist.
         */
        public function settings_post_types_option()
        {
            $legacyPlugin = Factory::getLegacyPlugin();

            $legacyPlugin->settings->helper_option_custom_post_type($this->module);
        }

        /**
         * Displays the field to choose between display or not the warning icon
         * close to the submit button
         *
         * @param array
         */
        public function settings_status_filter_option($args = [])
        {
            $id    = $this->module->options_group_name . '_status_filter_settings';
            $value = 'no';

            echo '<label for="' . esc_attr($id) . '" class="disabled-pro-option">';
            echo '<input type="checkbox" value="yes" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[status_filter_settings]" '
                . checked($value, 'yes', false) . ' disabled="disabled" />';
            echo '&nbsp;&nbsp;&nbsp;' . esc_html__(
                'This allows tasks to be disabled for specific statuses such as "Draft" or "Published.',
                'publishpress-checklists'
            );
            echo '</label>';
            echo ' <a href="https://publishpress.com/links/checklists-menu" target="_blank" class="pro-badge">PRO</a>';
        }

        /**
         * Displays the promo field for duplicate checklists in the free version
         *
         * @param array $args
         */
        public function settings_duplicate_checklist_option($args = [])
        {
            $id    = $this->module->options_group_name . '_duplicate_checklist_settings';
            $value = 'no';

            echo '<label for="' . esc_attr($id) . '" class="disabled-pro-option">';
            echo '<input type="checkbox" value="yes" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[duplicate_checklist_settings]" '
                . checked($value, 'yes', false) . ' disabled="disabled" />';
            echo '&nbsp;&nbsp;&nbsp;' . esc_html__(
                'This allows users to duplicate existing checklist task.',
                'publishpress-checklists'
            );
            echo '</label>';
            echo ' <a href="https://publishpress.com/links/checklists-menu" target="_blank" class="pro-badge">PRO</a>';
        }

        /**
         * Displays the checkbox to enable or disable the Checklists column in post lists
         * close to the submit button
         *
         * @param array
         */
        public function settings_show_checklists_option($args = [])
        {
            $id    = $this->module->options_group_name . '_show_checklists_settings';
            $value = 'no';

            echo '<label for="' . esc_attr($id) . '" class="disabled-pro-option">';
            echo '<input type="checkbox" value="yes" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[show_checklists_settings]" '
                . checked($value, 'yes', false) . ' disabled="disabled" />';
            echo '&nbsp;&nbsp;&nbsp;' . esc_html__(
                'Add a Checklists column to the Posts screen showing how many requirements are complete.',
                'publishpress-checklists'
            );
            echo '</label>';
            echo ' <a href="https://publishpress.com/links/checklists-menu" target="_blank" class="pro-badge">PRO</a>';
        }

        /**
         * Displays the field to choose between display or not the warning icon
         * close to the submit button
         *
         * @param array
         */
        public function settings_show_warning_icon_submit_option($args = [])
        {
            $id    = $this->module->options_group_name . '_show_warning_icon_submit';
            $value = isset($this->module->options->show_warning_icon_submit) ? $this->module->options->show_warning_icon_submit : 'no';

            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" value="yes" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[show_warning_icon_submit]" '
                . checked($value, 'yes', false) . ' />';
            echo '&nbsp;&nbsp;&nbsp;' . esc_html__(
                'This will display a warning icon in the "Checklists" box if requirements are incomplete.',
                'publishpress-checklists'
            );
            echo '</label>';
        }

        /**
         * Displays the checkbox to enable deleting plugin data on uninstall
         *
         * @param array $args
         */
        public function settings_delete_data_on_uninstall_option($args = [])
        {
            $id    = $this->module->options_group_name . '_delete_data_on_uninstall';
            $value = isset($this->module->options->delete_data_on_uninstall) ? $this->module->options->delete_data_on_uninstall : 'off';

            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" value="on" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[delete_data_on_uninstall]" '
                . checked($value, 'on', false) . ' />';
            echo '&nbsp;&nbsp;&nbsp;' . esc_html__(
                'When enabled, all PublishPress Checklists data will be deleted if the plugin is uninstalled.',
                'publishpress-checklists'
            );
            echo '</label>';
        }

        /**
         * Display the field to enable or disable who can ignore
         * 
         * @param array
         * 
         */
        public function settings_who_can_ignore_option($args = [])
        {
            $id    = $this->module->options_group_name . '_who_can_ignore_option';
            $value = isset($this->module->options->who_can_ignore_option) ? $this->module->options->who_can_ignore_option : 'no';

            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" value="yes" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[who_can_ignore_option]" '
                . checked($value, 'yes', false) . ' />';
            echo '&nbsp;&nbsp;&nbsp;' . esc_html__(
                'This will show "Who can ignore" options.',
                'publishpress-checklists'
            );
            echo '</label>';
        }

        /**
         * Displays the checkbox to enable or disable status row
         * in quick edit
         *
         * @param array
         */
        public function settings_disable_quick_edit_publish_option($args = [])
        {
            $id    = $this->module->options_group_name . '_disable_quick_edit_publish';
            $value = isset($this->module->options->disable_quick_edit_publish) ? $this->module->options->disable_quick_edit_publish : 'no';

            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" value="yes" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[disable_quick_edit_publish]" '
                . checked($value, 'yes', false) . ' />';
            echo '&nbsp;&nbsp;&nbsp;' . esc_html__(
                'Disabling the "Status" option is recommended because it can be used to avoid using the Checklists requirements.',
                'publishpress-checklists'
            );
            echo '</label>';
        }

        /**
         * Displays the checkbox to enable or disable quick edit
         * 
         *
         * @param array
         */
        public function settings_disable_quick_edit_completely_option($args = [])
        {
            $id    = $this->module->options_group_name . '_disable_quick_edit_completely';
            $value = isset($this->module->options->disable_quick_edit_completely) ? $this->module->options->disable_quick_edit_completely : 'no';

            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" value="yes" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[disable_quick_edit_completely]" '
                . checked($value, 'yes', false) . ' />';
            echo '&nbsp;&nbsp;&nbsp;' . esc_html__(
                'This will disable "Quick Edit" for all users except those with the "manage_options" capability.',
                'publishpress-checklists'
            );
            echo '</label>';
        }

        /**
         * Displays the checkbox to enable or disable quick edit
         * 
         *
         * @param array
         */
        public function settings_disable_publish_button_option($args = [])
        {
            $id    = $this->module->options_group_name . '_disable_publish_button';
            $value = isset($this->module->options->disable_publish_button) ? $this->module->options->disable_publish_button : 'no';

            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" value="yes" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[disable_publish_button]" '
                . checked($value, 'yes', false) . ' />';
            echo '&nbsp;&nbsp;&nbsp;' . esc_html__(
                'This will disable the "Publish" button when checklist requirements are not met.',
                'publishpress-checklists'
            );
            echo '</label>';
        }

        /**
         * Displays the openai api key settings
         *
         * @param array
         */
        public function settings_openai_api_key_option($args = [])
        {
            $id    = $this->module->options_group_name . '_openai_api_key';
            $value = isset($this->module->options->openai_api_key) ? $this->module->options->openai_api_key : '';

            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="text" value="' . esc_attr($value) . '" id="' . esc_attr($id) . '" name="' . esc_attr($this->module->options_group_name) . '[openai_api_key]" />';
            echo '<br />' . esc_html__(
                'Enter your API Key to use OpenAI prompts in checklist tasks.',
                'publishpress-checklists'
            );
            echo '</label>';
        }

        /**
         * Validate data entered by the user
         *
         * @param array $new_options New values that have been entered by the user
         * @param string $module_name The name of the module
         *
         * @return array $new_options Form values after they've been sanitized
         */
        public function filter_settings_validate($new_options, $module_name)
        {
            if ($module_name !== 'checklist') {
                return $new_options;
            }

            // Whitelist validation for the post type options
            if (!isset($new_options['post_types'])) {
                $new_options['post_types'] = [];
            }

            $new_options['post_types'] = $this->clearPostTypesOptions(
                $new_options['post_types'],
                $this->module->post_type_support
            );

            if (!isset($new_options['show_warning_icon_submit'])) {
                $new_options['show_warning_icon_submit'] = Base_requirement::VALUE_NO;
            }
            $new_options['show_warning_icon_submit'] = Base_requirement::VALUE_YES === $new_options['show_warning_icon_submit'] ? Base_requirement::VALUE_YES : Base_requirement::VALUE_NO;

            if (!isset($new_options['who_can_ignore_option'])) {
                $new_options['who_can_ignore_option'] = Base_requirement::VALUE_YES;
            }
            $new_options['who_can_ignore_option'] = Base_requirement::VALUE_YES === $new_options['who_can_ignore_option'] ? Base_requirement::VALUE_YES : Base_requirement::VALUE_NO;

            return $new_options;
        }

        /**
         * @param array $tabs
         *
         * @return array
         */
        public function settings_tab($tabs)
        {
            $tabs = array_merge(
                $tabs,
                [
                    '#ppch-tab-post-types'  => esc_html__('Post Types', 'publishpress-checklists'),
                    '#ppch-tab-general'     => esc_html__('General', 'publishpress-checklists'),
                    '#ppch-tab-publishing-options' => esc_html__('Publishing Options', 'publishpress-checklists'),
                    '#ppch-tab-integration'       => esc_html__('Integration', 'publishpress-checklists'),
                    '#ppch-tab-tools'       => esc_html__('Tools', 'publishpress-checklists'),
                ]
            );

            return $tabs;
        }

        public function settings_section_general()
        {
            echo '<input type="hidden" id="ppch-tab-general" />';
        }

        public function settings_section_post_types()
        {
            echo '<input type="hidden" id="ppch-tab-post-types" />';
        }

        public function settings_section_publishing_options()
        {
            echo '<input type="hidden" id="ppch-tab-publishing-options" />';
        }

        public function settings_section_integration()
        {
            echo '<input type="hidden" id="ppch-tab-integration" />';
        }

        public function settings_section_tools()
        {
            echo '<input type="hidden" id="ppch-tab-tools" />';
        }
    }
}
