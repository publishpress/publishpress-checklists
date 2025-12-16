<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Requirement;

use PublishPress\Checklists\Core\Factory;
use PPCH_Checklists;

defined('ABSPATH') or die('No direct script access allowed.');

class Openai_item extends Base_simple implements Interface_required
{
    const VALUE_YES = 'yes';

    const OPEN_AI_MODEL = 'gpt-3.5-turbo';

    const OPEN_AI_API_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * The title.
     *
     * @var string
     */
    protected $title;

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'custom';

    public $require_button;

    /**
     * The constructor. It adds the action to load the requirement.
     *
     * @param string $name
     * @param string $module
     * @param string $post_type
     *
     * @return  void
     */
    public function __construct($name, $module, $post_type)
    {
        $this->name      = trim((string)$name);
        $this->require_button = true;
        $this->is_custom = false;
        $this->group     = 'custom';

        parent::__construct($module, $post_type);

        $this->init_hooks();
    }

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']          = __('Openai', 'publishpress-checklists');
        $this->lang['label_settings'] = __('Openai', 'publishpress-checklists');
    }

    public function init_hooks() {
        global $open_ai_hooks_init;

        if ($open_ai_hooks_init) {
            return;
        }

        add_action('wp_ajax_pp_checklists_openai_requirement', [$this, 'handle_ajax_request']);
        
        $open_ai_hooks_init = true;
    }

    /**
     * Get the HTML for the title setting field for the specific post type.
     *
     * @return string
     */
    public function get_setting_title_html($css_class = '')
    {
        $var_name = $this->name . '_title';

        $name = 'publishpress_checklists_checklists_options[' . $var_name . '][' . $this->post_type . ']';

        $html = sprintf(
            '<textarea name="%s" data-id="%s" placeholder="%s" class="pp-checklists-custom-item-title">%s</textarea>',
            $name,
            esc_attr($this->name),
            esc_html__('Enter OpenAI task prompt', 'publishpress-checklists'),
            esc_attr($this->get_title())
        );

        $html .= sprintf(
            '<input type="hidden" name="publishpress_checklists_checklists_options[openai_items][]" value="%s" />',
            esc_attr($this->name)
        );

        return $html;
    }

    /**
     * Returns the title of this custom item.
     *
     * @return string
     */
    public function get_title()
    {
        if (!empty($this->title)) {
            return $this->title;
        }

        $title    = '';
        $var_name = $this->name . '_title';

        if (isset($this->module->options->{$var_name}[$this->post_type])) {
            $title = stripslashes($this->module->options->{$var_name}[$this->post_type]);
        }

        $this->title = $title;

        return $this->title;
    }

    /**
     * Get the HTML for the setting field for the specific post type.
     *
     * @return string
     */
    public function get_setting_field_html($css_class = '')
    {
        $html = parent::get_setting_field_html(esc_attr($css_class));

        $html .= sprintf(
            '<a href="javascript:void(0);" class="pp-checklists-remove-custom-item" data-id="%1$s" title="%2$s" data-type="openai"><span class="dashicons dashicons-no" data-id="%1$s" data-type="openai"></span></a>',
            esc_attr($this->name),
            __('Remove', 'publishpress-checklists')
        );

        return $html;
    }

    /**
     * Add the requirement to the list to be displayed in the meta box.
     *
     * @param array $requirements
     * @param stdClass $post
     *
     * @return array
     */
    public function filter_requirements_list($requirements, $post)
    {
        // Check if the OpenAI API key is set
        $legacyPlugin = Factory::getLegacyPlugin();
        $api_key     = !empty($legacyPlugin->settings->module->options->openai_api_key) ? $legacyPlugin->settings->module->options->openai_api_key : '';
        
        if (empty(trim($api_key))) {
            return $requirements;
        }    

        // Check if it is a compatible post type. If not, ignore this requirement.
        if ($post->post_type !== $this->post_type) {
            return $requirements;
        }

        // Rule
        $rule = $this->get_option_rule();

        // Enabled
        $enabled = $this->is_enabled();

        // Register in the requirements list
        if ($enabled) {
            $requirements[$this->name] = [
                'status'    => $this->get_current_status($post, $enabled),
                'label'     => $this->get_title(),
                'value'     => $enabled,
                'rule'      => $rule,
                'id'        => $this->name,
                'is_custom' => $this->is_custom,
                'require_button' => $this->require_button,
                'type'      => $this->type,
                'source'    => 'openai',
            ];
        }

        return $requirements;
    }

    /**
     * Returns the current status of the requirement.
     *
     * @param stdClass $post
     * @param mixed $option_value
     *
     * @return mixed
     */
    public function get_current_status($post, $option_value)
    {
        if (!($post instanceof WP_Post)) {
            $post = get_post($post);
        }

        return self::VALUE_YES === get_post_meta($post->ID, PPCH_Checklists::POST_META_PREFIX . $this->name, true);
    }

    /**
     * Validates the option group, making sure the values are sanitized.
     *
     * @param array $new_options
     *
     * @return array
     */
    public function filter_settings_validate($new_options)
    {
        // Make sure to remove the options that were cleaned up
        if (isset($new_options[$this->name . '_title'][$this->post_type])
            && empty($new_options[$this->name . '_title'][$this->post_type])) {
            // Look for empty title
            $index = array_search($this->name, $new_options['openai_items']);
            if (false !== $index) {
                unset(
                    $new_options[$this->name . '_title'][$this->post_type],
                    $new_options[$this->name . '_rule'][$this->post_type],
                    $new_options['openai_items'][$index]
                );
            }
        }

        // Check if we need to remove items
        if (isset($new_options['openai_items_remove'])
            && !empty($new_options['openai_items_remove'])) {
            foreach ($new_options['openai_items_remove'] as $id) {
                $var_name = $id . '_title';
                unset($new_options[$var_name]);

                $var_name = $id . '_rule';
                unset($new_options[$var_name]);

                unset($new_options[$id]);

                if ($new_options['openai_items'] && !empty($new_options['openai_items'])) {
                    $index_remove = array_search($id, $new_options['openai_items']);
                    if (false !== $index_remove) {
                        unset($new_options['openai_items'][$index_remove]);
                    }
                }
            }
        }

        unset($new_options['openai_items_remove']);

        return $new_options;
    }

    /**
     * Generates an <option> element.
     *
     * @param string $value The option's value.
     * @param string $label The option's label.
     * @param string $selected HTML selected attribute for an option.
     *
     * @return string The generated <option> element.
     */
    protected function generate_option($value, $label, $selected = '')
    {
        return '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
    }

    public function handle_ajax_request() {
        
        $legacyPlugin = Factory::getLegacyPlugin();
        
        $response = [
            'yes_no'  => '',
            'content' => __('An error occured.', 'publishpress-checklists')
        ];

        if (
            empty($_POST['nonce'])
            || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'pp-checklists-requirements')
        ) {
            $response['content'] = esc_html__(
                'Validation error. Kindly reload this page and try again.',
                'publishpress-checklists'
            );
        } elseif (empty(trim(sanitize_text_field($_POST['content'])))) {
            $response['content'] = esc_html__(
                'Post content is empty.',
                'publishpress-checklists'
            );
        } else {
            $content     = $this->clean_up_content(wp_kses_post($_POST['content']));
            $requirement = map_deep($_POST['requirement'], 'sanitize_text_field');
            $api_key     = !empty($legacyPlugin->settings->module->options->openai_api_key) ? $legacyPlugin->settings->module->options->openai_api_key : '';
            if (empty(trim($api_key))) {
                $response['content'] = esc_html__(
                    'OpenAI tasks require an API Key. Please add your API Key in the Settings area.',
                    'publishpress-checklists'
                );
            } else {
                // configure prompt
                $prompt = "
                You are a content analyzer. Your task is to analyze the following content based on the given prompt.
                You must start your response with either 'No:' or 'Yes:' followed by your explanation.
                Do not use any other format for the yes/no response.

                Prompt: {$requirement['label']}

                Content: {$content}

                Remember: Start your response with either 'Yes:' or 'No:' followed by your explanation.
                ";

                // prepare body data
                $body_data = [
                    'model'         => self::OPEN_AI_MODEL,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature'   => 0.9,
                    'max_tokens'    => 500,
                ];
                
                // add header
                $headers = [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ];
            
                // send request and get response
                $http_response = wp_remote_post(self::OPEN_AI_API_URL, array(
                    'timeout' => 60,
                    'headers' => $headers,
                    'body' => wp_json_encode($body_data),
                ));

                // Check for errors
                if (!$http_response || is_wp_error($http_response) || $http_response == null) {
                    $response['content'] = $http_response->get_error_message();
                } else {
                    $status_code = wp_remote_retrieve_response_code($http_response);
                    $body_data = json_decode(wp_remote_retrieve_body($http_response), true);

                    if ($status_code !== 200) {
                        // status code is not 200
                        $error_message = (is_array($body_data) && !empty($body_data['error']['message'])) ? $body_data['error']['message'] : $status_code;
                        
                        $response['content'] = sprintf(esc_html__('API Error: %1s.', 'publishpress-checklists'), $error_message);
                    } elseif (isset($body_data['choices'][0]['message']['content'])) {
                        // Extract the response content
                        $api_content = $body_data['choices'][0]['message']['content'];

                        // Extract Yes/No response
                        $yes_no_response = '';
                        
                        if (preg_match('/^(Yes|No):/i', $api_content, $matches)) {
                            if (isset($matches[1])) {
                                $yes_no_response = strtolower(trim($matches[1]));
                            }
                        }

                        if (in_array($yes_no_response, ['yes', 'no'])) {
                            $response['yes_no'] = $yes_no_response;
                            $response_content = '<div class="ppch-yes-no-response">';
                            $response_content .= ucfirst($yes_no_response);
                            $response_content .= '. <a href="#" onclick="event.preventDefault(); var message = this.closest(\'.ppch-message\'); var fullResponse = message.querySelector(\'.ppch-full-response\'); var yesNoResponse = message.querySelector(\'.ppch-yes-no-response\'); if (fullResponse && yesNoResponse) { fullResponse.style.display = \'block\'; yesNoResponse.remove(); }">'. esc_html__('See the full response.', 'publishpress-checklists') .'</a>';

                            $response_content .= '</div>';
                            $response_content .= '<div style="display: none;" class="ppch-full-response">';
                            $response_content .= trim(str_ireplace(
                                [
                                    'Yes/No: Yes',
                                    'Yes/No: No',
                                    'Full Response:'
                                ],
                                '',
                                stripslashes($api_content)
                            ));
                            $response_content .= '</div>';
                        } else {
                            $response_content = $api_content;
                        }

                        $response['content'] = $response_content;

                    } else {
                        $response['content'] = esc_html__('Invalid response', 'publishpress-checklists');
                    }
                }
            }
        }
    
        wp_send_json($response);
        exit;
    }

    /**
     * Clean up post content for API request.
     *
     * @param string $post_content The content of the post.
     * @param string $post_title The title of the post.
     * 
     * @return string The cleaned-up content.
     */
    public function clean_up_content($post_content = '', $post_title = '') {

        // Return empty string if both post content and title are empty
        if (empty($post_content) && empty($post_title)) {
            return '';
        }
    
        // Apply content and title filters if provided
        if (!empty($post_content)) {
            $post_content = apply_filters('the_content', $post_content);
        
            /* Remove HTML entities */
            $post_content = preg_replace( '/&#?[a-z0-9]{2,8};/i', '', $post_content );
    
            /*  Remove abbreviations */
            $post_content = preg_replace( '/[A-Z][A-Z]+/', '', $post_content );
    
            /* Replace HTML line breaks with newlines */
            $post_content = preg_replace( '#<br\s?/?>#', "\n\n", $post_content );
        
            // Strip all remaining HTML tags
            $post_content = wp_strip_all_tags( $post_content );
        }
        if (!empty($post_title)) {
            $post_title = apply_filters('the_title', $post_title);
        }
    
        // Initialize the cleaned-up content variable
        $cleaned_up_content = '';
    
        // Combine post title and content if both are available
        if (!empty($post_content) && !empty($post_title)) {
            $cleaned_up_content = $post_title . ".\n\n" . $post_content;
        } 
        // Use post content if title is empty
        elseif (!empty($post_content)) {
            $cleaned_up_content = $post_content;
        } 
        // Use post title if content is empty
        elseif (!empty($post_title)) {
            $cleaned_up_content = $post_title;
        }
    
        // Return the cleaned-up content
        return $cleaned_up_content;
    }
}
