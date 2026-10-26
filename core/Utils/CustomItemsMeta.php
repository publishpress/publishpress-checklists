<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       2.30.0
 */

namespace PublishPress\Checklists\Core\Utils;

\defined('ABSPATH') || exit;

/**
 * Saves the state of tasks the user ticks by hand (custom tasks, OpenAI tasks,
 * "Approved by") in the block editor.
 *
 * The block editor has no Checklist meta box (a classic meta box turns off
 * visual revisions and real-time collaboration, see #1208). The Classic Editor
 * still saves the same meta keys (META_PREFIX . id) through the meta box form.
 *
 * In the block editor there are two ways to save a task:
 * - Tasks anyone who can edit the post may tick are registered as REST post
 *   meta and edited with editPost(). They are saved with the post, and
 *   real-time collaboration shares them between editors.
 * - Restricted tasks ("Approved by", Pro's "Approved by user", custom tasks
 *   limited to some roles) are not REST meta. Real-time collaboration shares
 *   registered meta between everyone who can edit the post, so an author
 *   could put an approval into the shared state and let an editor's save
 *   store it. These tasks are saved by the user who clicks them, through
 *   admin-ajax, which checks that this user may tick them. Other editors get
 *   the saved state through the Heartbeat API. Tasks of post types that don't
 *   expose post meta to the REST API are saved the same way.
 *
 * This runs on every request, not only in wp-admin, because block editor saves
 * are REST requests.
 */
class CustomItemsMeta
{
    /**
     * Same prefix as PPCH_Checklists::POST_META_PREFIX.
     */
    public const META_PREFIX = 'pp_checklist_custom_item_';

    public const OPTION_NAME = 'publishpress_checklists_checklists_options';

    /**
     * Ticked-by-hand tasks with a fixed name, saved under META_PREFIX . name.
     * approved_by_user comes from Checklists Pro and is only registered when Pro is loaded.
     */
    public const APPROVAL_ITEMS = ['approved_by', 'approved_by_user'];

    /**
     * @var array|null
     */
    private static $itemsByPostType = null;

    public static function init()
    {
        add_action('init', [__CLASS__, 'register'], 100);
        add_action('update_option_' . self::OPTION_NAME, [__CLASS__, 'flushCache']);

        foreach (['add', 'update', 'delete'] as $action) {
            add_filter($action . '_post_metadata', [__CLASS__, 'filterRestWrite'], 10, 3);
        }
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

        $options = self::getOptions();
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
            // Without Pro, "Approved by user" is only a placeholder that can't be ticked.
            if ('approved_by_user' === $id && !defined('PPCHPRO_VERSION')) {
                continue;
            }

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
     * @return object The Checklists options.
     */
    private static function getOptions()
    {
        $options = get_option(self::OPTION_NAME);

        return is_object($options) ? $options : (object)(is_array($options) ? $options : []);
    }

    /**
     * Returns whether a user may tick a task, with the same rules the
     * requirements use to make the task clickable: the roles of "Approved by",
     * the users of Pro's "Approved by user" and the roles allowed to mark a
     * custom task. Other tasks can be ticked by anyone who can edit the post.
     *
     * @param string       $postType
     * @param string       $id   Requirement id
     * @param WP_User|null $user Defaults to the current user.
     *
     * @return bool
     */
    public static function userCanComplete($postType, $id, $user = null)
    {
        $user    = $user instanceof \WP_User ? $user : wp_get_current_user();
        $id      = sanitize_key((string)$id);
        $options = self::getOptions();

        if ('approved_by_user' === $id) {
            $optionName = 'approved_by_user_users';
        } elseif ('approved_by' === $id) {
            $optionName = 'approved_by_roles';
        } else {
            $optionName = $id . '_editable_by';
        }

        $allowed = isset($options->{$optionName}) ? (array)$options->{$optionName} : [];

        if (!isset($allowed[$postType])) {
            return true;
        }

        if ('approved_by_user' === $id) {
            return in_array($user->ID, (array)$allowed[$postType]);
        }

        return (bool)array_intersect((array)$allowed[$postType], (array)$user->roles);
    }

    /**
     * Keeps the stored state of a task when a REST request (the block editor
     * saves through the REST API) comes from a user who may not tick it.
     *
     * The block editor sends every registered meta field with the post, so
     * refusing the request would also refuse the user's other changes. The
     * write is skipped instead.
     *
     * @param mixed  $check   Null to let WordPress write the meta.
     * @param int    $postId
     * @param string $metaKey
     *
     * @return mixed
     */
    public static function filterRestWrite($check, $postId, $metaKey)
    {
        if (
            null !== $check
            || !defined('REST_REQUEST')
            || !REST_REQUEST
            || 0 !== strpos((string)$metaKey, self::META_PREFIX)
        ) {
            return $check;
        }

        $post = get_post($postId);
        $id   = substr((string)$metaKey, strlen(self::META_PREFIX));

        if (
            !$post
            || !(self::isRegisteredItem($post->post_type, $id) || self::isRestrictedItem($post->post_type, $id))
            || self::userCanComplete($post->post_type, $id)
        ) {
            return $check;
        }

        return true;
    }

    /**
     * Returns whether a requirement id is a ticked-by-hand task configured for the post type.
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

    /**
     * Returns whether only some users may tick the task: "Approved by", Pro's
     * "Approved by user" and custom tasks limited to some roles.
     *
     * @param string $postType
     * @param string $id
     *
     * @return bool
     */
    public static function isRestrictedItem($postType, $id)
    {
        $id = sanitize_key((string)$id);

        if (in_array($id, self::APPROVAL_ITEMS, true)) {
            return true;
        }

        $options    = self::getOptions();
        $optionName = $id . '_editable_by';
        $allowed    = isset($options->{$optionName}) ? (array)$options->{$optionName} : [];

        return isset($allowed[$postType]);
    }

    /**
     * Returns whether the block editor saves the task as REST post meta. Other
     * tasks are saved through admin-ajax by the user who ticks them.
     *
     * @param string $postType
     * @param string $id
     *
     * @return bool
     */
    public static function isRestItem($postType, $id)
    {
        return self::isRegisteredItem($postType, $id)
            && !self::isRestrictedItem($postType, $id)
            && post_type_supports($postType, 'custom-fields');
    }

    /**
     * Returns the saved state of the tasks of a post that the block editor
     * saves through admin-ajax, so other editors of the post can show it.
     *
     * @param \WP_Post $post
     *
     * @return array requirement id => 'yes' or 'no'
     */
    public static function getAjaxItemStates($post)
    {
        $items  = self::getItemsByPostType();
        $states = [];

        if (empty($items[$post->post_type])) {
            return $states;
        }

        foreach ($items[$post->post_type] as $id) {
            if (!self::isRestItem($post->post_type, $id)) {
                $states[$id] = 'yes' === get_post_meta($post->ID, self::getMetaKey($id), true) ? 'yes' : 'no';
            }
        }

        return $states;
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

            // Without custom-fields the REST API doesn't return the meta, so
            // the tasks are saved through admin-ajax instead.
            if (!post_type_supports($postType, 'custom-fields')) {
                continue;
            }

            foreach ($ids as $id) {
                if (self::isRestrictedItem($postType, $id)) {
                    continue;
                }

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
