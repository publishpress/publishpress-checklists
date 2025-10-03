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
use PublishPress\Checklists\Core\Plugin;
use PublishPress\Checklists\Core\Requirement\Base_requirement;
use PublishPress\Checklists\Core\Requirement\Custom_item;
use PublishPress\Checklists\Core\Requirement\Openai_item;
use PublishPress\Checklists\Core\Utils\FieldsTabs;
use PublishPress\Checklists\Core\Utils\ElementorUtils;
use PublishPress\Checklists\Core\Requirement\Pro_Requirement;

if (!class_exists('PPCH_Checklists')) {
    /**
     * class PPCH_Checklists
     */
    #[\AllowDynamicProperties]
    class PPCH_Checklists extends Module
    {

        const METADATA_TAXONOMY = 'pp_checklist_meta';

        const METADATA_POSTMETA_KEY = "_pp_checklist_meta";

        const SETTINGS_SLUG = 'pp-checklists-settings';

        const POST_META_PREFIX = 'pp_checklist_custom_item_';

        const FLAG_OPTIONS_MIGRATED_2_0_0 = 'publishpress_checklists_options_migrated_2_0_0';

        const FLAG_OPTIONS_MIGRATED_2_6_0 = 'publishpress_checklists_options_migrated_2_6_0';

        /**
         * @var string
         */
        const MENU_SLUG = 'ppch-checklists';

        public $module_name = 'checklists';

        /**
         * List of requirements, filled with instances of requirement classes.
         * The list is indexed by post types.
         *
         * @var array
         */
        protected $requirements = [];

        /**
         * List of post types which supports checklists
         *
         * @var array
         */
        protected $post_types = [];

        /**
         * List of tab for settings page
         */
        protected $field_tabs = [];

        /**
         * Instace for the module
         *
         * @var stdClass
         */
        public $module;

        /**
         * Construct the PPCH_Checklists class
         */
        public function __construct()
        {
            $legacyPlugin = Factory::getLegacyPlugin();

            $this->module_url = $this->getModuleUrl(__FILE__);

            // Register the module with PublishPress
            $args = [
                'title'             => apply_filters(
                    'publishpress_checklists_plugin_title',
                    esc_html__('Checklists', 'publishpress-checklists')
                ),
                'short_description' => '',
                'module_url'        => $this->module_url,
                'icon_class'        => 'dashicons dashicons-feedback',
                'slug'              => 'checklists',
                'default_options'   => [
                    'enabled'      => 'on',
                    'custom_items' => [],
                    'openai_items' => [],
                ],
                'autoload'          => true,
            ];

            // Apply a filter to the default options
            $args['default_options'] = apply_filters(
                'publishpress_checklists_requirements_default_options',
                $args['default_options']
            );

            $this->module = $legacyPlugin->register_module($this->module_name, $args);
            add_action('admin_init', [$this, 'retrieveFieldTabs']);
        }

        public function migrateLegacyOptions()
        {
            global $wp_roles;

            if (wp_doing_ajax()) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            // Do the migration
            if (!(bool)get_option(self::FLAG_OPTIONS_MIGRATED_2_0_0)) {
                $legacyOptions = get_option('publishpress_checklist_options');
                if (!empty($legacyOptions)) {
                    $settingsOptions                           = new stdClass();
                    $settingsOptions->enabled                  = 'on';
                    $settingsOptions->loaded_once              = 1;
                    $settingsOptions->post_types               = isset($legacyOptions->post_types) ? $legacyOptions->post_types : ['post' => 'on'];

                    $settingsOptions->show_warning_icon_submit = isset($legacyOptions->show_warning_icon_submit) ? $legacyOptions->show_warning_icon_submit : Base_requirement::VALUE_YES;

                    unset($legacyOptions->post_types, $legacyOptions->show_warning_icon_submit);

                    update_option('publishpress_checklists_settings_options', $settingsOptions);
                    update_option('publishpress_checklists_checklists_options', $legacyOptions);
                }

                update_option(self::FLAG_OPTIONS_MIGRATED_2_0_0, true);
            }

            // Do 2.6.0 migration
            if (!(bool)get_option(self::FLAG_OPTIONS_MIGRATED_2_6_0) && function_exists('get_role')) {
                //add newly introduced checklist role for roles with manage_options 
                $all_roles = $wp_roles->roles;
                if (is_array($all_roles) && !empty($all_roles)) {
                    foreach ($all_roles as $role => $details) {
                        $role = get_role($role);
                        if ($role->has_cap('manage_options') || $role->name === 'administrator') {
                            $role->add_cap('manage_checklists');
                        }
                    }
                }

                update_option(self::FLAG_OPTIONS_MIGRATED_2_6_0, true);
            }
        }

        /**
         * Extract labels and name from roles.
         *
         * @return array.
         */
        public static function get_editable_roles_labels()
        {
            $labels = [];

            $userRoles = get_editable_roles();

            foreach ($userRoles as $slug => $role) {
                $labels[$slug] = $role['name'];
            }
            return $labels;
        }

        /**
         * Loads the requirements for each post type
         */
        protected function instantiate_requirement_classes()
        {
            $post_types = $this->get_post_types();

            foreach ($post_types as $slug => $label) {
                $this->instantiate_post_type_requirements($slug);
            }
        }

        /**
         * Instantiates the requirements for the given post type
         *
         * @param string $post_type
         */
        protected function instantiate_post_type_requirements($post_type)
        {
            global $wp_taxonomies;

            $req_classes = apply_filters('publishpress_checklists_post_type_requirements', [], $post_type);

            if (!isset($this->requirements[$post_type])) {
                $this->requirements[$post_type] = [];
            }

            $requirementInstances         = [];
            $unsortedRequirementInstances = [];
            $positionMap                  = [];

            foreach ($req_classes as $class_name) {
                $params = null;

                // Some classes can be sent as serialized data, containing the class and params. If it is only string, it won't be affected.
                $class_name = maybe_unserialize($class_name);

                // Add support to additional arguments.
                if (is_array($class_name)) {
                    $params     = $class_name['params'];
                    $class_name = $class_name['class'];

                    // Check if the taxonomy is displayed in the UI.
                    if (isset($params['taxonomy'])) {
                        if (isset($wp_taxonomies[$params['taxonomy']])) {
                            $taxonomy = $wp_taxonomies[$params['taxonomy']];

                            if (!$taxonomy->show_ui) {
                                continue;
                            }

                            // Ignore multiple authors taxonomy
                            // @todo: add support for multiple authors
                            if ($taxonomy->query_var === 'ppma_author') {
                                continue;
                            }
                        }
                    }
                }

                if (class_exists($class_name)) {
                    // Instantiate the class
                    $instance = new $class_name($this->module, $post_type);

                    if (!is_null($params) && method_exists($instance, 'set_params')) {
                        $instance->set_params($params);
                    }

                    $unsortedRequirementInstances[] = $instance;

                    if (isset($instance->position) && !empty($instance->position)) {
                        $positionMap[] = $instance->position;
                    } else {
                        $positionMap[] = 10000 + count($unsortedRequirementInstances);
                    }
                } else {
                    Factory::getErrorHandler()->add('PublishPress Checklist Requirement Class not found', $class_name);
                }
            }

            // Sort the requirements
            $positionMap = array_flip($positionMap);
            ksort($positionMap, SORT_NUMERIC);

            foreach ($positionMap as $position => $arrayIndex) {
                $requirementInstances[] = $unsortedRequirementInstances[$arrayIndex];
            }

            $this->requirements[$post_type] = $requirementInstances;

            // Instantiate custom items
            if (isset($this->module->options->custom_items) && !empty($this->module->options->custom_items)) {
                foreach ($this->module->options->custom_items as $id) {
                    $id = trim((string)$id);

                    // Check if there is a title set for this post type. If not, we do not instantiate
                    $var_name = $id . '_title';
                    if (isset($this->module->options->{$var_name}[$post_type])) {
                        $custom_item                      = new Custom_item($id, $this->module, $post_type);
                        $this->requirements[$post_type][] = $custom_item;
                    }
                }
            }

            if (isset($this->module->options->openai_items) && !empty($this->module->options->openai_items)) {
                foreach ($this->module->options->openai_items as $id) {
                    $id = trim((string)$id);

                    // Check if there is a title set for this post type. If not, we do not instantiate
                    $var_name = $id . '_title';
                    if (isset($this->module->options->{$var_name}[$post_type])) {
                        $openai_item                      = new Openai_item($id, $this->module, $post_type);
                        $this->requirements[$post_type][] = $openai_item;
                    }
                }
            }
        }

        /**
         * Set the list of post types
         *
         * @param array $post_types
         *
         * @return array
         */
        public function filter_post_types($post_types)
        {
            $selected_post_types = $this->getSelectedPostTypes();

            return array_merge($post_types, $selected_post_types);
        }

        protected function getPostTypeTaxonomies($post_type)
        {
            global $wp_taxonomies;

            $postTypeTaxonomies = [];

            foreach ($wp_taxonomies as $taxonomy) {
                if (in_array($post_type, $taxonomy->object_type)) {
                    $postTypeTaxonomies[] = $taxonomy->name;
                }
            }

            return $postTypeTaxonomies;
        }

        /**
         * Set the requirements list for the given post type
         *
         * @param array $requirements
         * @param string $post_type
         *
         * @return array
         */
        public function filter_post_type_requirements($requirements, $post_type)
        {
            $classes = [];

            // Check the supported taxonomies for the post type.
            $taxonomies = $this->getPostTypeTaxonomies($post_type);

            $taxonomies_map = [
                'category' => [
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Categories_count',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Required_categories',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Prohibited_categories',
                ],
                'post_tag' => [
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Tags_count',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Required_tags',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Prohibited_tags',
                ],
            ];

            foreach ($taxonomies as $taxonomy) {
                if (array_key_exists($taxonomy, $taxonomies_map)) {
                    $classes = array_merge($classes, $taxonomies_map[$taxonomy]);
                } else {
                    $classes[] = maybe_serialize(
                        [
                            'class'  => '\\PublishPress\\Checklists\\Core\\Requirement\\Taxonomies_count',
                            'params' => [
                                'post_type' => $post_type,
                                'taxonomy'  => $taxonomy,
                            ],
                        ]
                    );
                }
            }

            $classes[] = '\\PublishPress\\Checklists\\Core\\Requirement\\Approved_by';

            // Check the "supports" for the post type.
            $supports_map = [
                'title'     => [
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Title_count',
                ],
                'editor'    => [
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Words_count',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Internal_links',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\External_links',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Image_alt',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Image_alt_count',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Validate_links',
                ],
                'thumbnail' => [
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Featured_image',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Featured_image_alt',
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Featured_image_caption',
                ],
                'excerpt'   => [
                    '\\PublishPress\\Checklists\\Core\\Requirement\\Filled_excerpt',
                ],
            ];

            // Config-driven Pro rules
            if (!Util::isChecklistsProActive()) {
                $pro_requirements_file = __DIR__ . '/pro-requirements.php';
                if ( file_exists( $pro_requirements_file ) ) {
                    $pro_requirements = include $pro_requirements_file;
                    foreach ( $pro_requirements as $req ) {
                        if (
                        ! empty( $req['post_types'] )
                        && ! in_array( $post_type, (array) $req['post_types'], true )
                        ) {
                            continue;
                        }
                        
                        $support = Pro_Requirement::get_support_for_config( $req );
                        if ( post_type_supports( $post_type, $support ) ) {
                            $supports_map[ $support ][] = [
                                'class'  => Pro_Requirement::class,
                                'params' => $req,
                            ];
                        }
                    }
                }
            }

            foreach ($supports_map as $supports => $requirements) {
                foreach ($requirements as $requirement) {
                    if (post_type_supports($post_type, $supports)) {
                        $classes[] = $requirement;
                    }
                }
            }

            if (!empty($classes)) {
                $requirements = $classes;
            }

            return $requirements;
        }

        /**
         * Initialize the module. Conditionally loads if the module is enabled
         */
        public function init()
        {
            add_action('publishpress_checklists_admin_menu_page', [$this, 'action_admin_menu_page']);
            add_action('publishpress_checklists_admin_submenu', [$this, 'action_admin_submenu']);
            add_action('add_meta_boxes', [$this, 'handle_post_meta_boxes']);
            add_action('save_post', [$this, 'save_post_meta_box'], 10, 2);
            add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);

            add_filter(
                'publishpress_checklists_post_type_requirements',
                [$this, 'filter_post_type_requirements'],
                10,
                2
            );
            add_filter('publishpress_checklists_post_types', [$this, 'filter_post_types']);

            add_action('admin_init', [$this, 'migrateLegacyOptions']);
            add_action('admin_init', [$this, 'save_global_checklist']);

            // Editor
            add_filter('mce_external_plugins', [$this, 'add_mce_plugin']);

            add_action('admin_enqueue_scripts', [$this, 'add_admin_scripts']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);

            do_action('publishpress_checklists_load_addons');

            // Load the requirements
            $this->instantiate_requirement_classes();
            do_action('publishpress_checklists_load_requirements');

            add_filter('publishpress_checklists_rules_list', [$this, 'filterRulesList']);

            add_filter('publishpress_checklists_requirement_list', [$this, 'filterRequirementsRule'], 1000);

            // Redirect on plugin activation
            add_action('admin_init', [$this, 'redirect_on_activate'], 2000);
        }

        /**
         * Load default editorial metadata the first time the module is loaded
         *
         * @since 0.7
         */
        public function install() {}

        /**
         * Upgrade our data in case we need to
         *
         * @since 0.7
         */
        public function upgrade($previous_version) {}

        /**
         * Generate a link to one of the editorial metadata actions
         *
         * @param array $args (optional) Action and any query args to add to the URL
         *
         * @return string $link Direct link to complete the action
         * @since 0.7
         *
         */
        protected function get_admin_link($args = [])
        {
            $args['page']   = 'ppch-settings';
            $args['module'] = 'ppch-settings';

            return add_query_arg($args, get_admin_url(null, 'admin.php'));
        }

        /**
         * Add the MCE plugin file to make the interface between the editor and
         * the requirement meta box. This was the unique way that worked, making
         * it loaded before the MCE is initialized, allowing to configure it.
         *
         * @param array $plugin_array
         */
        public function add_mce_plugin($plugin_array)
        {
            if (is_admin()) {
                $plugin_array['pp_checklists_requirements'] =
                    Util::pluginDirUrl()
                    . 'modules/checklists/assets/js/tinymce-pp-checklists-requirements.js';
            }

            return $plugin_array;
        }

        /**
         * Enqueue scripts and stylesheets for the admin pages.
         */
        public function add_admin_scripts()
        {
            $screen = get_current_screen();

            // Post edit pages, for displaying the checklists metabox.
            if (!is_null($screen) && $screen->base === 'post') {
                $supported_post_types = $this->getSelectedPostTypes();

                if (array_key_exists($screen->post_type, $supported_post_types)) {
                    wp_enqueue_style(
                        'pp-checklists-requirements',
                        $this->module_url . 'assets/css/post-editor-checklists.css',
                        false,
                        PPCH_VERSION,
                        'all'
                    );

                    wp_register_style(
                        'pp-remodal',
                        $this->module_url . 'assets/css/remodal.css',
                        false,
                        PPCH_VERSION,
                        'all'
                    );
                    wp_register_style(
                        'pp-remodal-default-theme',
                        $this->module_url . 'assets/css/remodal-default-theme.css',
                        ['pp-remodal'],
                        PPCH_VERSION,
                        'all'
                    );

                    wp_register_script(
                        'pp-remodal',
                        $this->module_url . 'assets/js/remodal.min.js',
                        ['jquery'],
                        PPCH_VERSION,
                        true
                    );

                    wp_enqueue_style('pp-remodal-default-theme');
                    wp_enqueue_script('pp-remodal');
                }
            } elseif (isset($_GET['page']) && $_GET['page'] === 'ppch-checklists') {
                // Admin pages
                wp_enqueue_style(
                    'pp-checklists-global-checklists',
                    $this->module_url . 'assets/css/admin-pages.css',
                    [],
                    PPCH_VERSION,
                    'all'
                );

                wp_enqueue_script(
                    'pp-checklists-global-checklists',
                    $this->module_url . 'assets/js/global-checklists.js',
                    ['jquery'],
                    PPCH_VERSION,
                    true
                );


                $rules = apply_filters('publishpress_checklists_rules_list', []);
                $roles = self::get_editable_roles_labels();

                // Get all the keys of post types, to select the first one for the JS script
                $postTypes = array_keys($this->get_post_types());
                // Make sure we are on the first item
                reset($postTypes);

                //required rules for option validation
                $required_rules = array(
                    Plugin::RULE_ONLY_DISPLAY,
                    Plugin::RULE_WARNING,
                    Plugin::RULE_BLOCK,
                );

                wp_localize_script(
                    'pp-checklists-global-checklists',
                    'objectL10n_checklists_global_checklist',
                    [
                        'rules'             => $rules,
                        'roles'             => $roles,
                        'first_post_type'   => current($postTypes),
                        'required_rules'    => $required_rules,
                        'ajaxurl'           => admin_url('admin-ajax.php'),
                        'nonce'             => wp_create_nonce('pp-checklists-rules'),
                        'submit_error'      => esc_html__(
                            'options cannot be empty.',
                            'publishpress-checklists'
                        ),
                        'custom_item_error' => esc_html__(
                            'Please make sure to add a name for all the custom tasks.',
                            'publishpress-checklists'
                        ),
                        'editable_by'       => esc_html__(
                            'Which roles can mark this task as complete?',
                            'publishpress-checklists'
                        ),
                        'noResults'         => esc_html__('No results found', 'publishpress-checklists'),
                        'searching'         => esc_html__('Searching…', 'publishpress-checklists'),
                        'remove'            => esc_html__('Remove', 'publishpress-checklists'),
                        'custom_enter_name' => esc_html__('Enter name of custom task', 'publishpress-checklists'),
                        'openai_enter_name' => esc_html__('Enter OpenAI task prompt', 'publishpress-checklists'),
                        'suggestion_title' => esc_html__('Suggested Prompts', 'publishpress-checklists'),
                        'openai_option_description' => esc_html__('What\'s the expected OpenAI response to mark the requirement as pass?', 'publishpress-checklists'),
                        'openai_suggestions' => [
                            'clear_content' => [
                                'label' => esc_html__('Clear Content', 'publishpress-checklists'),
                                'prompt' => esc_html__('Is this content clear and easy to read?', 'publishpress-checklists'),
                            ],
                            'friendly_tone' => [
                                'label' => esc_html__('Friendly Tone Content', 'publishpress-checklists'),
                                'prompt' => esc_html__('Is this content tone friendly?', 'publishpress-checklists'),
                            ],
                            'professional_tone' => [
                                'label' => esc_html__('Professional Tone Content', 'publishpress-checklists'),
                                'prompt' => esc_html__('Is this content tone professional?', 'publishpress-checklists'),
                            ],
                            'persuasive_tone' => [
                                'label' => esc_html__('Persuasive Tone Content', 'publishpress-checklists'),
                                'prompt' => esc_html__('Is this content tone persuasive?', 'publishpress-checklists'),
                            ],
                            'empathetic_tone' => [
                                'label' => esc_html__('Empathetic Tone Content', 'publishpress-checklists'),
                                'prompt' => esc_html__('Is this content tone empathetic?', 'publishpress-checklists'),
                            ],
                            'adventurous_tone' => [
                                'label' => esc_html__('Adventurous Tone Content', 'publishpress-checklists'),
                                'prompt' => esc_html__('Is this content tone adventurous?', 'publishpress-checklists'),
                            ],
                            'promotional_tone' => [
                                'label' => esc_html__('Promotional Tone Content', 'publishpress-checklists'),
                                'prompt' => esc_html__('Is this content tone promotional?', 'publishpress-checklists'),
                            ]
                        ],
                    ]
                );
            } elseif (isset($_GET['page']) && $_GET['page'] === 'ppch-settings') {
                // Admin pages
                wp_enqueue_style(
                    'pp-checklists-global-checklists',
                    $this->module_url . 'assets/css/admin-pages.css',
                    [],
                    PPCH_VERSION,
                    'all'
                );
            }
        }

        /**
         * Enqueue scripts and stylesheets for the admin pages.
         */
        public function enqueueAdminScripts()
        {
            if (isset($_GET['page']) && $_GET['page'] === 'ppch-checklists') {
                wp_enqueue_script(
                    'publishpress-select2-js',
                    plugins_url('/assets/lib/select2-v4.0.13/js/select2.full.min.js', PPCH_FILE),
                    [],
                    PPCH_VERSION
                );

                wp_enqueue_script(
                    'publishpress-checklists-admin-js',
                    plugins_url('/modules/permissions/assets/js/admin.js', PPCH_FILE),
                    ['jquery', 'publishpress-select2-js'],
                    PPCH_VERSION
                );

                wp_enqueue_style(
                    'publishpress-select2-css',
                    plugins_url('/assets/lib/select2-v4.0.13/css/select2.min.css', PPCH_FILE),
                    false,
                    PPCH_VERSION,
                    'screen'
                );

                wp_enqueue_style(
                    'publishpress-checklists-admin-css',
                    plugins_url('/modules/permissions/assets/css/admin.css', PPCH_FILE),
                    false,
                    PPCH_VERSION,
                    'screen'
                );
            }
        }

        /*
        ==================================
        =            Meta boxes          =
        ==================================
        */

        /**
         * Load the post meta boxes for all of the post types that are supported
         */
        public function handle_post_meta_boxes()
        {
            $title = esc_html__('Checklist', 'publishpress-checklists');
            $supported_post_types = $this->getSelectedPostTypes();

            // Hide checklist meta box from acf plugin
            $excludeKey = 'acf-field-group';
            if (array_key_exists($excludeKey, $supported_post_types)) {
                unset($supported_post_types[$excludeKey]);
            }

            foreach ($supported_post_types as $post_type => $label) {
                // Create a dummy post object for requirement checks
                $dummy_post = (object) [ 'post_type' => $post_type ];
                $requirements = [];
                $requirements = apply_filters('publishpress_checklists_requirement_list', $requirements, $dummy_post);

                if (!empty($requirements)) {
                    add_meta_box(self::METADATA_TAXONOMY, $title, [$this, 'display_meta_box'], $post_type, 'side', 'high');
                }
            }
        }

        protected function getSelectedPostTypes()
        {
            $legacyPlugin  = Factory::getLegacyPlugin();
            $postTypeSlugs = $this->getPostTypesForModule($legacyPlugin->settings->module);
            $postTypes     = [];

            foreach ($postTypeSlugs as $slug) {
                $postType = get_post_type_object($slug);
                if (is_object($postType)) {
                    // Need to overide the value to prevent user confusion
                    if ($slug === 'acf-field-group') $postType->label = 'ACF';
                    $postTypes[$slug] = $postType->label;
                }
            }

            return $postTypes;
        }

        /**
         * Displays HTML output for Checklist post meta box
         *
         * @param object $post Current post
         */
        public function display_meta_box($post)
        {
            $requirements = [];

            // Apply filters to the list of requirements
            $requirements = apply_filters('publishpress_checklists_requirement_list', $requirements, $post);

            $new_requirements_array = $this->rearrange_requirement_array($requirements);

            $legacyPlugin = Factory::getLegacyPlugin();

            $options = get_option('publishpress_checklists_settings_options');

            $checklistsLink = add_query_arg(['page' => 'ppch-checklists'], get_admin_url(null, 'admin.php'));

            // Add the scripts
            if (!empty($requirements)) {
                wp_enqueue_script(
                    'pp-checklists-requirements',
                    $this->module_url . 'assets/js/meta-box.js',
                    ['jquery', 'word-count'],
                    PPCH_VERSION,
                    true
                );

                wp_localize_script(
                    'pp-checklists-requirements',
                    'ppChecklists',
                    [
                        'requirements'                    => $new_requirements_array,
                        'configure_link'                  => $checklistsLink,
                        'nonce'                           => wp_create_nonce('pp-checklists-requirements'),
                        'empty_checklist_message'         => esc_html__(
                            'You don\'t have to complete any Checklist tasks.',
                            'publishpress-checklists'
                        ),
                        'label_checklist'                 => esc_html__('Checklist', 'publishpress-checklists'),
                        'msg_missed_optional_publishing'  => esc_html__(
                            'Are you sure you want to publish anyway?',
                            'publishpress-checklists'
                        ),
                        'msg_missed_optional_updating'    => esc_html__(
                            'Are you sure you want to update the published post anyway?',
                            'publishpress-checklists'
                        ),
                        'msg_missed_required_publishing'  => esc_html__(
                            'Please complete the following tasks before publishing:',
                            'publishpress-checklists'
                        ),
                        'msg_missed_required_updating'    => esc_html__(
                            'Please complete the following tasks before updating the published post:',
                            'publishpress-checklists'
                        ),
                        'msg_missed_important_publishing' => esc_html__(
                            'Not required, but important: ',
                            'publishpress-checklists'
                        ),
                        'msg_missed_important_updating'   => esc_html__(
                            'Not required, but important: ',
                            'publishpress-checklists'
                        ),
                        'show_warning_icon_submit' => Base_requirement::VALUE_YES === $legacyPlugin->settings->module->options->show_warning_icon_submit,
                        'disable_publish_button'   => Base_requirement::VALUE_YES === $legacyPlugin->settings->module->options->disable_publish_button,
                        'title_warning_icon'       => esc_html__('One or more items in the checklist are not completed', 'publishpress-checklists'),
                        'is_gutenberg_active'      => $this->is_gutenberg_active(),
                        'user_can_manage_options'  => current_user_can('manage_options'),
                        'configure_url'            => esc_url($this->get_admin_link()),
                        'status_filter_enabled'    => isset($options->status_filter_enabled) ? $options->status_filter_enabled : 'off',
                    ]
                );

                do_action('publishpress_checklists_enqueue_scripts');
            }

            // Render the box
            $templateLoader = Factory::getTemplateLoader();

            $templateLoader->load(
                'checklists',
                'meta-box',
                [
                    'metadata_taxonomy' => self::METADATA_TAXONOMY,
                    'requirements'      => $new_requirements_array,
                    'configure_link'    => $checklistsLink,
                    'nonce'             => wp_create_nonce(__FILE__),
                    'lang'              => [
                        'empty_checklist_message' => esc_html__(
                            'You don\'t have to complete any %sChecklist tasks%s.',
                            'publishpress-checklists'
                        ),
                        'required'                => esc_html__('Required', 'publishpress-checklists'),
                        'check'                   => esc_html__('Check Now', 'publishpress-checklists'),
                        'ok'                      => esc_html__('Ok', 'publishpress-checklists'),
                        'no'                      => esc_html__('No', 'publishpress-checklists'),
                        'yes'                     => esc_html__('Yes', 'publishpress-checklists'),

                    ],
                ]
            );
        }

        private function is_gutenberg_active()
        {
            $gutenberg    = false;
            $block_editor = false;

            if (has_filter('replace_editor', 'gutenberg_init')) {
                // Gutenberg is installed and activated.
                $gutenberg = true;
            }

            if (version_compare($GLOBALS['wp_version'], '5.0-beta', '>')) {
                // Block editor.
                $block_editor = true;
            }

            // WPBakery compatibility
            if (get_option('wpb_js_gutenberg_disable')) {
                $block_editor = false;
            }

            if (!$gutenberg && !$block_editor) {
                return false;
            }

            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            if (!is_plugin_active('classic-editor/classic-editor.php')) {
                return true;
            }

            // Classic Editor is installed

            $replaceOption = get_option('classic-editor-replace');

            $use_block_editor = $replaceOption === 'no-replace' || $replaceOption === 'block';

            if (!$use_block_editor && isset($_GET['classic-editor__forget'])) {
                $use_block_editor = true;
            }

            if (isset($_GET['classic-editor'])) {
                $use_block_editor = false;
            }

            return $use_block_editor;
        }

        /**
         * Save the state of custom items.
         *
         * @param int $id Unique ID for the post being saved
         * @param object $post Post object
         */
        public function save_post_meta_box($id, $post)
        {
            // Authentication checks: make sure data came from our meta box and that the current user is allowed to edit the post
            // TODO: switch to using check_admin_referrer? See core (e.g. edit.php) for usage
            if (
                !isset($_POST[self::METADATA_TAXONOMY . "_nonce"])
                || !wp_verify_nonce(sanitize_key($_POST[self::METADATA_TAXONOMY . "_nonce"]), __FILE__)
            ) {
                return $id;
            }

            if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                || !array_key_exists($post->post_type, $this->getSelectedPostTypes())
                || $post->post_type == 'post' && !current_user_can('edit_post', $id)
                || $post->post_type == 'page' && !current_user_can('edit_page', $id)
            ) {
                return $id;
            }

            // Check if we have data coming from custom items
            if (isset($_POST['_PPCH_custom_item'])) {
                if (!empty($_POST['_PPCH_custom_item'])) {
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    foreach ($_POST['_PPCH_custom_item'] as $item_id => $value) {
                        update_post_meta($id, self::POST_META_PREFIX . sanitize_key($item_id), sanitize_text_field($value));
                    }
                }
            }
        }

        /**
         * Creates the admin menu if there is no menu set.
         */
        public function action_admin_menu_page()
        {
            $legacyPlugin = Factory::getLegacyPlugin();

            $legacyPlugin->addMenuPage(
                apply_filters(
                    'publishpress_checklists_plugin_title',
                    esc_html__('Checklists', 'publishpress-checklists')
                ),
                apply_filters('publishpress_checklists_manage_checklist_cap', 'manage_checklists'),
                self::MENU_SLUG,
                [$this, 'options_page_controller']
            );
        }

        /**
         * Add necessary things to the admin menu
         */
        public function action_admin_submenu()
        {
            $legacyPlugin = Factory::getLegacyPlugin();

            $menuLabel = esc_html__('Checklists', 'publishpress-checklists');

            // Main Menu
            if (defined('PHP_VERSION') && version_compare(PHP_VERSION, '8', '<')) {
                add_submenu_page(
                    $legacyPlugin->getMenuSlug(),
                    $menuLabel,
                    $menuLabel,
                    apply_filters('publishpress_checklists_manage_checklist_cap', 'manage_checklists'),
                    self::MENU_SLUG,
                    [$this, 'options_page_controller']
                );
            }

            add_submenu_page(
                $legacyPlugin->getMenuSlug(),
                $menuLabel,
                $menuLabel,
                apply_filters('publishpress_checklists_manage_checklist_cap', 'manage_checklists'),
                self::MENU_SLUG,
                [$this, 'options_page_controller']
            );
        }

        public function options_page_controller()
        {
            // Apply filters to the list of requirements
            $post_types = $this->get_post_types();

            wp_enqueue_script('jquery-ui-sortable');

            $templateLoader = Factory::getTemplateLoader();

            $this->printDefaultHeader($this->module);

            $new_requirements_array = array();

            foreach ($this->requirements as $post_type => $requirements) {
                $new_requirements_array[$post_type] = $this->rearrange_requirement_array($requirements, false);
            }

            $templateLoader->load(
                'checklists',
                'global-checklists',
                [
                    'requirements' => $new_requirements_array,
                    'tabs'         => $this->field_tabs,
                    'post_types'   => $post_types,
                    'success'      => isset($_GET['success']) && $_GET['success'] === '1',
                    'lang'         => [
                        'description'     => esc_html__('Task', 'publishpress-checklists'),
                        'action'          => esc_html__('Disabled, Recommended or Required', 'publishpress-checklists'),
                        'params'          => esc_html__('Options', 'publishpress-checklists'),
                        'add_custom_item' => esc_html__('Add custom task', 'publishpress-checklists'),
                    ],
                ]
            );

            if (apply_filters('publishpress_checklist_display_branding', true)) {
                $this->printDefaultFooter($this->module);
            }
        }

        /**
         * Get a list of rules for the requirement
         *
         * @param $rules
         *
         * @return array
         */
        public function filterRulesList($rules)
        {
            return array_merge(
                $rules,
                [
                    Plugin::RULE_DISABLED => esc_html__('Disabled', 'publishpress-checklists'),
                    Plugin::RULE_WARNING  => esc_html__('Recommended', 'publishpress-checklists'),
                    Plugin::RULE_BLOCK    => esc_html__('Required', 'publishpress-checklists'),
                ]
            );
        }

        /**
         * Recognize RULE_ONLY_DISPLAY rule as RULE_WARNING
         *
         * @param $requirements
         *
         * @return $requirements
         */
        public function filterRequirementsRule($requirements)
        {
            foreach ($requirements as $requirement => $requirementData) {
                $requirements[$requirement]['rule'] = $requirementData['rule'] === Plugin::RULE_ONLY_DISPLAY ? Plugin::RULE_WARNING : $requirementData['rule'];
            }

            return $requirements;
        }

        /**
         * Enqueue Gutenberg assets.
         */
        public function enqueue_block_editor_assets()
        {
            $screen = get_current_screen();

            if (!is_null($screen)) {
                $supported_post_types = $this->getSelectedPostTypes();

                if ($screen->base === 'post' && array_key_exists($screen->post_type, $supported_post_types)) {
                    // Required thing to build Gutenberg Blocks
                    wp_enqueue_script(
                        'pp-checklists-requirements-gutenberg',
                        plugins_url('/modules/checklists/assets/js/gutenberg-warning.min.js', PPCH_FILE),
                        [
                            'wp-i18n',
                            'wp-element',
                            'wp-hooks',
                            'wp-edit-post',
                            'react',
                            'react-dom',
                        ],
                        PPCH_VERSION,
                        true
                    );
                    wp_enqueue_script(
                        'pp-checklists-panel-gutenberg',
                        plugins_url('/modules/checklists/assets/js/gutenberg-panel.min.js', PPCH_FILE),
                        [
                            'wp-i18n',
                            'wp-element',
                            'wp-hooks',
                            'wp-edit-post',
                            'wp-polyfill',
                            'react',
                            'react-dom',
                        ],
                        PPCH_VERSION,
                        true
                    );
                    wp_localize_script(
                        'pp-checklists-panel-gutenberg',
                        'i18n',
                        array(
                            'completeRequirementMessage' => __("Please complete the required(*) checklists task.", "publishpress-checklists"),
                            'checklistLabel' => __("Checklists", "publishpress-checklists"),
                            'noTaskLabel' => __("You don't have to complete any Checklist tasks.", "publishpress-checklists"),
                            'required' => __("required", "publishpress-checklists"),
                            'elementorNotice' => __("Checklists tasks are not available in Elementor editors", "publishpress-checklists"),
                            'isElementorEnabled' => ElementorUtils::isElementorEnabled() ? "1" : "0",
                        )
                    );
                }
            }
        }

        /**
         * Validate data entered by the user
         */
        public function save_global_checklist()
        {
            if (!isset($_GET['page']) || $_GET['page'] !== self::MENU_SLUG) {
                return;
            }

            if (!isset($_POST['publishpress_checklists_checklists_options']) || empty($_POST['publishpress_checklists_checklists_options'])) {
                return;
            }

            if (!wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'ppch-global-checklists')) {
                return;
            }

            $manageChecklistsCap = apply_filters(
                'publishpress_checklists_manage_checklist_cap',
                'manage_checklists'
            );
            if (!current_user_can($manageChecklistsCap)) {
                return;
            }

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_options = $_POST['publishpress_checklists_checklists_options'];

            //sanitize checklists options
            $new_options = $this->sanitize_checklists_options($new_options);

            // Instantiate custom items so they are able to process the settings validations
            $this->instantiate_custom_items_to_validate_settings($new_options);


            $options = (array)get_option('publishpress_checklists_checklists_options');

            if (empty($options)) {
                $options = [];
            }

            $new_option_keys = array_keys($new_options);

            $sorted_options = array_merge(array_flip($new_option_keys), $options);

            $options = array_merge($sorted_options, $new_options);

            $options = apply_filters('publishpress_checklists_validate_requirement_settings', $options);

            $options = (object)$options;

            update_option('publishpress_checklists_checklists_options', $options);

            // Reload the module's options after saving.
            if (isset($_SERVER['HTTP_REFERER'])) {
                $redirect_url = $_SERVER['HTTP_REFERER'];
            } else {
                $redirect_url = admin_url('admin.php?page=' . self::MENU_SLUG);
            }
            
            // Preserve tab state in redirect URL
            if (isset($_POST['ppch_active_post_type']) && !empty($_POST['ppch_active_post_type'])) {
                $redirect_url = add_query_arg('post_type', sanitize_text_field($_POST['ppch_active_post_type']), $redirect_url);
            }
            
            if (isset($_POST['ppch_active_inner_tab']) && !empty($_POST['ppch_active_inner_tab'])) {
                $redirect_url = add_query_arg('inner_tab', sanitize_text_field($_POST['ppch_active_inner_tab']), $redirect_url);
            }
            
            // Add success parameter to show success notice
            $redirect_url = add_query_arg('success', '1', $redirect_url);
            
            wp_redirect($redirect_url);
            exit();
        }

        /**
         * Instantiate custom items according to the new_options.
         *
         * @param array $new_options
         */
        protected function instantiate_custom_items_to_validate_settings($new_options)
        {
            if (isset($new_options['custom_items']) && !empty($new_options['custom_items'])) {
                foreach ($new_options['custom_items'] as $id) {
                    if (isset($new_options[$id . '_title'])) {
                        foreach ($new_options[$id . '_title'] as $post_type => $title) {
                            $custom_item = new Custom_item($id, $this->module, $post_type);
                            $custom_item->init();
                        }
                    }
                }
            }

            if (isset($new_options['openai_items']) && !empty($new_options['openai_items'])) {
                foreach ($new_options['openai_items'] as $id) {
                    if (isset($new_options[$id . '_title'])) {
                        foreach ($new_options[$id . '_title'] as $post_type => $title) {
                            $openai_item = new Openai_item($id, $this->module, $post_type);
                            $openai_item->init();
                        }
                    }
                }
            }
        }

        /**
         * Sanitize checklists options.
         *
         * @param array $new_options
         * 
         * @return array $new_options
         */
        protected function sanitize_checklists_options($new_options)
        {
            foreach ($new_options as $option_key => $option_value) {
                //sanitize original key
                $sanitized_key = sanitize_key($option_key);

                //option value is an array of keys => $value pair
                $sanitized_value = [];
                foreach ($option_value as $option_value_key => $option_value_value) {
                    $sanitized_value[sanitize_key($option_value_key)] = is_array($option_value_value) ? array_map('sanitize_text_field', $option_value_value) : sanitize_text_field($option_value_value);
                }

                //unset original option sanitize_key can potentially change key value if they are manipulated ?
                unset($new_options[$option_key]);

                //add santized options
                $new_options[$sanitized_key] = $sanitized_value;
            }

            return $new_options;
        }

        /**
         * Rearrange the requirements array by custom order
         *
         * @param array $requirements
         * @param boolean $is_on_metabox
         */
        protected function rearrange_requirement_array($requirements, $is_on_metabox = true)
        {
            $options = (array)get_option('publishpress_checklists_checklists_options');

            $requirement_rule_array = [];
            $new_requirements_array = [];

            if ($is_on_metabox) {
                foreach ($requirements as $requirement_key => $p_requirements) {
                    $requirement_rule_array[$requirement_key . '_rule'] = $requirement_key;
                }
            } else {
                $index = 0;
                foreach ($requirements as $requirement) {
                    $requirement_rule_array[$requirement->name . '_rule'] = $index++;
                }
            }

            $new_arr = array_intersect_key($options, $requirement_rule_array);

            $requirement_rule_array = array_merge(array_flip(array_keys($new_arr)), $requirement_rule_array);

            $index = 0;
            foreach ($requirement_rule_array as $req_index) {
                $new_index                          = ($is_on_metabox) ? $req_index : $index++;
                $new_requirements_array[$new_index] = $requirements[$req_index];
            };

            return $new_requirements_array;
        }


        /**
         * Redirect user on plugin activation
         *
         * @return void
         */
        public function redirect_on_activate()
        {
            if (get_option('ppch_activated')) {
                delete_option('ppch_activated');
                wp_redirect(admin_url("admin.php?page=ppch-checklists"));
                exit;
            }
        }

        /**
         * Retrieves the field tabs and assigns them to the class property.
         */
        public function retrieveFieldTabs()
        {
            // Get the singleton instance
            $fieldsTabs = FieldsTabs::getInstance();
            $postTypes = $this->get_post_types();
            $allFieldsTabs =  $fieldsTabs->getFieldsTabs();
            $filteredFieldsTabs = array_filter($allFieldsTabs, function ($_, $key) {
                return !in_array($key, ['advanced-custom-fields']);
            }, ARRAY_FILTER_USE_BOTH);
            $result = [];
            foreach ($postTypes as $key => $postType) {
                $result[$key] = $filteredFieldsTabs;
            }

            $this->field_tabs = apply_filters('publishpress_checklists_filter_field_tabs', $result, $allFieldsTabs);
        }
    }
}
