<?php

/**
 * @deprecated
 * Clase para la protección CSRF (Cross-Site Request Forgery)
 * 
 * Esta clase proporciona métodos para generar, validar y gestionar tokens CSRF.
 * esta clase está obsoleta y se recomienda usar Micro\Helpers\CSRFProtection en su lugar.
 */
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
}
