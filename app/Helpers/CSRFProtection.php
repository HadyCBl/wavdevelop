<?php

namespace Micro\Helpers;

class CSRFProtection
{
    private $token;
    private $tokenName = 'csrf_token';

    public function __construct()
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION[$this->tokenName])) {
            $this->regenerateToken();
        } else {
            $this->token = $_SESSION[$this->tokenName];
        }
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getTokenName()
    {
        return $this->tokenName;
    }

    public function regenerateToken()
    {
        $this->token = bin2hex(random_bytes(32));
        $_SESSION[$this->tokenName] = $this->token;
    }

    public function validateToken($token, $estrict = true)
    {
        if (!isset($_SESSION[$this->tokenName])) {
            return false;
        }

        if (hash_equals($_SESSION[$this->tokenName], $token)) {
            if ($estrict) {
                $this->regenerateToken();
            }
            return true;
        }

        return false;
    }

    public function getTokenField()
    {
        return '<input type="hidden" id="' . $this->tokenName . '" name="' . $this->tokenName . '" value="' . $this->getToken() . '">';
    }

    public static function insertTokenIntoForm()
    {
        $csrf = new self();
        echo $csrf->getTokenField();
    }

    public static function getTokenValue()
    {
        $csrf = new self();
        return $csrf->getToken();
    }
}
