<?php
/**
 * Phergie plugin for Play One Night Revolution on IRC (https://github.com/frozen-solid/phergie-irc-plugin-react-onenightrevolution)
 *
 * @link https://github.com/frozen-solid/phergie-irc-plugin-react-onenightrevolution for the canonical source repository
 * @copyright Copyright (c) 2016 Matt Schraeder (http://frozen-solid.net)
 * @license http://phergie.org/license Simplified BSD License
 * @package Phergie\Irc\Plugin\React\One Night Revolution Bot
 */

namespace Phergie\Irc\Plugin\React\GameBot;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;

/**
 * Plugin class.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\GameBot
 */
class Plugin extends AbstractPlugin
{
	private $activeGames = array();

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
            'command.create' => 'handleCreate',
			'command.start' => 'handleStart',
			'command.join' => 'handleJoin',
			'command.end' => 'handleEnd'
        ];
    }

    /**
     *
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleCreate(Event $event, Queue $queue)
    {
        $channel = $event->getSource();
		$connection = $event->getConnection();
		$serverName = $connection->getServerhostname();
		$gameName = strtolower($event->getCustomParams()[0]);

		if (isset($this->activeGames[$serverName][$channel])) {
			$queue->ircPrivmsg($channel, 'Game already running in '.$channel);
			return;
		}

		switch ($gameName) {
			case 'onenightrevolution':
			case 'onr':
			case 'one night revolution':
				$game = new OneNightRevolution($queue, $channel, $this->emitter);
				break;
			default:
				$queue->ircPrivmsg($channel, 'Game not found');
				return;
				break;
		}

		$this->activeGames[$serverName][$channel] = $game;
		$queue->ircPrivmsg($channel, $game->getMessage());
    }

    /**
     *
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
	public function handleStart(Event $event, Queue $queue)
	{
		$channel = $event->getSource();
		$connection = $event->getConnection();
		$serverName = $connection->getServerhostname();
		$game = $this->activeGames[$serverName][$channel];

		if ($game) {
			$game->start();

			if ($game->getPhase() === 'pregame') {
				$queue->ircPrivmsg($channel, $game->getMessage());
			}
		} else {
			$queue->ircPrivmsg($channel, 'No game has been created.');
		}
	}

	/**
	 *
	 *
	 * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
	 * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
	 */
	public function handleJoin(Event $event, Queue $queue)
	{
		$channel = $event->getSource();
		$connection = $event->getConnection();
		$serverName = $connection->getServerhostname();
		$game = $this->activeGames[$serverName][$channel];

		if ($game) {
			if ($game->getPhase() === 'pregame') {
				if ($game->addPlayer($event->getNick())) {
					$queue->ircPrivmsg($channel, $event->getNick().' joined the game!');
				} else {
					$queue->ircPrivmsg($channel, $event->getNick().' is already in the game!');
				}
			} else {
				$queue->ircPrivmsg($channel, 'Game already started. Wait until next game.');
			}
		} else {
			$queue->ircPrivmsg($channel, 'No game has been created.');
		}
	}

	/**
	 *
	 *
	 * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
	 * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
	 */
	public function handleEnd(Event $event, Queue $queue)
	{
		$channel = $event->getSource();
		$connection = $event->getConnection();
		$serverName = $connection->getServerhostname();
		$game = $this->activeGames[$serverName][$channel];

		if ($game) {
			$queue->ircPrivmsg($channel, 'Game has been ended.');
			unset($this->activeGames[$serverName][$channel]);

			if (count($this->activeGames[$serverName]) === 0) {
				unset($this->activeGames[$serverName]);
			}
		} else {
			$queue->ircPrivmsg($channel, 'No game has been created.');
		}
	}

	public static function shuffle_assoc(&$array) {
		$keys = array_keys($array);
		$shuffled = [];

		shuffle($keys);

		foreach($keys as $key) {
			$shuffled[$key] = $array[$key];
		}

		$array = $shuffled;

		return true;
	}
}
