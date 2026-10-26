<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Utils;


\defined('ABSPATH') || exit;

class HyperlinkExtractor
{
    /**
     * @param $text
     *
     * @return array
     */
    public function extractLinksFromHyperlinksInText($text)
    {
        $links = [];

        preg_match_all('/(?:<a[^>]+href=[\'"])([^\'"]+)(?:[\'"][^>]*>)/', $text, $links);

        return $links[1];
    }
}
