<?php

namespace PublishPress\Checklists\Core\Utils;


use Codeception\Example;
use WpunitTester;

class HyperlinkValidatorCest
{
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
     * @example ["http://invalidlinkcom"]
     * @example ["htt//invalidlink.com/"]
     * @example ["http:/invalidlink.com"]
     * @example ["skype://test"]
     * @example ["mailto"]
     * @example ["mailto:"]
     * @example ["mailto::test@example.com"]
     * @example ["mailto:testexample.com?subject=Mail from Our Site"]
     * @example ["mailt:someone@yoursite.com?cc=someoneelse@theirsite.com, another@thatsite.com, me@mysite.com&bcc=lastperson@theirsite.com&subject=Big%20News"]
     * @example ["mailtosomeone@yoursite.com?cc=someoneelse@theirsite.com, another@thatsite.com, me@mysite.com&bcc=lastperson@theirsite.com&subject=Big%20News&body=Body-goes-here"]
     * @example ["#"]
     * @example ["/relative path/with spaces"]
     * @example ["../relative path/with spaces"]
     * @example ["?invalid query"]
     * @example ["/"]
     * @example ["//"]
     * @example ["../"]
     * @example ["?"]
     */
    public function isValidLinkWithInvalidLinkReturnsFalse(WpunitTester $I, Example $example)
    {
        $validator = new HyperlinkValidator();

        $actual = $validator->isValidLink($example[0]);

        $I->assertFalse($actual);
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
     * @example ["mailto:someone@yoursite.com?cc=someoneelse@theirsite.com, another@thatsite.com, me@mysite.com&bcc=lastperson@theirsite.com&amp;subject=Big%20News&body=Body-goes-here"]
     * @example ["#checklist-item"]
     * @example ["/sample-page"]
     * @example ["/sample-page?preview=true"]
     * @example ["../sample-page"]
     * @example ["./sample-page"]
     * @example ["?preview=true"]
     * @example ["//cdn.example.com/image.jpg"]
     * @example ["https://validlink.com/#section"]
     * @example ["mailto:test@example.com?subject=Issue #12 from Our Site"]
     */
    public function isValidLinkWithValidLinkReturnsTrue(WpunitTester $I, Example $example)
    {
        $validator = new HyperlinkValidator();

        $actual = $validator->isValidLink($example[0]);

        $I->assertTrue($actual);
    }
}
