<?php namespace core\Requirement;


use PublishPress\Checklists\Core\Requirement\Validate_links;
use WpunitTester;
use Codeception\Example;

class Validate_linksCest
{
    public function _before(WpunitTester $I)
    {
    }

    public function getCurrentStatusForContentWithoutLinksReturnsTrue(WpunitTester $I)
    {
        $uid = microtime();

        $I->wantTo('call getCurrentStatus for content without links to return true');
        $postId = $I->factory()->post->create(
            [
                'post_name'    => 'the_post_' . $uid,
                'post_title'   => 'The post ' . $uid,
                'post_content' => 'This is a post with no links.',
                'post_status'  => 'publish',
            ]
        );

        $post = get_post($postId);

        $requirementInstance = new Validate_links(null, null);
        $status = $requirementInstance->get_current_status($post, '');

        $I->assertTrue($status);
    }

    /**
     * @example ["htt://invalidlink.com"]
     * @example ["httpw://invalidlink.com"]
     * @example ["http:/invalidlink.com"]
     * @example ["http:///invalidlink.com"]
     * @example ["https:///invalidlink.com"]
     * @example ["https:///invalidlink.com"]
     * @example [":https:///invalidlink.com"]
     * @example [":https:///invalidlink.com"]
     * @example ["https//invalidlink.com"]
     * @example ["tel:+134-42059f"]
     * @example ["tel:+2523552*"]
     * @example ["mailto"]
     * @example ["mailto:"]
     * @example ["mailto::test@example.com"]
     * @example ["mailto:testexample.com?subject=Mail from Our Site"]
     * @example ["mailt:someone@yoursite.com?cc=someoneelse@theirsite.com, another@thatsite.com, me@mysite.com&bcc=lastperson@theirsite.com&subject=Big%20News"]
     * @example ["mailtosomeone@yoursite.com?cc=someoneelse@theirsite.com, another@thatsite.com, me@mysite.com&bcc=lastperson@theirsite.com&subject=Big%20News&body=Body-goes-here"]
     */
    public function getCurrentStatusForContentWithInvalidLinkReturnsFalse(WpunitTester $I, Example $example)
    {
        $uid = microtime();

        $I->wantTo('call getCurrentStatus for content with links to return false');
        $postId = $I->factory()->post->create(
            [
                'post_name'    => 'the_post_' . $uid,
                'post_title'   => 'The post ' . $uid,
                'post_content' => 'This is a post has an <a href="' . $example[0] . '">invalid link</a>.',
                'post_status'  => 'publish',
            ]
        );

        $post = get_post($postId);

        $requirementInstance = new Validate_links(null, null);
        $status = $requirementInstance->get_current_status($post, '');

        $I->assertFalse($status);
    }

    /**
     * @example ["https://validlink.com"]
     * @example ["http://validlink.com"]
     * @example ["https://www.validlink.com"]
     * @example ["https://www.validlink.com/?arg1=3"]
     * @example ["https://validlink.com/?arg1=3&arg2=new"]
     * @example ["https://validlink.com/?arg1=3&amp;arg2=new"]
     * @example ["https://www.facebook.com/TestingTheLink/?__tn__=-UC*A"]
     * @example ["https://www.facebook.com/TestingTheLink/?a=235&amp;b=1"]
     * @example ["tel:+5544999993820"]
     * @example ["tel:+55-44-99999-3820"]
     * @example ["tel:205-555-1212"]
     * @example ["tel:+1-205-555-1212"]
     * @example ["tel:12055551212"]
     * @example ["mailto:test@example.com"]
     * @example ["mailto:test@example.com?subject=Mail from Our Site"]
     * @example ["mailto:someone@yoursite.com?cc=someoneelse@theirsite.com, another@thatsite.com, me@mysite.com&bcc=lastperson@theirsite.com&subject=Big%20News"]
     * @example ["mailto:someone@yoursite.com?cc=someoneelse@theirsite.com, another@thatsite.com, me@mysite.com&bcc=lastperson@theirsite.com&subject=Big%20News&body=Body-goes-here"]
     * @example ["mailto:someone@yoursite.com?cc=someoneelse@theirsite.com, another@thatsite.com, me@mysite.com&amp;bcc=lastperson@theirsite.com&amp;subject=Big%20News&amp;body=Body-goes-here"]
     * @example ["#checklist-item"]
     * @example ["/sample-page"]
     * @example ["../sample-page"]
     * @example ["?preview=true"]
     */
    public function getCurrentStatusForContentWithValidLinkReturnsTrue(WpunitTester $I, Example $example)
    {
        $uid = microtime();

        $I->wantTo('call getCurrentStatus for content with links to return false');
        $postId = $I->factory()->post->create(
            [
                'post_name'    => 'the_post_' . $uid,
                'post_title'   => 'The post ' . $uid,
                'post_content' => 'This is a post has an <a href="' . $example[0] . '">valid link</a>.',
                'post_status'  => 'publish',
            ]
        );

        $post = get_post($postId);

        $requirementInstance = new Validate_links(null, null);
        $status = $requirementInstance->get_current_status($post, '');

        $I->assertTrue($status);
    }
}
