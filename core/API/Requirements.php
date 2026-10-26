<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       2.18.0
 */

namespace PublishPress\Checklists\Core\API;

use PublishPress\Checklists\Core\Plugin;

\defined('ABSPATH') || exit;

/**
 * API class for programmatically setting requirement values
 */
class Requirements
{
    /**
     * Rule mapping from frontend to backend
     */
    public const RULE_MAPPING = [
        'required' => Plugin::RULE_BLOCK,
        'recommended' => Plugin::RULE_WARNING,
        'disabled' => Plugin::RULE_DISABLED
    ];

    /**
     * Set a requirement configuration
     *
     * @param string $requirement_name The name of the requirement (e.g., 'words_count')
     * @param array $config Configuration array with the following keys:
     *                      - rule: (string) The rule to set ('required', 'recommended', 'disabled')
     *                      - params: (array) Associative array of parameter names and values
     *                      - terms: (array) Array of term IDs for taxonomy requirements
     *                      - ignored_by: (array) Array of role slugs that can ignore the task
     * @param string|array $post_types Post type(s) to apply the configuration to. Use 'all' for all post types.
     * @return bool True if successful, false otherwise
     */
    public function set_requirement($requirement_name, $config, $post_types = 'all')
    {
        // Get the options
        $options = get_option('publishpress_checklists_checklists_options');
        if (!is_object($options)) {
            $options = new \stdClass();
        }

        // Get post types
        $post_types_array = $this->get_post_types_array($post_types);
        
        // Set the rule if provided
        if (isset($config['rule'])) {
            if (!$this->validate_rule($config['rule'])) {
                return false;
            }
            
            // Map frontend rule to backend rule
            $backend_rule = self::RULE_MAPPING[$config['rule']];
            
            // Prepare the option name
            $option_name = $requirement_name . '_rule';
            
            // If the option doesn't exist, create it
            if (!isset($options->{$option_name})) {
                $options->{$option_name} = [];
            }
            
            // Set the rule for each post type
            foreach ($post_types_array as $post_type) {
                $options->{$option_name}[$post_type] = $backend_rule;
            }
        }
        
        // Set parameters if provided
        if (isset($config['params']) && is_array($config['params'])) {
            foreach ($config['params'] as $param_name => $value) {
                // Prepare the option name
                $option_name = $requirement_name . '_' . $param_name;
                
                // If the option doesn't exist, create it
                if (!isset($options->{$option_name})) {
                    $options->{$option_name} = [];
                }
                
                // Set the parameter for each post type
                foreach ($post_types_array as $post_type) {
                    $options->{$option_name}[$post_type] = $value;
                }
            }
        }
        
        // Handle taxonomy terms if provided
        if (isset($config['terms']) && is_array($config['terms'])) {
            // Check if this is a taxonomy requirement
            $is_taxonomy_requirement = false;
            $taxonomy = null;
            
            // Determine taxonomy based on requirement name
            if (strpos($requirement_name, 'categories') !== false) {
                $is_taxonomy_requirement = true;
                $taxonomy = 'category';
            } elseif (strpos($requirement_name, 'tags') !== false) {
                $is_taxonomy_requirement = true;
                $taxonomy = 'post_tag';
            }
            
            if ($is_taxonomy_requirement && !empty($taxonomy)) {
                // Format term IDs to ID__Name format required by the plugin
                $formatted_terms = [];
                foreach ($config['terms'] as $term_id) {
                    $term = get_term($term_id, $taxonomy);
                    if (!is_wp_error($term) && $term) {
                        $formatted_terms[] = $term_id . '__' . $term->name;
                    }
                }
                
                // Set the multiple parameter with formatted terms
                $option_name = $requirement_name . '_multiple';
                
                // If the option doesn't exist, create it
                if (!isset($options->{$option_name})) {
                    $options->{$option_name} = [];
                }
                
                // Set the parameter for each post type
                foreach ($post_types_array as $post_type) {
                    $options->{$option_name}[$post_type] = $formatted_terms;
                }
            }
        }
        
        // Set who can ignore if provided
        if (isset($config['ignored_by']) && is_array($config['ignored_by'])) {
            // Prepare the option name - using '_can_ignore' to match the plugin's core functionality
            $option_name = $requirement_name . '_can_ignore';
            
            // If the option doesn't exist, create it
            if (!isset($options->{$option_name})) {
                $options->{$option_name} = [];
            }
            
            // Set the ignored_by for each post type
            foreach ($post_types_array as $post_type) {
                $options->{$option_name}[$post_type] = $config['ignored_by'];
            }
        }
        
        // Save the options
        return update_option('publishpress_checklists_checklists_options', $options);
    }

    /**
     * Validate the rule value
     *
     * @param string $rule
     * @return bool
     */
    private function validate_rule($rule)
    {
        return in_array($rule, array_keys(self::RULE_MAPPING));
    }

    /**
     * Get an array of post types based on input
     *
     * @param string|array $post_types
     * @return array
     */
    private function get_post_types_array($post_types)
    {
        if ($post_types === 'all') {
            // Get all post types that support checklists
            $settings = get_option('publishpress_checklists_settings_options');
            if (is_object($settings) && isset($settings->post_types)) {
                return array_keys((array)$settings->post_types);
            }
            
            // Fallback to default post types
            return ['post', 'page'];
        }
        
        return is_array($post_types) ? $post_types : [$post_types];
    }
}
