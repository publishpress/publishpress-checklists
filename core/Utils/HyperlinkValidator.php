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
        if (!is_string($link)) {
            return false;
        }

        $link = trim($link);

        if ($link === '') {
            return false;
        }

        if ($this->isValidAnchorLink($link)) {
            return true;
        }

        // tel: and mailto: are opaque: a "#" is part of the value, not a fragment.
        if ($this->isValidTelephoneLink($link)) {
            return true;
        }

        if ($this->isValidMailtoLink($link)) {
            return true;
        }

        $linkWithoutFragment = $this->removeFragment($link);

        if ($this->isValidHttpLink($linkWithoutFragment)) {
            return true;
        }

        return $this->isValidRelativeLink($linkWithoutFragment);
    }

    /**
     * @param string $link
     *
     * @return string
     */
    private function removeFragment($link)
    {
        if ($link === '' || $link[0] === '#') {
            return $link;
        }

        $fragmentPosition = strpos($link, '#');

        if ($fragmentPosition === false) {
            return $link;
        }

        return substr($link, 0, $fragmentPosition);
    }

    /**
     * @param string $link
     *
     * @return bool
     */
    private function isValidAnchorLink($link)
    {
        return (bool)preg_match('/^#[-a-zA-Z0-9@:%._\+~=]{1,256}$/', $link);
    }

    /**
     * @param string $link
     *
     * @return bool
     */
    private function isValidHttpLink($link)
    {
        if (strpos($link, '//') === 0) {
            $link = 'https:' . $link;
        }

        if (!preg_match('/^https?:\/\//i', $link)) {
            return false;
        }

        if (filter_var($link, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // FILTER_VALIDATE_URL accepts dotless hosts such as "http://invalidlinkcom".
        $host = parse_url($link, PHP_URL_HOST);

        if (empty($host)) {
            return false;
        }

        return (bool)preg_match('/\.[a-z0-9-]{2,63}$/i', $host);
    }

    /**
     * @param string $link
     *
     * @return bool
     */
    private function isValidRelativeLink($link)
    {
        if (preg_match('/^\?[^\s]+$/', $link)) {
            return true;
        }

        if (!preg_match('/^(?:\.\.?)?\/[^\s]*$/', $link)) {
            return false;
        }

        // Reject paths made only of separators, such as "/", "//" or "../".
        return trim($link, './') !== '';
    }

    /**
     * @param string $link
     *
     * @return bool
     */
    private function isValidTelephoneLink($link)
    {
        return (bool)preg_match('/^tel:\+?[0-9\-]+$/i', $link);
    }

    /**
     * @param string $link
     *
     * @return bool
     */
    private function isValidMailtoLink($link)
    {
        if (stripos($link, 'mailto:') !== 0) {
            return false;
        }

        $addressAndQuery = substr($link, strlen('mailto:'));
        $parts = explode('?', $addressAndQuery, 2);

        // Only the address is validated. Query values such as "subject" or "body"
        // are free text and may legitimately contain spaces and commas.
        return filter_var($parts[0], FILTER_VALIDATE_EMAIL) !== false;
    }
}
