<?php

namespace PublishPress\Checklists\Core\Requirement;

use PublishPress\Checklists\Core\Requirement\Base_simple;
use PublishPress\Checklists\Core\Requirement\Interface_required;

defined('ABSPATH') or die('No direct script access allowed.');

/**
 * Generic runner for Pro requirements defined in config.
 */
class Pro_Requirement extends Base_simple implements Interface_required
{
    /**
     * @var array Config row for this requirement
     */
    protected $config;

    /**
     * Requirement identifier.
     *
     * @var string
     */
    public $name;

    /**
     * Group/tab key for this requirement.
     *
     * @var string
     */
    public $group;

    /**
     * Mark as Pro requirement.
     *
     * @var bool
     */
    public $pro = true;

    /**
     * @param object $module
     * @param string $post_type
     * @param array  $config     Optional config row
     */
    public function __construct($module, $post_type, array $config = [])
    {
        parent::__construct($module, $post_type);
        $this->config = $config;

        // Set requirement properties from config
        $this->name = $config['id'] ?? '';
        if (isset($config['group'])) {
            $this->group = $config['group'];
        } else {
            // Map WP support to tab group key
            $map = [
                'editor'    => 'content',
                'title'     => 'title',
                'thumbnail' => 'featured_image',
                'excerpt'   => 'excerpt',
            ];
            $support = $config['support'] ?? '';
            $this->group = $map[$support] ?? $support;
        }
    }

    public function get_label(): string
    {
        return $this->config['label'];
    }

    public function get_current_status($post, $option)
    {
        if (!($post instanceof WP_Post)) {
            $post = get_post($post);
        }
        
        $html = $post->post_content;
        switch ($this->config['type']) {
            case 'simple':
                return (bool) $option === Base_requirement::VALUE_YES;
            case 'counter':
                $count = substr_count($html, '<img');
                return $count >= (int) $this->config['min'];
            case 'time':
                $val = get_post_meta($post->ID, $this->config['field_key'], true);
                return ! empty($val);
        }
        return true;
    }

    public function get_setting_field_html($css_class = '')
    {
        if ($this->config['type'] === 'time') {
            $value = get_post_meta(get_the_ID(), $this->config['field_key'], true);
            return sprintf(
                '<input type="time" name="%s" value="%s" class="%s" aria-label="%s" />',
                esc_attr($this->config['field_key']),
                esc_attr($value),
                esc_attr($css_class),
                esc_attr(sprintf(__('Set time for %s', 'publishpress-checklists'), $this->get_label()))
            );
        }
        if ($this->config['type'] === 'counter') {
            // Inline counter fields HTML
            $min    = $this->config['min'] ?? '';
            $max    = $this->config['max'] ?? '';
            $id_key = esc_attr($this->config['id']);
            return sprintf(
                '<div class="pp-checklists-number"><label for="%6$s-min">%1$s</label><input type="number" id="%6$s-min" name="min" value="%3$s" class="%4$s pp-checklists-small-input pp-checklists-number" /></div>' .
                '<div class="pp-checklists-number"><label for="%6$s-max">%2$s</label><input type="number" id="%6$s-max" name="max" value="%5$s" class="%4$s pp-checklists-small-input pp-checklists-number" /></div>',
                esc_html__('Min', 'publishpress-checklists'),
                esc_html__('Max', 'publishpress-checklists'),
                esc_attr($min),
                esc_attr($css_class),
                esc_attr($max),
                $id_key
            );
        }
        if ($this->config['type'] === 'multiple') {
            return sprintf(
                '<select class="%s" multiple="multiple" aria-label="%s"></select>',
                esc_attr($css_class),
                esc_attr(sprintf(__('Select options for %s', 'publishpress-checklists'), $this->get_label()))
            );
        }
        return parent::get_setting_field_html($css_class);
    }

    /**
     * Set config parameters after instantiation.
     * Called by loader when using serialized params.
     *
     * @param array $config
     */
    public function set_params(array $config)
    {
        $this->config = $config;
        // Update name, group, and pro flag based on config
        $this->name = $config['id'] ?? '';
        if (isset($config['group'])) {
            $this->group = $config['group'];
        } else {
            $map = [
                'editor'    => 'content',
                'title'     => 'title',
                'thumbnail' => 'featured_image',
                'excerpt'   => 'excerpt',
            ];
            $support = $config['support'] ?? '';
            $this->group = $map[$support] ?? $support;
        }
        $this->pro = true;
    }

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $label = $this->config['label'] ?? '';
        $this->lang['label'] = $label;
        $this->lang['label_settings'] = $label;
    }

    public static function get_support_for_config(array $config): string
    {
        return $config['support'];
    }
}
