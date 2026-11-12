<?php

/**
 * Accessible help tooltip for the Checklists settings screen.
 *
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2026 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       2.31.0
 */

namespace PublishPress\Checklists\Core\Utils;

\defined('ABSPATH') || exit;

/**
 * Renders an info button that shows long help text in a tooltip, so the
 * requirement rows on the Checklists screen keep a compact height.
 *
 * The tooltip text stays in the page and is referenced with aria-describedby,
 * from both the button and the field it describes, so screen readers still
 * announce it. modules/checklists/assets/js/help-tip.js shows it on hover and
 * focus, keeps it open while the pointer is over it and closes it with Escape
 * (WCAG 2.2, 1.4.13 Content on Hover or Focus).
 *
 * @since 2.31.0
 */
class HelpTip
{
    /**
     * Returns the id of the tooltip that describes a field.
     *
     * Use it for the field's aria-describedby attribute.
     *
     * @param string $fieldId The id of the field the help text describes.
     *
     * @return string
     */
    public static function getId($fieldId)
    {
        return sanitize_html_class($fieldId) . '-help';
    }

    /**
     * Returns the tooltip markup.
     *
     * @param string $text    Plain-text help. It is escaped here.
     * @param string $fieldId The id of the field the help text describes.
     * @param string $label   Accessible name of the info button. Defaults to "More information".
     *
     * @return string
     */
    public static function render($text, $fieldId, $label = '')
    {
        $text = trim((string)$text);

        if ('' === $text) {
            return '';
        }

        $tipId = self::getId($fieldId);
        $label = '' !== $label ? $label : __('More information', 'publishpress-checklists');

        return sprintf(
            '<span class="ppch-help-tip">'
            . '<button type="button" class="ppch-help-tip__button" aria-label="%1$s" aria-describedby="%2$s">'
            . '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>'
            . '</button>'
            . '<span class="ppch-help-tip__bubble" role="tooltip" id="%2$s" hidden>%3$s</span>'
            . '</span>',
            esc_attr($label),
            esc_attr($tipId),
            esc_html($text)
        );
    }
}
