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
     * @var string
     */
    protected $serverKeyFingerprint;

    /**
     * GPGMailer constructor.
     * @param TransportInterface $transport
     * @param array $options For Crypt_GPG
     */
    public function __construct(
        TransportInterface $transport,
        array $options = [],
        string $serverKey = ''
    ) {
        $this->mailer = $transport;
        $this->options = $options;
        if (!empty($serverKey)) {
            $this->serverKeyFingerprint = $this->import($serverKey);
        }
    }

    /**
     * Get the public key corresponding to a fingerprint.
     *
     * @param string $fingerprint
     * @return string
     */
    public function export(string $fingerprint): string
    {
        $gnupg = new \Crypt_GPG($this->options);
        $gnupg->addEncryptKey($fingerprint);
        return $gnupg->exportPublicKey($fingerprint, true);
    }

    /**
     * Encrypt the body of an email.
     *
     * @param Message $message
     * @return Message
     */
    public function decrypt(Message $message): Message
    {
        $gnupg = new \Crypt_GPG($this->options);

        $gnupg->addDecryptKey($this->serverKeyFingerprint);

        // Replace the message with its encrypted counterpart
        $decrypted = $gnupg->decrypt($message->getBodyText());

        return $message->setBody($decrypted);
    }

    /**
     * Encrypt the body of an email.
     *
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
     * Encrypt the body of an email.
     *
     * @param Message $message
     * @param string $fingerprint
     * @return Message
     * @throws \Exception
     */
    public function encryptAndSign(Message $message, string $fingerprint): Message
    {
        if (!$this->serverKeyFingerprint) {
            throw new \Exception('No signing key provided');
        }
        $gnupg = new \Crypt_GPG($this->options);
        $gnupg->addEncryptKey($fingerprint);
        $gnupg->addSignKey($this->serverKeyFingerprint);

        // Replace the message with its encrypted counterpart
        $encrypted = $gnupg->encryptAndSign($message->getBodyText(), true);

        return $message->setBody($encrypted);
    }

    /**
     * Return the stored Transport.
     *
     * @return TransportInterface
     */
    public function getTransport(): TransportInterface
    {
        return $this->getTransport();
    }

    /**
     * Import a public key, return the fingerprint
     *
     * @param string $gpgKey An ASCII armored public key
     * @return string The GPG fingerprint for this key
     */
    public function import(string $gpgKey): string
    {
        try {
            $gnupg = new \Crypt_GPG($this->options);
            $data = $gnupg->importKey($gpgKey);
            return $data['fingerprint'];
        } catch (\Crypt_GPG_NoDataException $ex) {
            return '';
        } catch (\Crypt_GPG_Exception $ex) {
            return '';
        }
    }

    /**
     * Encrypt then email a message
     *
     * @param Message $message    The message data
     * @param string $fingerprint Which public key fingerprint to use
     */
    public function send(Message $message, string $fingerprint)
    {
        if ($this->serverKeyFingerprint) {
            $this->mailer->send(
                // Encrypted, signed
                $this->encryptAndSign($message, $fingerprint)
            );
        } else {
            $this->mailer->send(
                // Encrypted, unsigned
                $this->encrypt($message, $fingerprint)
            );
        }
    }

    /**
     * Encrypt then email a message
     *
     * @param Message $message The message data
     * @param bool $force      Send even if we don't have a private key?
     */
    public function sendUnencrypted(Message $message, bool $force = false)
    {
        if (!$this->serverKeyFingerprint) {
            if ($force) {
                // Unencrypted, unsigned
                $message->setBody($message->getBodyText());
            }
            return;
        }
        $this->mailer->send(
            // Unencrypted, signed:
            $this->sign($message)
        );
    }

    /**
     * Sets the private key for signing.
     *
     * @param string $serverKey
     * @return GPGMailer
     */
    public function setPrivateKey(string $serverKey): self
    {
        $this->serverKeyFingerprint = $this->import($serverKey);
        return $this;
    }

    /**
     * Sign a message (but don't encrypt)
     *
     * @param Message $message
     * @return string
     * @throws \Exception
     */
    public function sign(Message $message): Message
    {
        if (!$this->serverKeyFingerprint) {
            throw new \Exception('No signing key provided');
        }
        $gnupg = new \Crypt_GPG($this->options);
        $gnupg->addSignKey($this->serverKeyFingerprint);

        $message->setBody(
            $gnupg->sign(
                $message->getBodyText(),
                \Crypt_GPG::SIGN_MODE_CLEAR,
                true
            )
        );
        return $message;
    }


    /**
     * Verify a message
     *
     * @param Message $message
     * @param string $fingerprint
     * @return string
     * @throws \Exception
     */
    public function verify(Message $message, string $fingerprint): bool
    {
        $gnupg = new \Crypt_GPG($this->options);
        $gnupg->addSignKey($fingerprint);

        /**
         * @var \Crypt_GPG_Signature[]
         */
        $verified = $gnupg->verify($message->getBodyText());
        foreach ($verified as $sig) {
            if (false) {
                $sig = new \Crypt_GPG_Signature();
            }
            if ($sig->isValid()) {
                return true;
            }
        }
        return false;
    }
}
