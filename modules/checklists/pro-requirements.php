<?php
/**
 * Static Pro checklist rules.
 *
 * This array defines the built-in Pro requirements, each entry containing:
 *   - id: unique identifier
 *   - type: requirement type (simple, counter, multiple, time)
 *   - support: WP post_type_supports key
 *   - group: requirement group/tab
 *   - label: displayed text in settings
 *   - post_type: post type need to be supported
 *   - optional params: min, max, post_type, field_key
 *
 * @since 1.0.0
 */
$static = [
    [
        'id'       => 'all_in_one_seo_headline_score',
        'type'     => 'counter',
        'support'  => 'editor',
        'group'    => 'all_in_one_seo',
        'label'    => __('All in One SEO Headline Score', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'all_in_one_seo_score',
        'type'     => 'counter',
        'support'  => 'editor',
        'group'    => 'all_in_one_seo',
        'label'    => __('All in One SEO Score', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'featured_image_height',
        'type'     => 'counter',
        'support'  => 'editor',
        'group'    => 'featured_image',
        'label'    => __('Featured image height', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'featured_image_width',
        'type'     => 'counter',
        'support'  => 'editor',
        'group'    => 'featured_image',
        'label'    => __('Featured image width', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'heading_in_hierarchy',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'accessibility',
        'label'    => __('Heading in hierarchy', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'image_count',
        'type'     => 'counter',
        'support'  => 'editor',
        'group'    => 'images',
        'label'    => __('Number of images in content', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'image_caption_count',
        'type'     => 'counter',
        'support'  => 'editor',
        'group'    => 'images',
        'label'    => __('Number of characters in image captions', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'audio_count',
        'type'     => 'counter',
        'support'  => 'editor',
        'group'    => 'audio_video',
        'label'    => __('Number of audio in content', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'video_count',
        'type'     => 'counter',
        'support'  => 'editor',
        'group'    => 'audio_video',
        'label'    => __('Number of video in content', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'approved_by_role',
        'type'     => 'multiple',
        'support'  => 'editor',
        'group'    => 'approval',
        'label'    => __('Approved by role', 'publishpress-checklists'),
    ],
    [
        'id'       => 'approved_by_user',
        'type'     => 'multiple',
        'support'  => 'editor',
        'group'    => 'approval',
        'label'    => __('Approved by user', 'publishpress-checklists'),
    ],
    [
        'id'       => 'no_heading_tags',
        'type'     => 'multiple',
        'support'  => 'editor',
        'group'    => 'content',
        'label'    => __('Avoid heading tags in content', 'publishpress-checklists'),
    ],
    [
        'id'        => 'publish_time_exact',
        'type'      => 'time',
        'support'   => 'editor',
        'group'     => 'publish_date_time',
        'label'     => __('Published at exact time', 'publishpress-checklists'),
        'field_key' => '_publish_time_exact',
    ],
    [
        'id'        => 'publish_time_future',
        'type'      => 'time',
        'support'   => 'editor',
        'group'     => 'publish_date_time',
        'label'    => __('Publish time should be in the future', 'publishpress-checklists'),
        'field_key' => '_publish_time_future'
    ],
    [
        'id'       => 'rank_math_score',
        'type'     => 'counter',
        'support'  => 'editor',
        'group'    => 'rank_math',
        'label'    => __('Rank Math SEO Score', 'publishpress-checklists'),
        'min'      => '',
        'max'      => '',
    ],
    [
        'id'       => 'single_h1_per_page',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'accessibility',
        'label'    => __('Only one H1 tag in content', 'publishpress-checklists'),
    ],
    [
        'id'       => 'table_header',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'accessibility',
        'label'    => __('Tables have a header row', 'publishpress-checklists'),
    ],
    [
        'id'       => 'backorder',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Check the "Allow backorders?" box', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'crosssell',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Select some products for "Cross-sells"', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'discount',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Discount for the "Sale price"', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'downloadable',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Check the "Downloadable" box', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'image',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Product image', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'manage_stock',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Check the "Manage stock?" box', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'regular_price',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Enter a "Regular price"', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'sale_price',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Enter a "Sale price"', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'sale_price_scheduled',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Schedule the "Sale price"', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'sku',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Enter a "SKU"', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'sold_individually',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Check the "Sold individually" box', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'upsell',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Select some products for "Upsells"', 'publishpress-checklists'),
        'post_type' => 'product',
    ],
    [
        'id'       => 'virtual',
        'type'     => 'simple',
        'support'  => 'editor',
        'group'    => 'woocommerce',
        'label'    => __('Check the "Virtual" box', 'publishpress-checklists'),
        'post_type' => 'product',
    ]
    
];

/**
 * Dynamically generate Pro requirement entries for each ACF field.
 *
 * - For 'text' and 'textarea' fields: uses 'counter' type to count characters.
 * - For other field types: uses 'simple' type to check if the field is filled.
 *
 * @since 1.0.0
 * @link https://www.advancedcustomfields.com/resources/acf_get_field_groups/ ACF Field Groups API
 */
$acf = [];
if ( function_exists('acf_get_field_groups') ) {
    $groups = acf_get_field_groups();
    foreach ( $groups as $group ) {
        $fields = acf_get_fields( $group );
        if ( empty( $fields ) ) {
            continue;
        }
        foreach ( $fields as $f ) {
            if ( in_array( $f['type'], ['text', 'textarea'] ) ) {
                $acf[] = [
                    'id'        => 'acf_' . $f['key'],
                    'type'      => 'counter',
                    'support'   => 'editor',
                    'group'     => 'advanced-custom-fields',
                    'label'     => sprintf(
                        __('Number of Characters in %s field', 'publishpress-checklists'),
                        $f['label']
                    ),
                    'name'      => $f['name'],
                    'field_key' => $f['key'],
                ];
            } else {
                $acf[] = [
                    'id'        => 'acf_' . $f['key'],
                    'type'      => 'simple',
                    'support'   => 'editor',
                    'group'     => 'advanced-custom-fields',
                    'label'     => sprintf(
                        __('%s is filled', 'publishpress-checklists'),
                        $f['label']
                    ),
                    'name'      => $f['name'],
                    'field_key' => $f['key'],
                ];
            }
        }
    }
}
return array_merge( $static, $acf );
