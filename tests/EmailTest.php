<?php
use ParagonIE\GPGMailer\GPGMailer;
use PHPUnit\Framework\TestCase;
use Zend\Mail\Message;
use Zend\Mail\Transport\File;

class EmailTest extends TestCase
{
    /**
     * @covers GPGMailer::import()
     * @covers GPGMailer::export()
     */
    public function testImport()
    {
        // Instantiate GPGMailer:
        $gpgMailer = new GPGMailer(
            new File(),
            ['homedir' => '~']
        );

        $publicKey = file_get_contents(__DIR__ . '/public.key');
        $fingerprint = $gpgMailer->import($publicKey);
        $this->assertSame(
            '1B6EFC02852A489B8162033CC7C64BB7CA403A7E',
            $fingerprint
        );

        $exported = $gpgMailer->export($fingerprint);
        $this->assertSame(
            $this->stripComment($publicKey),
            $this->stripComment($exported)
        );
    }

    /**
     * @covers GPGMailer::decrypt()
     * @covers GPGMailer::encrypt()
     */
    public function testEncryptedMessage()
    {
        // First, create a Zend\Mail message as usual:
        $plaintext = 'Cleartext for now. Do not worry; this gets encrypted. Don\' actually send this, however.';
        $plaintext .= \random_bytes(32);
        $message = new Message;
        $message->addTo('nobody@example.com', 'GPGMailer Test Email');
        $message->setBody($plaintext);

        // Instantiate GPGMailer:
        $gpgMailer = new GPGMailer(
            new File(),
            ['homedir' => '~']
        );

        $publicKey = file_get_contents(__DIR__ . '/public.key');
        $fingerprint = $gpgMailer->import($publicKey);

        $encrypted = $gpgMailer->encrypt($message, $fingerprint);
        $body = $encrypted->getBodyText();
        $this->assertTrue(
            \strpos($body, '-----BEGIN PGP MESSAGE-----') !== false
        );
        $privateKey = file_get_contents(__DIR__ . '/private.key');
        $gpgMailer->setPrivateKey($privateKey);
        
        $decrypted = $gpgMailer->decrypt($encrypted);
        $this->assertSame(
            $plaintext,
            $decrypted->getBodyText()
        );
    }

    /**
     * @covers GPGMailer::sign()
     * @covers GPGMailer::verify()
     */
    public function testSignedMessage()
    {
        // First, create a Zend\Mail message as usual:
        $message = new Message;
        $message->addTo('nobody@example.com', 'GPGMailer Test Email');
        $message->setBody(
            'Cleartext for now. We are going to sign this.'
        );

        $privateKey = file_get_contents(__DIR__ . '/private.key');

        // Instantiate GPGMailer:
        $gpgMailer = new GPGMailer(
            new File(),
            ['homedir' => '~'],
            $privateKey
        );

        $publicKey = file_get_contents(__DIR__ . '/public.key');
        $signature = $gpgMailer->sign($message);
        $this->assertTrue(
            \strpos($signature->getBodyText(), '-----BEGIN PGP SIGNATURE-----') !== false
        );

        $fingerprint = $gpgMailer->import($publicKey);
        $this->assertTrue(
            $gpgMailer->verify($signature, $fingerprint)
        );
    }

    /**
     * @param string $input
     * @return string
     */
    protected function stripComment(string $input): string
    {
        $pieces = \explode("\n", $input);
        foreach ($pieces as $i => $piece) {
            if (\preg_match('/^Version:/', $piece)) {
                unset($pieces[$i]);
            }
        }
        return \str_replace("\r", '', \implode('', $pieces));
    }
}
