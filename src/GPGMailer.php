<?php
declare(strict_types=1);
namespace ParagonIE\GPGMailer;

use Laminas\Mail\{
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
     * @var array<string, string|int|float|bool|null|array>
     */
    protected $options;

    /**
     * @var string
     */
    protected $serverKeyFingerprint = '';

    /**
     * GPGMailer constructor.
     *
     * @param TransportInterface $transport
     * @param array<string, string|int|float|bool|null|array> $options
     *        For Crypt_GPG
     * @param string $serverKey
     *
     * @throws GPGMailerException
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
     * @throws \Crypt_GPG_Exception
     * @throws \Crypt_GPG_FileException
     * @throws GPGMailerException
     * @throws \PEAR_Exception
     */
    public function export(string $fingerprint): string
    {
        $gnupg = new \Crypt_GPG($this->options);
        try {
            $gnupg->addEncryptKey($fingerprint);
            return $gnupg->exportPublicKey($fingerprint, true);
        } catch (\PEAR_Exception $ex) {
            throw new GPGMailerException(
                'Could not export fingerprint "' . $fingerprint . '": ' .
                    $ex->getMessage(),
                (int) $ex->getCode(),
                $ex
            );
        }
    }

    /**
     * Encrypt the body of an email.
     *
     * @param Message $message
     * @param string $passphrase
     * @return Message
     * @throws \Crypt_GPG_FileException
     * @throws GPGMailerException
     * @throws \PEAR_Exception
     */
    public function decrypt(Message $message, string $passphrase = null): Message
    {
        $gnupg = new \Crypt_GPG($this->options);

        try {
            $gnupg->addDecryptKey($this->serverKeyFingerprint, $passphrase);
            // Replace the message with its encrypted counterpart
            $decrypted = $gnupg->decrypt($message->getBodyText());
            return (clone $message)->setBody($decrypted);
        } catch (\PEAR_Exception $ex) {
            throw new GPGMailerException(
                'Could not decrypt message: ' . $ex->getMessage(),
                (int) $ex->getCode(),
                $ex
            );
        }
    }

    /**
     * Encrypt the body of an email.
     *
     * @param Message $message
     * @param string $fingerprint
     * @return Message
     * @throws \Crypt_GPG_Exception
     * @throws \Crypt_GPG_FileException
     * @throws GPGMailerException
     * @throws \PEAR_Exception
     */
    public function encrypt(Message $message, string $fingerprint): Message
    {
        $gnupg = new \Crypt_GPG($this->options);
        try {
            $gnupg->addEncryptKey($fingerprint);

            // Replace the message with its encrypted counterpart
            $encrypted = $gnupg->encrypt($message->getBodyText(), true);

            return (clone $message)->setBody($encrypted);
        } catch (\PEAR_Exception $ex) {
            throw new GPGMailerException(
                'Could not encrypt message: ' . $ex->getMessage(),
                (int) $ex->getCode(),
                $ex
            );
        }
    }

    /**
     * Encrypt and sign the body of an email.
     *
     * @param Message $message
     * @param string $fingerprint
     * @return Message
     * @throws GPGMailerException
     * @throws \Crypt_GPG_Exception
     * @throws \Crypt_GPG_FileException
     * @throws \PEAR_Exception
     */
    public function encryptAndSign(Message $message, string $fingerprint): Message
    {
        if (!$this->serverKeyFingerprint) {
            throw new GPGMailerException('No signing key provided');
        }
        $gnupg = new \Crypt_GPG($this->options);
        $gnupg->addEncryptKey($fingerprint);
        $gnupg->addSignKey($this->serverKeyFingerprint);

        try {
            // Replace the message with its encrypted counterpart
            $encrypted = $gnupg->encryptAndSign($message->getBodyText(), true);

            return (clone $message)->setBody($encrypted);
        } catch (\PEAR_Exception $ex) {
            throw new GPGMailerException(
                'Could not encrypt and sign message: ' . $ex->getMessage(),
                (int) $ex->getCode(),
                $ex
            );
        }
    }

    /**
     * Return the stored Transport.
     *
     * @return TransportInterface
     */
    public function getTransport(): TransportInterface
    {
        return $this->mailer;
    }

    /**
     * Import a public key, return the fingerprint
     *
     * @param string $gpgKey An ASCII armored public key
     * @return string The GPG fingerprint for this key
     * @throws GPGMailerException
     */
    public function import(string $gpgKey): string
    {
        try {
            $gnupg = new \Crypt_GPG($this->options);
            /** @var array<string, string> $data */
            $data = $gnupg->importKey($gpgKey);

            return $data['fingerprint'];
        } catch (\PEAR_Exception $ex) {
            throw new GPGMailerException(
                'Could not import public key: ' . $ex->getMessage(),
                (int) $ex->getCode(),
                $ex
            );
        }
    }

    /**
     * Encrypt then email a message
     *
     * @param Message $message    The message data
     * @param string $fingerprint Which public key fingerprint to use
     * @return void
     *
     * @throws \Crypt_GPG_Exception
     * @throws \Crypt_GPG_FileException
     * @throws GPGMailerException
     * @throws \PEAR_Exception
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
     * Email a message without encrypting it.
     *
     * @param Message $message The message data
     * @param bool $force      Send even if we don't have a private key?
     * @return void
     *
     * @throws GPGMailerException
     * @throws \Crypt_GPG_FileException
     * @throws \PEAR_Exception
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
     * @param string $key
     * @return string|int|float|bool|null|array
     *
     * @throws GPGMailerException
     */
    public function getOption(string $key)
    {
        if (!\array_key_exists($key, $this->options)) {
            throw new GPGMailerException('Key ' . $key . ' not defined');
        }
        return $this->options[$key];
    }

    /**
     * @return array<string, string|int|float|bool|null|array>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Override an option at runtime
     *
     * @param string $key
     * @param string|int|float|bool|null|array $value
     *
     * @return self
     * @throws GPGMailerException
     */
    public function setOption(string $key, $value): self
    {
        $options = $this->options;
        $options[$key] = $value;
        // Try to set this, so it will throw an exception
        try {
            (new \Crypt_GPG($options));
        } catch (\PEAR_Exception $ex) {
            throw new GPGMailerException(
                'Could not set option "' . $key . '": ' . $ex->getMessage(),
                (int) $ex->getCode(),
                $ex
            );
        }
        $this->options = $options;
        return $this;
    }

    /**
     * Sets the private key for signing.
     *
     * @param string $serverKey
     * @return self
     * @throws GPGMailerException
     */
    public function setPrivateKey(string $serverKey): self
    {
        $this->serverKeyFingerprint = $this->import($serverKey);
        return $this;
    }

    /**
     * @param TransportInterface $transport
     *
     * @return self
     */
    public function setTransport(TransportInterface $transport): self
    {
        $this->mailer = $transport;
        return $this;
    }

    /**
     * Sign a message (but don't encrypt)
     *
     * @param Message $message
     * @return Message
     *
     * @throws \Crypt_GPG_FileException
     * @throws GPGMailerException
     * @throws \PEAR_Exception
     */
    public function sign(Message $message): Message
    {
        if (!$this->serverKeyFingerprint) {
            throw new GPGMailerException('No signing key provided');
        }
        $gnupg = new \Crypt_GPG($this->options);
        $gnupg->addSignKey($this->serverKeyFingerprint);

        try {
            return (clone $message)->setBody(
                $gnupg->sign(
                    $message->getBodyText(),
                    \Crypt_GPG::SIGN_MODE_CLEAR,
                    true
                )
            );
        } catch (\PEAR_Exception $ex) {
            throw new GPGMailerException(
                'Could not sign message: ' . $ex->getMessage(),
                (int) $ex->getCode(),
                $ex
            );
        }
    }

    /**
     * Verify a message
     *
     * @param Message $message
     * @param string $fingerprint
     * @return bool
     *
     * @throws \Crypt_GPG_FileException
     * @throws GPGMailerException
     * @throws \PEAR_Exception
     */
    public function verify(Message $message, string $fingerprint): bool
    {
        $gnupg = new \Crypt_GPG($this->options);
        $gnupg->addSignKey($fingerprint);

        /**
         * @var \Crypt_GPG_Signature[] $verified
         */
        try {
            $verified = $gnupg->verify($message->getBodyText());
            /**
             * @var \Crypt_GPG_Signature $sig
             */
            foreach ($verified as $sig) {
                if ($sig->isValid()) {
                    return true;
                }
            }
            return false;
        } catch (\PEAR_Exception $ex) {
            throw new GPGMailerException(
                'An error occurred trying to verify this message: ' .
                    $ex->getMessage(),
                (int) $ex->getCode(),
                $ex
            );
        }
    }
}
