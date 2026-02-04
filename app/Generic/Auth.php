<?php

namespace Micro\Generic;

class Auth
{
    private const LOGIN_KEYS = [
        'id',
        'nombre',
        'apellido',
        'dpi',
        'usu',
        'puesto',
        'id_agencia',
        'agencia',
        'nomagencia',
        'idreglogin',
    ];

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::ensureSession();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::ensureSession();
        return array_key_exists($key, $_SESSION);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureSession();
        return self::has($key) ? $_SESSION[$key] : $default;
    }

    public static function remove(string $key): void
    {
        if (self::has($key)) {
            unset($_SESSION[$key]);
        }
    }

    public static function all(): array
    {
        self::ensureSession();
        return $_SESSION;
    }

    public static function clear(): void
    {
        self::ensureSession();
        $_SESSION = [];
    }

    public static function destroy(): void
    {
        self::clear();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * @param array{
     *     id:int|string,
     *     nombre:string,
     *     apellido:string,
     *     dpi:string,
     *     usu:string,
     *     puesto:string,
     *     id_agencia:int|string,
     *     agencia:string,
     *     nomagencia:string,
     *     idreglogin:int|string
     * } $loginData
     */
    public static function setLoginData(array $loginData): void
    {
        foreach (self::LOGIN_KEYS as $key) {
            if (!array_key_exists($key, $loginData)) {
                throw new \InvalidArgumentException("Falta la llave '{$key}' en los datos de login.");
            }
            self::set($key, $loginData[$key]);
        }
    }

    public static function getUserId(): int|string|null
    {
        $value = self::get('id');
        return is_int($value) || is_string($value) ? $value : null;
    }

    public static function getUserName(): ?string
    {
        $value = self::get('nombre');
        return is_string($value) ? $value : null;
    }

    public static function getAgencyId(): ?int
    {
        $value = self::get('id_agencia');
        return is_int($value) ? $value : null;
    }

    public static function getLoginSnapshot(): array
    {
        $snapshot = [];
        foreach (self::LOGIN_KEYS as $key) {
            $snapshot[$key] = self::get($key);
        }
        return $snapshot;
    }
}
