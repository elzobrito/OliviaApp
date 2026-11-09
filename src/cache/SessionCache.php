<?php

    namespace OliviaCache;

    class SessionCache implements CacheInterface
    {
        private $sessionKey;

        public function __construct($sessionKey)
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $this->sessionKey = $sessionKey;
        }

        public function get($key)
        {
            if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
                return null;
            }

            return isset($_SESSION[$this->sessionKey][$key]) ? $_SESSION[$this->sessionKey][$key] : null;
        }

        public function set($key, $value)
        {
            if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
                $_SESSION[$this->sessionKey] = array();
            }

            $_SESSION[$this->sessionKey][$key] = $value;
        }

        public function delete()
        {
            unset($_SESSION[$this->sessionKey]);
        }
    }
