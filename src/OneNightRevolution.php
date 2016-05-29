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

use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use Phergie\Irc\Client\React\Client as Client;
use Phergie\Irc\Plugin\React\GameBot\Plugin as GameBot;

class OneNightRevolution
{
	private $players = [];
	private $phase = null;
	private $maxPlayers = 10;
	private $validSpecialties = ['Analyst', 'Confirmer', 'DeepAgent', 'Defector', 'Investigator', 'Observer', 'Reassigner', 'Revealer', 'Signaller', 'Rogue', 'Thief'];
	private $specialties = ['Reassigner' => 2, 'Investigator' => 2, 'Thief' => 2, 'Signaler' => 2, 'Observer' => 2];
	private $ids = ['Rebel' => 10, 'Informant' => 3];
	private $message = null;
	private $queue = null;
	private $channel = null;
	private $emitter = null;

	private $hq = array();

	public function __construct(Queue $queue, String $channel, Client $emitter)
	{
		$this->phase = 'pregame';
		$this->queue = $queue;
		$this->channel = $channel;
		$this->emitter = $emitter;

		$callbacks = $this->getSubscribedEvents();

		foreach ($callbacks as $event => $callback) {
			$pluginCallback = [ $this, $callback ];
			if (is_callable($pluginCallback)) {
				$callback = $pluginCallback;
			}
			$this->emitter->on($event, $callback);
		}

		$this->message = 'One Night Revolution created! Type !join to join in!';
	}

	public function getSubscribedEvents()
	{
		return [
			 'command.action' => [$this, 'handleAction'],
			 'command.declare' => [$this, 'handleDeclare'],
			 'command.cheatsheet' => [$this, 'handleCheatsheet']
		];
	}

	public function getPhase()
	{
		return $this->phase;
	}

	public function getMessage()
	{
		return $this->message;
	}

	public function start()
	{
		switch(count($this->players))
		{
			case 10:
				break;
			case 9:
				$this->specialties['Reassigner']--;
				$this->ids['Rebel']--;
			case 8:
				$this->specialties['Thief']--;
				$this->ids['Rebel']--;
			case 7:
				$this->specialties['Observer']--;
				$this->ids['Rebel']--;
			case 6:
				$this->specialties['Signaler']--;
				$this->ids['Rebel']--;
			case 5:
				$this->specialties['Investigator']--;
				$this->ids['Rebel']--;
			case 4:
				$this->specialties['Observer']--;
				$this->ids['Rebel']--;
			case 3:
				$this->specialties['Signaler']--;
				$this->ids['Rebel']--;
				break;
			case 2:
			case 1:
			case 0:
				$this->message = 'Not enough players.';
				return;
				break;
		}

		GameBot::shuffle_assoc($this->players);

		foreach ($this->players as $key => $value) {
			GameBot::shuffle_assoc($this->specialties);

			$specialty = key($this->specialties);
			$this->players[$key]['specialist'] = $specialty;
			$this->specialties[$specialty]--;

			if ($this->specialties[$specialty] === 0) {
				unset($this->specialties[$specialty]);
			}

			GameBot::shuffle_assoc($this->ids);

			$id = key($this->ids);
			$this->players[$key]['id'] = $id;
			$this->ids[$id]--;

			if ($this->ids[$id] === 0) {
				unset($this->ids[$id]);
			}
		}

		foreach ($this->specialties as $key => $value) {
			for ($i = 0; $i < $value; $i++) {
				$this->facedownCards[] = $key;
			}
		}

		GameBot::shuffle_assoc($this->hq);
		GameBot::shuffle_assoc($this->players);

		$this->message = 'Game started! Check notices for IDs and Specialties.';
		$this->queue->ircPrivmsg($this->channel, $this->message);

		$this->phase = 'informantReveal';
		$this->runPhase();
	}

	public function addPlayer(string $playerName)
	{
		if (isset($this->players[$playerName])) {
			return false;
		} else {
			$this->players[$playerName] = ['id' => '', 'specialist' => ''];
			return true;
		}
	}

	public function runPhase()
	{
		switch ($this->phase)
		{
			case 'informantReveal':
				$this->informantsReveal();
				break;
			case 'nightActions':
				$this->nightActions();
				break;
		}
	}

	private function informantsReveal()
	{
		$informants = [];
		foreach ($this->players as $playerName => $player) {
			if ($this->players[$playerName]['id'] === 'Informant') {
				$informants[] = $playerName;
				$this->queue->ircNotice($playerName, 'You are an Informant '.$this->players[$playerName]['specialist'].'.');
			} else {
				$this->queue->ircNotice($playerName, 'You are a Rebel '.$this->players[$playerName]['specialist'].'.');
			}
		}

		foreach ($informants as $playerName) {
			if ($this->players[$playerName]['specialist'] !== 'DeepAgent')
			$this->queue->ircNotice($playerName, 'The informants are: '.implode(', ', $informants));
		}

		$this->phase = 'nightActions';
		$this->runPhase();
	}

	public function nightActions()
	{
		$this->message = "Players will take their actions in this order: ";
		foreach ($this->players as $playerName => $player) {
			$this->message .= $playerName .', ';
		}

		$this->message = substr($this->message, -2);
		$this->queue->ircPrivmsg($this->channel, $this->message);

		foreach ($this->players as $playerName => $player) {
			switch ($player['specialist']) {
				case 'Analyst':
					$this->queue->ircNotice($playerName, 'View another player\'s specialist card. !action <playername>');
					break;
				case 'Confirmer':
					$this->queue->ircNotice($playerName, 'View your ID. You will be sent a notice as soon as it is your turn.');
					break;
				case 'DeepAgent':
					$this->queue->ircNotice($playerName, 'No action during the night phase.');
					break;
				case 'Defetor':
					if ($player['id'] === 'Rebel') {
						$this->queue->ircNotice($playerName, 'Switch your ID with an HQ Informant ID. !action <1, 2, or 3>');
					} else {
						$this->queue->ircNotice($playerName, 'View your ID. You will be sent a notice as soon as it is your turn.');
					}
					break;
				case 'Investigator':
					$this->queue->ircNotice($playerName, 'Look at another player\'s ID. !action <playername>');
					break;
				case 'Observer':
					$this->queue->ircNotice($playerName, 'No action during the night phase.');
					break;
				case 'Reassigner':
					if ($player['id'] === 'Rebel') {
						$this->queue->ircNotice($playerName, 'Switch two other player\'s IDs. !action <playername 1> <playername 2>');
					} else {
						$this->queue->ircNotice($playerName, 'Switch a Rebel player\'s ID with an HQ Informant ID. !action <playername> <1, 2, or 3>');
					}
					break;
				case 'Revealer':
					$this->queue->ircNotice($playerName, 'Flip another player\'s ID face up. If it\'s an Informant flip it back down. !action <playername>');
					break;
				case 'Signaller':
					if ($player['id'] === 'Rebel') {
						$this->queue->ircNotice($playerName, 'Tap the player on your immediate left or right. !action <left or right>');
					} else {
						$this->queue->ircNotice($playerName, 'Tab an informant on your immediate left or right. !action <left or right>');
					}
					break;
				case 'Rogue':
					if ($player['id'] === 'Rebel') {
						$this->queue->ircNotice($playerName, 'View your ID. You will be sent a notice as soon as it is your turn.');
					} else {
						$this->queue->ircNotice($playerName, 'Switch a Rebel player\'s ID with another informant player\'s ID. !action <playername 1> <playername 2>');
					}
					break;
				case 'Thief':
					if ($player['id'] === 'Rebel') {
						$this->queue->ircNotice($playerName, 'Switch your ID with another player\'s ID. You will be sent a notice of your current ID as soon as it is your turn. !action <playername>');
					} else {
						$this->queue->ircNotice($playerName, 'View your ID. You will be sent a notice as soon as it is your turn.');
					}
					break;
			}
		}
	}

	public function handleAction(Event $event, Queue $queue)
	{
		$player = $event->getNick();
	}

	public function handleDeclare(Event $event, Queue $queue)
	{

	}

	public function handleCheatsheet(Event $event, Queue $queue)
	{
        $nick = $event->getNick();
		$this->queue->ircPrivmsg($nick, 'Available specialties and their actions (if two actions are given they are in the order of Rebel / Informant): ');
		$this->queue->ircPrivmsg($nick, 'Analyst: View another player\'s specialist card.');
		$this->queue->ircPrivmsg($nick, 'Confirmer: View your ID.');
		$this->queue->ircPrivmsg($nick, 'DeepAgent: No specialist action. / Raise your thumb, but do not open your eyes during informant reveal.');
		$this->queue->ircPrivmsg($nick, 'Defector: Switch your ID with an HQ Informant ID. / View your ID.');
		$this->queue->ircPrivmsg($nick, 'Investigator: Look at another player\'s ID.');
		$this->queue->ircPrivmsg($nick, 'Observer: No specialist action.');
		$this->queue->ircPrivmsg($nick, 'Reassigner: Switch two other player\'s IDs. / Switch a Rebel player\'s ID with an HQ Informant ID.');
		$this->queue->ircPrivmsg($nick, 'Revealer: Flip another player\'s ID face up. If it\'s an Informant flip it back down.');
		$this->queue->ircPrivmsg($nick, 'Signaller: Tap the player on your immediate left or right. / Tab an informant on your immediate left or right.');
		$this->queue->ircPrivmsg($nick, 'Rogue: View your ID / Switch a Rebel player\'s ID with another informant player\'s ID.');
		$this->queue->ircPrivmsg($nick, 'Thief: Switch your ID with another player\'s ID. View your new ID. / View your ID.');
	}
}