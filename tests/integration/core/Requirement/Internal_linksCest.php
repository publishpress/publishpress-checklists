<?php namespace core\Requirement;

use PublishPress\Checklists\Core\Requirement\Internal_links;
use WpunitTester;

class Internal_linksCest
{
    public function getCurrentStatusForMinimumOnlyRuleWithInternalLinkReturnsTrue(WpunitTester $I)
    {
        $post = (object) [
            'post_content' => '<p><a href="' . home_url('/sample-page/') . '">Internal link</a></p>',
        ];

        $requirement = new Internal_links(null, null);

        // Base_counter represents a blank maximum as 0. For a positive
        // minimum this is a minimum-only rule, not an exact-zero rule.
        $I->assertTrue($requirement->get_current_status($post, [1, 0]));
    }

    public function getCurrentStatusForMinimumOnlyRuleWithoutInternalLinkReturnsFalse(WpunitTester $I)
    {
        $post = (object) [
            'post_content' => '<p>No links yet.</p>',
        ];

        $requirement = new Internal_links(null, null);

        $I->assertFalse($requirement->get_current_status($post, [1, 0]));
    }
}
