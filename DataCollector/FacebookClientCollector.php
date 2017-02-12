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

namespace Smatyas\FacebookBundle\DataCollector;

use Smatyas\FacebookBundle\Service\Facebook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class FacebookClientCollector extends DataCollector
{
    /**
     * @var Facebook
     */
    private $facebook;

    /**
     * FacebookClientCollector constructor.
     * @param $facebook
     */
    public function __construct(Facebook $facebook)
    {
        $this->facebook = $facebook;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'facebook.facebook_client_collector';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $calls = $this->facebook->getProfiles();
        $callsTotalTime = 0;
        foreach ($calls as $call) {
            $callDuration = isset($call['duration']) ? (int) $call['duration'] : 0;
            $callsTotalTime += $callDuration;
        }
        $this->data = [
            'calls' => $calls,
            'callsTotalTime' => $callsTotalTime,
        ];
    }

    /**
     * @return array
     */
    public function getCalls()
    {
        return $this->data['calls'];
    }

    public function getCallsTotalTime()
    {
        return $this->data['callsTotalTime'];
    }
}
