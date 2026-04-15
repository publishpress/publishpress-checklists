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
        $link = trim((string)$link);

        if ($link === '') {
            return false;
        }

        if ($this->isValidAnchorLink($link)) {
            return true;
        }

        $linkWithoutFragment = $this->removeFragment($link);

        if ($this->isValidHttpLink($linkWithoutFragment)) {
            return true;
        }

        if ($this->isValidRelativeLink($linkWithoutFragment)) {
            return true;
        }

        if ($this->isValidTelephoneLink($linkWithoutFragment)) {
            return true;
        }

        if ($this->isValidMailtoLink($linkWithoutFragment)) {
            return true;
        }

        return false;
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
        return (bool)preg_match('/^#[-a-zA-Z0-9@:%._\+~#=]{1,256}$/', $link);
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

        return filter_var($link, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param string $link
     *
     * @return bool
     */
    private function isValidRelativeLink($link)
    {
        return (bool)preg_match('/^(?:\/|\.\.?\/|\?)[^\s]*$/', $link);
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

        $addressAndQuery = substr($link, 7);
        $parts = explode('?', $addressAndQuery, 2);
        $address = $parts[0];

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        if (!isset($parts[1]) || $parts[1] === '') {
            return true;
        }

        return (bool)preg_match('/^[^?\s]+$/', $parts[1]);
    }
}
