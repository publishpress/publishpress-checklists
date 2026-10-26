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
 * Class to map frontend labels to backend rules
 */
class LabelMapper
{
    /**
     * Initialize the label mapper
     */
    public static function init()
    {
        // Add a filter to map the frontend labels to backend rules
        add_filter('publishpress_checklists_rules_list', [__CLASS__, 'map_rules_labels']);
    }
    
    /**
     * Map the frontend labels to backend rules
     *
     * @param array $rules
     * @return array
     */
    public static function map_rules_labels($rules)
    {
        // Map the backend rules to frontend labels
        $rules[Plugin::RULE_DISABLED] = __('Disabled', 'publishpress-checklists');
        $rules[Plugin::RULE_WARNING] = __('Recommended', 'publishpress-checklists');
        $rules[Plugin::RULE_BLOCK] = __('Required', 'publishpress-checklists');
        
        return $rules;
    }
}
