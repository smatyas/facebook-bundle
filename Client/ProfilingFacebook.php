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

use Facebook\Facebook;
use Symfony\Component\Stopwatch\Stopwatch;

class ProfilingFacebook extends Facebook
{
    /**
     * ProfilingFacebookClient constructor.
     * @param array $config
     * @param Stopwatch $stopwatch
     */
    public function __construct(array $config = [], Stopwatch $stopwatch = null)
    {
        parent::__construct($config);

        // Override the client created in the parent with the profiling one.
        $enableBeta = isset($config['enable_beta_mode']) ? $config['enable_beta_mode'] : false;
        $this->client = new ProfilingFacebookClient(
            $this->client->getHttpClientHandler(),
            $enableBeta,
            $stopwatch
        );
    }

    /**
     * @return array
     */
    public function getProfiles()
    {
        return $this->client->getProfiles();
    }
}
