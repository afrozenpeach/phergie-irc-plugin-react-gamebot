<?php
/**
 * Phergie plugin for Play One Night Revolution on IRC (https://github.com/frozen-solid/phergie-irc-plugin-react-onenightrevolution)
 *
 * @link https://github.com/frozen-solid/phergie-irc-plugin-react-onenightrevolution for the canonical source repository
 * @copyright Copyright (c) 2016 Matt Schraeder (http://frozen-solid.net)
 * @license http://phergie.org/license Simplified BSD License
 * @package Phergie\Irc\Plugin\React\One Night Revolution Bot
 */

namespace Phergie\Irc\Tests\Plugin\React\One Night Revolution Bot;

use Phake;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use Phergie\Irc\Plugin\React\One Night Revolution Bot\Plugin;

/**
 * Tests for the Plugin class.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\One Night Revolution Bot
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{


    /**
     * Tests that getSubscribedEvents() returns an array.
     */
    public function testGetSubscribedEvents()
    {
        $plugin = new Plugin;
        $this->assertInternalType('array', $plugin->getSubscribedEvents());
    }
}
