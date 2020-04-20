<?php

use Laminas\Mail\Transport\File;
use ParagonIE\GPGMailer\GPGMailer;
use ParagonIE\GPGMailer\GPGMailerException;
use PHPUnit\Framework\TestCase;

/**
 * Class GPGMailerTest
 */
class GPGMailerTest extends TestCase
{
    /**
     * @var GPGMailer
     */
    private $gm;

    /**
     * @throws GPGMailerException
     */
    public function setUp(): void
    {
        if (\is_dir(__DIR__ . '/test/')) {
            \rmdir(__DIR__ . '/test/');
        }
        $this->gm = new GPGMailer(
            new File(),
            ['homedir' => '~']
        );
    }

    /**
     * @throws GPGMailerException
     */
    public function testSetOption()
    {
        $gm = clone $this->gm;
        $gm->setOption('invalid key', true);
        $this->assertTrue($gm->getOption('invalid key'));

        \mkdir(__DIR__ . '/test/', 0400);
        if (is_writable(__DIR__ . '/test/')) {
            $this->markTestSkipped('Inside virtualbox shared folder.');
        }
        try {
            $gm->setOption('homedir', __DIR__ . '/test/');
            $this->fail('No exception thrown');
        } catch (GPGMailerException $ex) {
        }
        \rmdir(__DIR__ . '/test/');

        $this->assertSame(
            ['homedir' => '~', 'invalid key' => true],
            $gm->getOptions()
        );
    }
}
