<?php
declare(strict_types=1);
namespace ParagonIE\GPGMailer;

use Zend\Mail\{
    Message,
    Transport\TransportInterface
};

/**
 * Class GPGMailer
 * @package ParagonIE\GPGMailer
 */
class GPGMailer
{
    /**
     * @var TransportInterface
     */
    protected $mailer;

    /**
     * @var array
     */
    protected $options;

    /**
     * GPGMailer constructor.
     * @param TransportInterface $transport
     * @param array $options
     */
    public function __construct(
        TransportInterface $transport,
        array $options = []
    ) {
        $this->mailer = $transport;
        $this->options = $options;
    }

    /**
     * @param Message $message
     * @param string $fingerprint
     * @return Message
     */
    public function encrypt(Message $message, string $fingerprint): Message
    {

        $gnupg = new \Crypt_GPG($this->options);
        $gnupg->addEncryptKey($fingerprint);

        // Replace the message with its encrypted counterpart
        $encrypted = $gnupg->encrypt($message->getBodyText(), true);

        return $message->setBody($encrypted);
    }

    /**
     * Encrypt then email a message
     *
     * @param Message $message    The message data
     * @param string $fingerprint Which public key fingerprint to use
     */
    public function send(Message $message, string $fingerprint)
    {
        $this->mailer->send(
            $this->encrypt($message, $fingerprint)
        );
    }

    /**
     * Import a public key, return the fingerprint
     *
     * @param string $publicKey
     * @return string
     */
    public function import(string $publicKey): string
    {
        try {
            $gnupg = new \Crypt_GPG($this->options);
            $data = $gnupg->importKey($publicKey);
            return $data['fingerprint'];
        } catch (\Crypt_GPG_NoDataException $ex) {
            return '';
        } catch (\Crypt_GPG_Exception $ex) {
            return '';
        }
    }
}
