<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Utils;


class HyperlinkValidator
{
    /**
     * @param $link
     *
     * @return bool
     */
    public function isValidLink($link)
    {
        $linkWithoutFragment = explode('#', $link)[0];

        return (bool)preg_match('/^(?:(#[-a-zA-Z0-9@:%._\+~#=]{0,256})|https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9-]{2,63}\b(?:[-a-zA-Z0-9()@;:%_\+.~#?&\/\/=*]*)|tel:\+?[0-9\-]+|mailto:[a-z0-9\-_\.]+@[a-z0-9\-_\.]+?[a-z0-9@\.\?=\s\%,\-&_;*]+)$/i', $linkWithoutFragment);
    }
}
