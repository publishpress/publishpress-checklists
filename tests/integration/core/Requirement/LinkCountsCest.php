<?php
/**
 * Tests for the internal and external link count requirements.
 *
 * PHP version 7.2.5
 *
 * @category  Tests
 * @package   PublishPress\Checklists
 * @author    PublishPress <help@publishpress.com>
 * @copyright 2026 PublishPress
 * @license   GPLv2 or later
 * @link      https://publishpress.com/
 */

namespace core\Requirement;

use PublishPress\Checklists\Core\Requirement\External_links;
use PublishPress\Checklists\Core\Requirement\Internal_links;
use WpunitTester;

if (!class_exists(Internal_links::class)) {
    include_once dirname(__DIR__, 4) . '/core/Autoloader.php';

    \PublishPress\Checklists\Core\Autoloader::register();
    \PublishPress\Checklists\Core\Autoloader::addNamespace(
        'PublishPress\\Checklists\\Core\\',
        dirname(__DIR__, 4) . '/core/'
    );
}

/**
 * Verifies the PHP link counters use the same boundaries as the editor.
 *
 * @category Tests
 * @package  PublishPress\Checklists
 * @author   PublishPress <help@publishpress.com>
 * @license  GPLv2 or later
 * @link     https://publishpress.com/
 */
class LinkCountsCest
{
    /**
     * Verify a blank maximum does not fail a satisfied minimum.
     *
     * @param WpunitTester $I Integration tester.
     *
     * @return void
     */
    public function minimumOnlyRulesAcceptSavedLinks(WpunitTester $I)
    {
        $post = $this->_createPostWithTwoInternalAndExternalLinks();

        $internalLinks = new Internal_links(null, null);
        $externalLinks = new External_links(null, null);

        // Base_counter serializes a blank maximum as 0. With a positive
        // minimum, that represents a minimum-only rule.
        $I->assertTrue($internalLinks->get_current_status($post, [1, 0]));
        $I->assertTrue($externalLinks->get_current_status($post, [1, 0]));
        $I->assertFalse($internalLinks->get_current_status($post, [3, 0]));
        $I->assertFalse($externalLinks->get_current_status($post, [3, 0]));
    }

    /**
     * Verify exact, bounded, and maximum-only counter rules.
     *
     * @param WpunitTester $I Integration tester.
     *
     * @return void
     */
    public function linkCountsFollowCounterRuleBoundaries(WpunitTester $I)
    {
        $post = $this->_createPostWithTwoInternalAndExternalLinks();

        $requirements = [
            new Internal_links(null, null),
            new External_links(null, null),
        ];

        foreach ($requirements as $requirement) {
            $I->assertTrue($requirement->get_current_status($post, [2, 2]));
            $I->assertFalse($requirement->get_current_status($post, [1, 1]));
            $I->assertTrue($requirement->get_current_status($post, [1, 3]));
            $I->assertFalse($requirement->get_current_status($post, [3, 4]));
            $I->assertTrue($requirement->get_current_status($post, [0, 2]));
            $I->assertFalse($requirement->get_current_status($post, [0, 1]));
            $I->assertFalse($requirement->get_current_status($post, [0, 0]));
        }
    }

    /**
     * Verify an exact-zero rule accepts content without links.
     *
     * @param WpunitTester $I Integration tester.
     *
     * @return void
     */
    public function exactZeroRulesAcceptContentWithoutLinks(WpunitTester $I)
    {
        $post = (object) ['post_content' => '<p>No links.</p>'];
        $internalLinks = new Internal_links(null, null);
        $externalLinks = new External_links(null, null);

        $I->assertTrue($internalLinks->get_current_status($post, [0, 0]));
        $I->assertTrue($externalLinks->get_current_status($post, [0, 0]));
    }

    /**
     * Create content with two links of each type.
     *
     * @return object Post-like object containing the test content.
     */
    private function _createPostWithTwoInternalAndExternalLinks()
    {
        return (object) [
            'post_content' => sprintf(
                '<p><a href="%1$s/one">One</a><a href="%1$s/two">Two</a>'
                . '<a href="https://example.com/one">Example one</a>'
                . '<a href="https://example.org/two">Example two</a></p>',
                home_url()
            ),
        ];
    }
}
