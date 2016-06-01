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
	private $availableSpecialties = null;
	private $ids = ['Rebel' => 10, 'Informant' => 3];
	private $message = null;
	private $queue = null;
	private $channel = null;
	private $emitter = null;
	private $currentPlayer = null;

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
			 'command.vote' => [$this, 'handleVote'],
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
		$this->queue->ircPrivmsg($this->channel, 'Game started! Check notices for IDs and Specialties.');

		switch(count($this->players))
		{
			case 10:
				break;
			case 3:
				$this->specialties['Signaler']--;
				$this->ids['Rebel']--;
			case 4:
				$this->specialties['Observer']--;
				$this->ids['Rebel']--;
			case 5:
				$this->specialties['Investigator']--;
				$this->ids['Rebel']--;
			case 6:
				$this->specialties['Signaler']--;
				$this->ids['Rebel']--;
			case 7:
				$this->specialties['Observer']--;
				$this->ids['Rebel']--;
			case 8:
				$this->specialties['Thief']--;
				$this->ids['Rebel']--;
			case 9:
				$this->specialties['Reassigner']--;
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
		$this->activeSpecialties = [];

		foreach ($this->players as $key => $value) {
			GameBot::shuffle_assoc($this->specialties);

			$specialty = key($this->specialties);
			$this->players[$key]['specialist'] = $specialty;
			$this->specialties[$specialty]--;

			if (isset($this->activeSpecialties[$specialty])) {
				$this->activeSpecialties[$specialty]++;
			} else {
				$this->activeSpecialties[$specialty] = 1;
			}

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
			case 'nightActionsPhase':
				$this->nightActions();
				break;
			case 'declarePhase':
				$this->declarePhase();
				break;
			case 'votePhase':
				$this->votePhaes();
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
			if ($this->players[$playerName]['specialist'] !== 'DeepAgent') {
				$this->queue->ircNotice($playerName, 'The informants are: '.implode(', ', $informants));
			}
		}

		$this->queue->ircPrivmsg($this->channel, 'Night phase has begun. Each player will be given their missions privately. Complete your mission when it is assigned.');
		$this->phase = 'nightActionsPhase';
		$this->runPhase();
	}

	private function getNextPlayer(string $nextPhase, string $phaseChangeMessage) {
		$foundPlayer = false;

		foreach ($this->players as $playerName => $player) {
			if ($this->currentPlayer === null || !isset($this->players[$playerName]['action'])) {
				$this->currentPlayer = $playerName;
				$first = true;

				echo 'Current player is: '.$playerName.PHP_EOL;
				var_dump($this->players[$playerName]);

				$foundPlayer = true;
				break;
			}
		}

		if (!$foundPlayer) {
			$this->phase = $nextPhase;
			$this->queue->ircPrivmsg($this->channel, $phaseChangeMessage);
			$this->runPhase();
		}

		return $foundPlayer;
	}

	private function nightActions()
	{
		if (!$this->getNextPlayer('declarePhase', 'The night has ended. When it is your turn, declare a specialty.')) {
			return;
		}

		switch ($this->players[$this->currentPlayer]['specialist']) {
			case 'Analyst':
				$this->queue->ircNotice($this->currentPlayer, 'View another player\'s specialist card. Send me a private message with !action <playername>');
				break;
			case 'Confirmer':
				$this->queue->ircNotice($this->currentPlayer, 'Your current ID: '.$this->players[$this->currentPlayer]['id']);
				break;
			case 'DeepAgent':
				$this->queue->ircNotice($this->currentPlayer, 'No action during the night phase. Send me a private message with !action');
				break;
			case 'Defetor':
				if ($player['id'] === 'Rebel') {
					$this->queue->ircNotice($this->currentPlayer, 'Switch your ID with an HQ Informant ID. Send me a private message with !action <1, 2, or 3>');
				} else {
					$this->queue->ircNotice($this->currentPlayer, 'Your current ID: '.$this->players[$this->currentPlayer]['id'].'. Send me a private message with !action');
				}
				break;
			case 'Investigator':
				$this->queue->ircNotice($this->currentPlayer, 'Look at another player\'s ID. Send me a private message with !action <playername>');
				break;
			case 'Observer':
				$this->queue->ircNotice($this->currentPlayer, 'No action during the night phase. Send me a private message with !action');
				break;
			case 'Reassigner':
				if ($player['id'] === 'Rebel') {
					$this->queue->ircNotice($this->currentPlayer, 'Switch two other player\'s IDs. Send me a private message with !action <playername 1> <playername 2>');
				} else {
					$this->queue->ircNotice($this->currentPlayer, 'Switch a Rebel player\'s ID with an HQ Informant ID. Send me a private message with !action <playername> <1, 2, or 3>');
				}
				break;
			case 'Revealer':
				$this->queue->ircNotice($this->currentPlayer, 'Flip another player\'s ID face up. If it\'s an Informant flip it back down. Send me a private message with !action <playername>');
				break;
			case 'Signaller':
				if ($player['id'] === 'Rebel') {
					$this->queue->ircNotice($this->currentPlayer, 'Tap the player on your immediate left or right. Send me a private message with !action <left or right>');
				} else {
					$leftPlayer = null;
					$rightPlayer = null;
					foreach ($this->players as $foundPlayerName => $foundPlayer) {
						if ($foundPlayerName === $playerName) {
							$rightPlayer = true;
						} else if ($rightPlayer === null) {
							$leftPlayer = $foundPlayerName;
						} else {
							$rightPlayer = $foundPlayerName;
							break;
						}
					}

					if ($this->players[$leftPlayer]['id'] !== 'Informant' && $this->players[$rightPlayer]['id'] !== 'Informant') {
						$this->queue->ircNotice($this->currentPlayer, 'There are no informants to your left or right. Send me a private message with !action');
					} else {
						$this->queue->ircNotice($this->currentPlayer, 'Tap an informant on your immediate left or right. Send me a private message with !action <left or right>');
					}
				}
				break;
			case 'Rogue':
				if ($player['id'] === 'Rebel') {
					$this->queue->ircNotice($plathis->currentPlayeryerName, 'Your current ID: '.$this->players[$this->currentPlayer]['id'].'.  Send me a private message with !action');
				} else {
					$this->queue->ircNotice($this->currentPlayer, 'Switch a Rebel player\'s ID with another informant player\'s ID. Send me a private message with !action <rebel playername> <informant playername>');
				}
				break;
			case 'Thief':
				if ($player['id'] === 'Rebel') {
					$this->queue->ircNotice($this->currentPlayer, 'Switch your ID with another player\'s ID. You will be sent a notice with your new ID. Send me a private message with !action <playername>');
				} else {
					$this->queue->ircNotice($this->currentPlayer, 'Your current ID: '.$this->players[$this->currentPlayer]['id'].'. Send me a private message with !action');
				}
				break;
		}
	}

	public function handleAction(Event $event, Queue $queue)
	{
		$playerName = $event->getNick();
		$player = $this->players[$playerName];

		//Don't accept events unless it is through a private message
		if ($event->getSource() !== $playerName) {
			return;
		}

		if ($this->currentPlayer !== $playerName) {
			return;
		}

		if ($this->phase !== 'nightActionsPhase') {
			return;
		}

		$args = $event->getCustomParams();

		echo 'Active player: '.$playerName.PHP_EOL;
		var_dump($args);

		switch ($player['specialist']) {
			case 'Analyst':
				if (!isset($this->players[$args[0]])) {
					$this->queue->ircNotice($playerName, 'Player not in the current game. Choose another player name.');
				} else {
					$this->players[$playerName]['action'] = $args[0];
					$this->queue->ircNotice($playerName, $args[0].' is a '.$this->players[$arg[0]]['specialist']);
				}
				break;
			case 'Confirmer':
				$this->players[$playerName]['action'] = 'none';
				break;
			case 'DeepAgent':
				$this->players[$playerName]['action'] = 'none';
				break;
			case 'Defetor':
				if ($player['id'] === 'Rebel') {
					if ($args[0] === '1' || $args[0] === '2' || $args[0] === '3') {
						$this->queue->ircNotice($playerName, 'HQ ID #'.$args[0].' is a '.$this->hq[$args[0]]);
						if ($this->hq[$args[0]] === 'Informant') {
							$this->player[$playerName]['action'] = 'Informant';
						} else {
							$this->queue->ircNotice($playerName, 'Choose another HQ card until an Informant is found.');
						}
					} else {
						$this->queue->ircNotice($playerName, 'Invalid HQ ID #. Choose 1, 2, or 3.');
					}
				} else {
					$this->players[$playerName]['action'] = 'none';
				}
				break;
			case 'Investigator':
				if (!isset($this->players[$args[0]])) {
					$this->queue->ircNotice($playerName, 'Player not in the current game. Choose another player name.');
				} else {
					$this->players[$playerName]['action'] = $args[0];
					$this->queue->ircNotice($playerName, $args[0].' is a '.$this->players[$args[0]]['id']);
				}
				break;
			case 'Observer':
				$this->players[$playerName]['action'] = 'none';
				break;
			case 'Reassigner':
				if ($player['id'] === 'Rebel') {
					if (!isset($this->players[$args[0]]) && !isset($this->players[$args[1]]) && $args[0] !== $args[1]) {
						$this->queue->ircNotice($playerName, 'One of the players is not in the current game. Choose another player name. Both players cannot be the same.');
					} else {
						$swap = $this->players[$args[0]];
						$this->players[$args[0]]['id'] = $this->players[$args[1]]['id'];
						$this->players[$args[1]]['id'] = $swap;
						$this->players[$playerName]['action'] = $args[0].' and '.$args[1];
					}
				} else {
					if (!isset($this->players[$args[0]])) {
						$this->queue->ircNotice($playerName, 'Player not in the current game. Choose another player name.');
					} else if ($args[1] === '1' || $args[1] === '2' || $args[1] === '3') {
						$this->queue->ircNotice($playerName, 'HQ ID #'.$args[1].' is a '.$this->hq[$args[1]]);
						if ($this->hq[$args[1]] === 'Informant') {
							$this->player[$args[0]]['action'] = 'Informant';
						} else {
							$this->queue->ircNotice($playerName, 'Choose another HQ card until an Informant is found.');
						}
					} else {
						$this->queue->ircNotice($playerName, 'Invalid HQ ID #. Choose 1, 2, or 3.');
					}
				}
				break;
			case 'Revealer':
				if (!isset($this->players[$args[0]])) {
					$this->queue->ircNotice($playerName, 'Player not in the current game. Choose another player name.');
				} else {
					$this->players[$playerName]['action'] = $args[0];
					$this->queue->ircNotice($playerName, $args[0].' is a '.$this->players[$arg[0]]['id']);
					if ($this->players[$args[0]]['id'] !== 'Informant') {
						$this->queue->ircPrivmsg($this->channel, $args[0].'\'s ID has been flipped face up. '.$args[0].' was a Rebel.');
					}
				}
				break;
			case 'Signaller':
				if ($player['id'] === 'Rebel') {
					if ($args[0] === 'left') {
						$foundPlayer = false;
						foreach ($this->players as $foundPlayerName => $player) {
							if ($playerName === $foundPlayerName) {
								$foundPlayer = true;
							} else if ($foundPlayer) {
								$this->queue->ircNotice($foundPlayerName, 'You have been tapped by a signaller.');
								$this->players[$playerName]['action'] = $args[0];
								break;
							}
						}
					} else if ($args[0] === 'right') {
						$foundPlayer = null;
						foreach ($this->players as $foundPlayerName => $player) {
							if ($playerName === $foundPlayerName) {
								$this->queue->ircNotice($foundPlayer, 'You have been tapped by a signaller.');
								$this->players[$playerName]['action'] = $args[0];
								break;
							} else {
								$foundPlayer = $foundPlayerName;
							}
						}
					} else {
						$this->queue->ircNotice($playerName, 'Invalid tap target. Choose left or right.');
					}
				} else {
					if ($args[0] === 'left') {
						$foundPlayer = false;
						foreach ($this->players as $foundPlayerName => $player) {
							if ($playerName === $foundPlayerName) {
								$foundPlayer = true;
							} else if ($foundPlayer) {
								if ($this->players[$foundPlayer]['id'] === 'Informant') {
									$this->queue->ircNotice($foundPlayerName, 'You have been tapped by a signaller.');
									$this->players[$playerName]['action'] = $args[0];
								} else {
									$this->queue->ircNotice($playerName, 'The chosen player is not an Informant.');
								}
								break;
							}
						}
					} else if ($args[0] === 'right') {
						$foundPlayer = null;
						foreach ($this->players as $foundPlayerName => $player) {
							if ($playerName === $foundPlayerName) {
								if ($this->players[$foundPlayer]['id'] === 'Informant') {
									$this->queue->ircNotice($foundPlayer, 'You have been tapped by a signaller.');
									$this->players[$playerName]['action'] = $args[0];
								} else {
									$this->queue->ircNotice($playerName, 'The chosen player is not an Informant.');
								}
								break;
							} else {
								$foundPlayer = $foundPlayerName;
							}
						}
					} else {
						$this->queue->ircNotice($playerName, 'Invalid tap target. Choose left or right.');
					}
				}
				break;
			case 'Rogue':
				if ($player['id'] === 'Informant') {
					if (!isset($this->players[$args[0]])) {
						$this->queue->ircNotice($playerName, 'Player not in the current game. Choose another player name.');
					} else if ($this->players[$args[0]]['id'] !== 'Rebel') {
						$this->queue->ircNotice($playerName, 'Player is not a Rebel. Choose another player name.');
					} else if ($this->players[$args[1]]['id'] !== 'Informant') {
						$this->queue->ircNotice($playerName, 'Player is not an Informant. Choose another player name.');
					} else {
						$this->players[$playerName]['action'] = $args[0];
						$swap = $this->players[$args[0]]['id'];
						$this->players[$args[0]]['id'] = $this->players[$args[1]]['id'];
						$this->players[$args[1]]['id'] = $swap;
					}
				} else {
					$this->players[$playerName]['action'] = 'none';
				}
				break;
			case 'Thief':
				if ($player['id'] === 'Rebel') {
					if (!isset($this->players[$args[0]])) {
						$this->queue->ircNotice($playerName, 'Player not in the current game. Choose another player name.');
					} else {
						$swap = $this->players[$args[0]];
						$this->players[$args[0]]['id'] = $this->players[$playerName]['id'];
						$this->players[$playerName]['id'] = $swap;
						$this->players[$playerName]['action'] = $args[0];
						$this->queue->ircNotice($playerName, 'Your new ID is: '.$this->players[$playerName]['id']);
					}
				} else {
					$this->players[$playerName]['action'] = 'none';
				}
				break;
		}

		if (isset($this->players[$playerName]['action'])) {
			$this->runPhase();
		}
	}

	private function declarePhase() {
		if (!$this->getNextPlayer('dayPhase', 'The declare phase has ended and the day phase has started. Discuss the events of the night. When ready to vote private message me with !vote <playername>')) {
			return;
		}

		$availableSpecialties = implode(', ', $this->availableSpecialties);
		$this->queue->ircPrivmsg($channel, $this->currentPlayer.': Declare your specialty with !declare <'.$availableSpecialties.'>');
	}

	public function handleDeclare(Event $event, Queue $queue)
	{
		$playerName = $event->getNick();
		$player = $this->players[$playerName];

		//Don't accept events unless it is through a public message
		if ($event->getSource() !== $channel) {
			return;
		}

		if ($this->currentPlayer !== $playerName) {
			return;
		}

		if ($this->phase !== 'declarePhase') {
			return;
		}

		$args = $event->getCustomParams();

		echo 'Active player: '.$playerName.PHP_EOL;
		var_dump($args);

		$args[0] =  ucfirst(strtolower($args[0]));

		if (isset($this->availableSpecialties[$args[0]])) {
			$this->players[$playerName]['declare'] = $args[0];
		} else {
			$this->queue->ircNotice($playerName, 'That specialty is unavailable for declaration.');
		}

		if (isset($this->players[$playerName]['declare'])) {
			$this->runPhase();
		}
	}

	public function handleVote(Event $event, Queue $queue)
	{
		$playerName = $event->getNick();

		if (isset($this->players[$playerName])) {
			$player = $this->players[$playerName];
		} else {
			return;
		}

		if ($this->phase !== 'dayPhase') {
			return;
		}

		$args = $event->getCustomParams();

		echo 'Active player: '.$playerName.PHP_EOL;
		var_dump($args);

		$args[0] =  strtolower($args[0]);

		$this->players[$playerName]['vote'] = $args[0];

		$allVotes = true;

		foreach ($this->players as $playerName => $player) {
			if (!isset($this->players[$playerName]['vote'])) {
				$allVotes = false;
				break;
			}
		}

		if ($allVotes) {
			$this->phase = 'votePhase';
			$this->runPhase();
		}
	}

	private function votePhase() {
		$votes = [];

		foreach ($this->players as $playerName => $player) {
			if (isset($votes[$this->players[$playerName]['vote'])) {
				$votes[$this->players[$playerName]['vote']]++;
			} else {
				$votes[$this->players[$playerName]['vote']] = 1;
			}
		}

		$highestVoteCount = 0;
		$terminated = [];
		$rebelsWin = false;

		foreach ($votes as $player => $count) {
			if ($count > $highestVoteCount) {
				unset($terminated);
				$terminated = [];
				$terminated[] = $player;
			} else if ($count == $highestVoteCount) {
				$terminated[] = $player;
			}
		}

		$rebelsWin = null;

		if (count($terminated) === count($this->players)) {
			$terminatedMessage = 'No one is terminated.';
			$rebelsWin = true;

			foreach ($this->players as $playerName => $player) {
				if ($this->players[$playerName]['id'] === 'Informant') {
					$rebelsWin = false;
					break;
				}
			}
		} else if (count($terminated) >= 2) {
			$terminatedMessage = 'The following players are terminated: '.implode(', ', $terminated);
			$rebelsWin = false;

			foreach ($this->players as $playerName => $player) {
				foreach ($terminated as $terminatedPlayerName) {
					if (strtolower($playerName) === $terminatedPlayerName && $this->players[$playerName]['id'] === 'Informant') {
						$rebelsWin = true;
					}
				}
			}
		} else {
			$terminatedMessage = $terminated[0].' is terminated.';
			$rebelsWin = false;

			foreach ($this->players as $playerName => $player) {
				if (strtolower($playerName) === $terminated[0] && $this->players[$playerName]['id'] === 'Informant') {
					$rebelsWin = true;
				}
			}
		}

		$this->queue->ircPrivmsg($channel, 'The votes are in. '.$terminatedMessage);

		if ($rebelsWin) {
			$this->queue->ircPrivmsg($channel, 'The Rebels win!');
		} else {
			$this->queue->ircPrivmsg($channel, 'The Informants win!')
		}
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