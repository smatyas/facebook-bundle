<?php
/**
 * This file is part of the Smatyas/FacebookBundle.
 *
 * (c) Mátyás Somfai <somfai.matyas@gmail.com>
 * Created at 2017.02.01.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Smatyas\FacebookBundle\Service;

use Facebook\PersistentData\PersistentDataInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SymfonySessionPersistentDataHandler implements PersistentDataInterface
{
    /**
     * The Symfony session service.
     *
     * @var SessionInterface
     */
    private $session;

    /**
     * @var string Prefix to use for session variables.
     */
    protected $sessionPrefix = 'FBRLH_';

    /**
     * Creates a new SymfonySessionPersistentDataHandler instance.
     *
     * @param SessionInterface $session The Symfony session service.
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        return $this->session->get($this->getPrefixedKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $this->session->set($this->getPrefixedKey($key), $value);
    }

    /**
     * Gets the prefixed session key.
     *
     * @param $key
     * @return string
     */
    protected function getPrefixedKey($key)
    {
        return $this->sessionPrefix . $key;
    }
}
