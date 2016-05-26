<?php
/**
 * Phergie plugin for Play One Night Revolution on IRC (https://github.com/frozen-solid/phergie-irc-plugin-react-onenightrevolution)
 *
 * @link https://github.com/frozen-solid/phergie-irc-plugin-react-onenightrevolution for the canonical source repository
 * @copyright Copyright (c) 2016 Matt Schraeder (http://frozen-solid.net)
 * @license http://phergie.org/license Simplified BSD License
 * @package Phergie\Irc\Plugin\React\One Night Revolution Bot
 */

namespace Phergie\Irc\Plugin\React\One Night Revolution Bot;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;

/**
 * Plugin class.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\One Night Revolution Bot
 */
class Plugin extends AbstractPlugin
{
    /**
     * Accepts plugin configuration.
     *
     * Supported keys:
     *
     *
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {

    }

    /**
     *
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            'command.' => 'handleCommand',
        ];
    }

    /**
     *
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleCommand(Event $event, Queue $queue)
    {
    }
}
