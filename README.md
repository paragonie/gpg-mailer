# GPG-Mailer

Send GPG-encrypted emails (using [zend-mail](https://github.com/zendframework/zend-mail)
and [Crypt_GPG](https://github.com/pear/Crypt_GPG)).

## Example

```php
<?php
use \ParagonIE\GPGMailer\GPGMailer;
use \Zend\Mail\Message;
use \Zend\Mail\Transport\Sendmail;

// First, create a Zend\Mail message as usual:
$message = new Message;
$message->addTo('test@example.com', 'Test Email');
$message->setBody('Cleartext for now. Do not worry; this gets encrypted.');

// Instantiate GPGMailer:
$gpgMailer = new GPGMailer(
    new Sendmail(), 
    ['homedir' => '/homedir/containing/keyring']
);

// GPG public key for <security@paragonie.com> (fingerprint):
$fingerprint = '7F52D5C61D1255C731362E826B97A1C2826404DA';

// Finally:
$gpgMailer->send($message, $fingerprint); 
```

If you're encrypting with a user provided public key (and they didn't tell you
their fingerprint), do this instead:

```php
$fingerprint = $gpgMailer->import($ASCIIArmoredPublicKey);
```
