<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Requirement;

use PublishPress\Checklists\Core\Utils\HyperlinkExtractor;
use PublishPress\Checklists\Core\Utils\HyperlinkValidator;

\defined('ABSPATH') || exit;


class Validate_links extends Base_simple
{

    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'validate_links';

     /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'links';

    /**
     * @var int
     */
    public $position = 120;

    /**
     * @var HyperlinkExtractor
     */
    private $hyperlinkExtractor;

    /**
     * @var HyperlinkValidator
     */
    private $hyperlinkValidator;

    public function __construct($module, $post_type)
    {
        parent::__construct($module, $post_type);

        $this->hyperlinkExtractor = new HyperlinkExtractor();
        $this->hyperlinkValidator = new HyperlinkValidator();
    }

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']          = __('All links use a valid format', 'publishpress-checklists');
        $this->lang['label_settings'] = __('All links use a valid format', 'publishpress-checklists');
    }

    /**
     * Check for invalid links in a text.
     *
     * @param string $content
     *
     * @return bool
     * @since  1.0.1
     */
    private function has_no_invalid_links($content)
    {
        if (empty($content)) {
            return true;
        }

        $links = $this->hyperlinkExtractor->extractLinksFromHyperlinksInText($content);

        if (empty($links)) {
            return true;
        }

        foreach ($links as $link) {
            if (!$this->hyperlinkValidator->isValidLink($link)) {
                return false;
            }
        }

        return true;
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
        $post_content = isset($post->post_content) ? $post->post_content : '';
        return $this->has_no_invalid_links($post_content);
    }
}
