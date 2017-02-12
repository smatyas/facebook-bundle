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

namespace Smatyas\FacebookBundle\Client;

use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\FacebookClient;
use Facebook\FacebookRequest;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

class ProfilingFacebookClient extends FacebookClient
{
    /**
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * @var array
     */
    private $profiles;

    /**
     * @var integer
     */
    private $profileCounter;

    public function __construct($httpClientHandler = null, $enableBeta = false, Stopwatch $stopwatch = null)
    {
        parent::__construct($httpClientHandler, $enableBeta);

        $this->stopwatch = $stopwatch;
        $this->profileCounter = 0;
        $this->profiles = [];
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(FacebookRequest $request)
    {
        $event = $this->startProfile($request);
        $code = null;
        $response = null;
        $thrownException = null;
        try {
            $response = parent::sendRequest($request);
            $code = $response->getHttpStatusCode();
        } catch (FacebookResponseException $e) {
            $thrownException = $e;
            $response = $thrownException->getResponse();
            $code = $response->getHttpStatusCode();
        } catch (FacebookSDKException $e) {
            $thrownException = $e;
            $code = $e->getCode();
            // TODO: extract some data?
        } catch (\Exception $e) {
            $thrownException = $e;
            $code = $e->getCode();
        } finally {
            $this->stopProfile($code, $response, $event);
        }

        if ($thrownException) {
            throw $thrownException;
        }

        return $response;
    }

    /**
     * @return array
     */
    public function getProfiles()
    {
        return $this->profiles;
    }

    private function startProfile(FacebookRequest $request)
    {
        if ($this->stopwatch == null) {
            return null;
        }

        $this->profileCounter++;
        $this->profiles[$this->profileCounter] = [
            'request' => $request,
            'duration' => null,
        ];

        return $this->stopwatch->start('facebook', 'facebook');
    }

    private function stopProfile($code, $response, StopwatchEvent $event = null)
    {
        if ($this->stopwatch == null) {
            return null;
        }

        $event->stop();
        $periods = $event->getPeriods();
        $values = [
            'duration' => end($periods)->getDuration(),
            'code' => $code,
            'response' => $response,
        ];

        $this->profiles[$this->profileCounter] = array_merge($this->profiles[$this->profileCounter], $values);
    }
}
