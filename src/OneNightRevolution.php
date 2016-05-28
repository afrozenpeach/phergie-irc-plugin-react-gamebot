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
	private $currentController = null;
	private $specialties = ['Reassigner' => 2, 'Investigator' => 2, 'Thief' => 2, 'Signaler' => 2, 'Observer' => 2];
	private $ids = ['Rebel' => 10, 'Informant' => 3];
	private $message = null;
	private $queue = null;
	private $emitter = null;
	
	private $facedownCards = array();
	
	public function __construct(Queue $queue, Client $emitter)
	{
		$this->phase = 'pregame';
		$this->queue = $queue;
		$this->emitter = $emitter;
		
		$callbacks = $this->getSubscribedEvents();
		
		foreach ($callbacks as $event => $callback) {
			$pluginCallback = [ $this, $callback ];
			if (is_callable($pluginCallback)) {
				$callback = $pluginCallback;
			}
			$this->emitter->on($event, $callback);
		}
		
		$this->message = 'One Night Revolution Started! Type !join to join in!';
	}
	
	public function getSubscribedEvents()
	{
		return [
			 'command.customcommand' => [$this, 'handleCustomCommand']
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
			$this->players['id'] = $id;
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
		
		GameBot::shuffle_assoc($this->facedownCards);		
		GameBot::shuffle_assoc($this->players);
		
		$this->phase = 'informantreveal';
	}
	
	public function addPlayer(string $playerName) {
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
			case 'informantsreveal':
				$this->informantsReveal();
				break;
		}
	}
	
	public function informantsReveal() {
		$informants = [];
		foreach ($this->players as $playerName => $player) {
			if ($player['id'] === 'Informant') {
				$informants[] = $playerName;
				$this->queue->ircNotice($playerName, 'You are an informant. Your specialty is: '.$player['specialist']);
			}
			else
				$this->queue->ircNotice($playerName, 'You are a Rebel. Your specialty is: '.$player['specialist'])
		}
		
		foreach ($informants as $playerName) {
			$this->queue->ircNotice($playerName, 'The other informants are: '.implode(', ', $informants));
		}
	}
	
	public function handleCustomCommand(Event $event, Queue $queue)
	{
		$queue->ircPrivmsg($event->getSource(), 'hello');
	}
}