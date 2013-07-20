<?php
namespace OpenCFP;

use Cartalyst\Sentry\Cookies\CookieInterface as SentryCookieInterface;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SymfonySentrySession implements SentryCookieInterface
{
    private $session;
    private $key;

    function __construct(SymfonySessionInterface $session, $key = null)
    {
        $this->session = $session;
        $this->key = $key ?: 'cartalyst_sentry';
    }

    function getKey()
    {
        return $this->key;
    }

    function put($value)
    {
        $this->session->set($this->key, $value);
    }

    public function get()
    {
        return $this->session->get($this->key);
    }

    public function forget()
    {
        $this->session->remove($this->key);
    }
}
