<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       2.30.0
 */

namespace PublishPress\Checklists\Core\Utils;

/**
 * Registers the post meta that stores the state of tasks the user ticks by hand
 * (custom tasks, OpenAI tasks, "Approved by") so the block editor can save it
 * through the REST API.
 *
 * The block editor has no Checklist meta box (a classic meta box turns off
 * visual revisions and real-time collaboration, see #1208), so these states are
 * edited as post meta with editPost(). Real-time collaboration syncs registered
 * post meta between editors. The Classic Editor still saves the same meta keys
 * through the meta box form.
 *
 * This runs on every request, not only in wp-admin, because block editor saves
 * are REST requests.
 */
class CustomItemsMeta
{
    /**
     * Same prefix as PPCH_Checklists::POST_META_PREFIX.
     */
    const META_PREFIX = 'pp_checklist_custom_item_';

    const OPTION_NAME = 'publishpress_checklists_checklists_options';

    /**
     * Ticked-by-hand tasks with a fixed name, saved under META_PREFIX . name.
     * approved_by_user comes from Checklists Pro.
     */
    const APPROVAL_ITEMS = ['approved_by', 'approved_by_user'];

    /**
     * @var array|null
     */
    private static $itemsByPostType = null;

    public static function init()
    {
        add_action('init', [__CLASS__, 'register'], 100);
        add_action('update_option_' . self::OPTION_NAME, [__CLASS__, 'flushCache']);
    }

    public static function flushCache()
    {
        self::$itemsByPostType = null;
    }

    /**
     * Returns the meta key for a requirement id.
     *
     * @param string $id
     *
     * @return string
     */
    public static function getMetaKey($id)
    {
        return self::META_PREFIX . sanitize_key($id);
    }

    /**
     * Returns the ticked-by-hand task ids configured for each post type.
     *
     * @return array post type => list of requirement ids
     */
    public static function getItemsByPostType()
    {
        if (null !== self::$itemsByPostType) {
            return self::$itemsByPostType;
        }

        $options = get_option(self::OPTION_NAME);
        $options = is_object($options) ? $options : (object)(is_array($options) ? $options : []);
        $items   = [];

        $addItem = function ($postType, $id) use (&$items) {
            $postType = (string)$postType;
            $id       = sanitize_key((string)$id);

            if ('' === $postType || '' === $id) {
                return;
            }

            if (!isset($items[$postType])) {
                $items[$postType] = [];
            }

            if (!in_array($id, $items[$postType], true)) {
                $items[$postType][] = $id;
            }
        };

        // Custom and OpenAI tasks exist for the post types that have a title for them.
        foreach (['custom_items', 'openai_items'] as $listName) {
            if (empty($options->{$listName}) || !is_array($options->{$listName})) {
                continue;
            }

            foreach ($options->{$listName} as $id) {
                $titles = isset($options->{trim((string)$id) . '_title'}) ? $options->{trim((string)$id) . '_title'} : [];

                foreach (array_keys((array)$titles) as $postType) {
                    $addItem($postType, trim((string)$id));
                }
            }
        }

        foreach (self::APPROVAL_ITEMS as $id) {
            $rules = isset($options->{$id . '_rule'}) ? (array)$options->{$id . '_rule'} : [];

            foreach ($rules as $postType => $rule) {
                if (!empty($rule) && 'off' !== $rule) {
                    $addItem($postType, $id);
                }
            }
        }

        /**
         * Filters the ticked-by-hand checklist tasks whose state is saved as
         * post meta (META_PREFIX . id) and exposed to the REST API.
         *
         * @param array $items post type => list of requirement ids
         */
        $items = apply_filters('publishpress_checklists_custom_item_meta_ids', $items);

        self::$itemsByPostType = is_array($items) ? $items : [];

        return self::$itemsByPostType;
    }

    /**
     * Returns whether a requirement id is saved as REST post meta for the post type.
     *
     * @param string $postType
     * @param string $id
     *
     * @return bool
     */
    public static function isRegisteredItem($postType, $id)
    {
        $items = self::getItemsByPostType();

        return isset($items[$postType]) && in_array(sanitize_key($id), $items[$postType], true);
    }

    public static function register()
    {
        if (!function_exists('register_post_meta')) {
            return;
        }

        foreach (self::getItemsByPostType() as $postType => $ids) {
            if (empty($ids)) {
                continue;
            }

            /*
             * The REST API only returns post meta for post types that support
             * custom-fields. Posts and pages do; add it for other post types
             * that have these tasks so the block editor can save them.
             */
            if (
                post_type_exists($postType)
                && !post_type_supports($postType, 'custom-fields')
                && apply_filters('publishpress_checklists_add_custom_fields_support', true, $postType)
            ) {
                add_post_type_support($postType, 'custom-fields');
            }

            foreach ($ids as $id) {
                register_post_meta(
                    $postType,
                    self::getMetaKey($id),
                    [
                        'type'              => 'string',
                        'single'            => true,
                        'show_in_rest'      => true,
                        'sanitize_callback' => [__CLASS__, 'sanitizeValue'],
                        'auth_callback'     => [__CLASS__, 'canEdit'],
                    ]
                );
            }
        }
    }

    /**
     * @param mixed $value
     *
     * @return string 'yes', 'no' or ''
     */
    public static function sanitizeValue($value)
    {
        $value = sanitize_text_field((string)$value);

        return in_array($value, ['yes', 'no'], true) ? $value : '';
    }

    /**
     * Same check as the Classic Editor meta box save: the user must be able to edit the post.
     *
     * @return bool
     */
    public static function canEdit($allowed, $metaKey, $postId)
    {
        return current_user_can('edit_post', $postId);
    }
}
