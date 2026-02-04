<?php

namespace Micro\Helpers;

class SecureID
{
    private $encryptionKey;
    private $cipherMethod;

    public function __construct($key)
    {
        $this->encryptionKey = hash('sha256', $key);
        $this->cipherMethod = 'AES-256-CBC';
    }

    public function encrypt($id)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipherMethod));
        $encryptedID = openssl_encrypt($id, $this->cipherMethod, $this->encryptionKey, 0, $iv);
        return base64_encode($encryptedID . '::' . $iv);
    }

    public function decrypt($encryptedData)
    {
        list($encryptedID, $iv) = explode('::', base64_decode($encryptedData), 2);
        return openssl_decrypt($encryptedID, $this->cipherMethod, $this->encryptionKey, 0, $iv);
    }
}