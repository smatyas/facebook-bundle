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

namespace Smatyas\FacebookBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class UpdateReceivedEvent extends Event
{
    const NAME = 'smatyas_facebook.update.received';

    /**
     * The update content.
     *
     * @var string
     */
    protected $content;

    /**
     * UpdateReceivedEvent constructor.
     * @param $content
     */
    public function __construct($content)
    {
        $this->content = $content;
    }

    /**
     * Returns the update content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Returns the update content as an array.
     *
     * @param bool $assoc
     * @return mixed
     */
    public function getContentAsArray($assoc = false)
    {
        return json_decode($this->getContent(), $assoc);
    }
}
