<?php
/**
 * ManiaLive - TrackMania dedicated server manager in PHP
 *
 * @copyright   Copyright (c) 2009-2011 NADEO (http://www.nadeo.com)
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 * @version     $Revision$:
 * @author      $Author$:
 * @date        $Date$:
 */

namespace ManiaLive\Data;

use ManiaLive\Utilities\String;
use ManiaLive\Application\SilentCriticalEventException;
use ManiaLive\Application\CriticalEventException;
use ManiaLive\DedicatedApi\Structures\Challenge;
use ManiaLive\Event\Dispatcher;
use ManiaLive\Utilities\Console;
use ManiaLive\DedicatedApi\Structures\Player;
use ManiaLive\DedicatedApi\Connection;

/**
 * Contain every important data about the server
 */
class Storage extends \ManiaLive\Utilities\Singleton implements \ManiaLive\DedicatedApi\Callback\Listener, \ManiaLive\Application\Listener
{
	protected $disconnetedPlayers = array();

	/**
	 * Contains Player object. It represents the player connected to the server
	 * @var \ManiaLive\DedicatedApi\Structures\Player[]
	 */
	public $players = array();
	/**
	 * Contains Player object. It represents the spectators connected to the server
	 * @var \ManiaLive\DedicatedApi\Structures\Player[]
	 */
	public $spectators = array();
	/**
	 * Contains Player object. It represents the current ranking on the server
	 * @var \ManiaLive\DedicatedApi\Structures\Player[]
	 */
	public $ranking = array();
	/**
	 * Contains Challenge objects. It represents the current challenges available on the server
	 * @var \ManiaLive\DedicatedApi\Structures\Challenge[]
	 */
	public $challenges;
	/**
	 * Represents the current Challenge object
	 * @var \ManiaLive\DedicatedApi\Structures\Challenge
	 */
	public $currentChallenge;
	/**
	 * Represents the next Challenge object
	 * @var \ManiaLive\DedicatedApi\Structures\Challenge
	 */
	public $nextChallenge;
	/**
	 * Represents the Current Server Options 
	 * @var \ManiaLive\DedicatedApi\Structures\ServerOptions
	 */
	public $server;
	/**
	 * Represents the Current Game Infos
	 * @var \ManiaLive\DedicatedApi\Structures\GameInfos
	 */
	public $gameInfos;
	/**
	 * Represents the current Server Status
	 * @var \ManiaLive\DedicatedApi\Structures\Status
	 */
	public $serverStatus;
	/**
	 * Contains the server login
	 * @var string
	 */
	public $serverLogin;

	/**
	 * @return \ManiaLive\Data\Storage
	 */
	static function getInstance()
	{
		return parent::getInstance();
	}

	protected function __construct()
	{
		\ManiaLive\Event\Dispatcher::register(\ManiaLive\DedicatedApi\Callback\Event::getClass(), $this);
		\ManiaLive\Event\Dispatcher::register(\ManiaLive\Application\Event::getClass(), $this);
	}

	#region Implementation de l'applicationListener
	function onInit()
	{
		$connection = Connection::getInstance();
		$this->serverStatus = $connection->getStatus();

		$players = $connection->getPlayerList(-1, 0);
		foreach ($players as $player)
		{
			try
			{
				$details = $connection->getDetailedPlayerInfo($player->login);

				foreach ($details as $key => $value)
				{
					if($value)
					{
						$param = lcfirst($key);
						$player->$param = $value;
					}
				}

				if($player->spectatorStatus % 10 == 0)
				{
					$this->players[$player->login] = $player;
				}
				else
				{
					$this->spectators[$player->login] = $player;
				}
			}
			catch (\Exception $e) {}
		}

		$this->challenges = $connection->getChallengeList(-1,0);
		$currentIndex = $connection->getCurrentChallengeIndex();
		$nextIndex = $connection->getNextChallengeIndex();
		$this->nextChallenge = $this->challenges[$nextIndex];
		$this->currentChallenge = $connection->getCurrentChallengeInfo();

		$this->server = $connection->getServerOptions();
		$this->gameInfos = $connection->getCurrentGameInfo();
		$this->serverLogin = $connection->getMainServerPlayerInfo()->login;
		
		Console::printlnFormatted('Current map: ' . String::stripAllTmStyle($this->currentChallenge->name));
	}

	function onRun() {}
	function onPreLoop() {}

	function onPostLoop()
	{
		foreach ($this->disconnetedPlayers as $key => $login)
		{
			if(array_key_exists($login, $this->spectators) && !$this->spectators[$login]->isConnected)
			{
				$this->spectators[$login] = null;
				unset($this->spectators[$login]);
			}
			elseif (array_key_exists($login, $this->players) && !$this->players[$login]->isConnected)
			{
				$this->players[$login] = null;
				unset($this->players[$login]);
			}
			unset($this->disconnetedPlayers[$key]);
		}
	}

	function onTerminate() {}
	#endRegion

	#region Implementation of DedicatedApi\Listener
	function onPlayerConnect($login, $isSpectator)
	{
		try
		{
			$playerInfos = Connection::getInstance()->getPlayerInfo($login, 1);
			$details = Connection::getInstance()->getDetailedPlayerInfo($login);

			foreach ($details as $key => $value)
			{
				if($value)
				{
					$param = lcfirst($key);
					$playerInfos->$param = $value;
				}
			}

			if($isSpectator)
			{
				$this->spectators[$login] = $playerInfos;
			}
			else
			{
				$this->players[$login] = $playerInfos;
			}
		}

		// if player can not be added to array, then we stop the onPlayerConnect event!
		catch (\Exception $e)
		{
			if ($e->getCode() == -1000 && $e->getMessage() == 'Login unknown.')
			{
				throw new SilentCriticalEventException($e->getMessage());
			}
			else
			{
				throw new CriticalEventException($e->getMessage());
			}
		}
	}

	function onPlayerDisconnect($login)
	{
		$this->disconnetedPlayers[] = $login;
		
		if(array_key_exists($login, $this->players))
		{
			$this->players[$login]->isConnected = false;
		}
		elseif(array_key_exists($login, $this->spectators))
		{
			$this->spectators[$login]->isConnected = false;
		}
		
		foreach($this->ranking as $key => $player)
		{
			if($player->login == $login)
			{
				unset($this->ranking[$key]);
			}
		}
	}

	function onPlayerChat($playerUid, $login, $text, $isRegistredCmd) {}
	function onPlayerManialinkPageAnswer($playerUid, $login, $answer) {}
	function onEcho($internal, $public) {}
	function onServerStart() {}
	function onServerStop() {}
	function onBeginRace($challenge)
	{
		$gameInfos = Connection::getInstance()->getCurrentGameInfo();
		if($gameInfos != $this->gameInfos)
		{
			foreach ($gameInfos as $key => $value)
			{
				$this->gameInfos->$key = $value;
			}
		}

		$serverOptions = Connection::getInstance()->getServerOptions();
		if($serverOptions != $this->server)
		{
			foreach ($serverOptions as $key => $value)
			{
				$this->server->$key = $value;
			}
		}
	}

	function onEndRace($rankings, $challenge)
	{
		$rankings = Player::fromArrayOfArray($rankings);
		$this->updateRanking($rankings);
	}

	function onBeginChallenge($challenge, $warmUp, $matchContinuation)
	{
		$oldChallenge = $this->currentChallenge;
		$this->currentChallenge = Challenge::fromArray($challenge);
		Console::printlnFormatted('Map change: ' . String::stripAllTmStyle($oldChallenge->name) . ' -> ' . String::stripAllTmStyle($this->currentChallenge->name));
		
		foreach ($this->players as $player)
		{
			$player->bestTime = 0;
			$player->score = 0;
		}
		
		foreach ($this->spectators as $spectator)
		{
			$spectator->bestTime = 0;
			$spectator->score = 0;
		}
	}

	function onEndChallenge($rankings, $challenge, $wasWarmUp, $matchContinuesOnNextChallenge, $restartChallenge)
	{
		if(!$wasWarmUp)
		{
			$rankings = Player::fromArrayOfArray($rankings);
			$this->updateRanking($rankings);
		}
	}

	function onBeginRound() {}

	function onEndRound()
	{
		// TODO find a better way to handle the -1000 "no race in progress" error ...
		try
		{
			if(count($this->players) || count($this->spectators))
			{
				$rankings = Connection::getInstance()->getCurrentRanking(-1, 0);
				$this->updateRanking($rankings);
			}
		}
		catch (\Exception $ex) {}
	}

	function onStatusChanged($statusCode, $statusName)
	{
		$this->serverStatus->code = $statusCode;
		$this->serverStatus->name = $statusName;
	}

	function onPlayerCheckpoint($playerUid, $login, $timeOrScore, $curLap, $checkpointIndex) {}

	function onPlayerFinish($playerUid, $login, $timeOrScore)
	{
		if (!isset($this->players[$login]))
		{
			return;
		}
		$player = $this->players[$login];

		switch ($this->gameInfos->gameMode)
		{
			case Connection::GAMEMODE_STUNTS:
				if (($timeOrScore > $player->score || $player->score <= 0) && $timeOrScore > 0)
				{
					$old_score = $player->score;
					$player->score = $timeOrScore;

					$rankings = Connection::getInstance()->getCurrentRanking(-1, 0);
					$this->updateRanking($rankings);

					if ($player->score == $timeOrScore)
					{
						// sanity checks
						$nbChecks = count($player->bestCheckpoints);
						switch ($this->gameInfos->gameMode)
						{
							case 0:
							case 2:
							case 5:
								$totalChecks = $this->currentChallenge->nbCheckpoints * $this->currentChallenge->nbLaps;
								if ($nbChecks != $totalChecks)
								{
									Console::println('Best score\'s checkpoint count does not match and was ignored!');
									Console::printPlayerScore($player);
									$player->score = $old_score;
									return;
								}
								break;
								
							case 1:
							case 3:
							case 4:
								if ($nbChecks != $this->currentChallenge->nbCheckpoints)
								{
									Console::println('Best score\'s checkpoint count does not match and was ignored!');
									Console::printPlayerScore($player);
									$player->score = $old_score;
									return;
								}
								break;
						}
						
						Dispatcher::dispatch(new Event($this, Event::ON_PLAYER_NEW_BEST_SCORE, array($player, $old_score, $timeOrScore)));
					}
				}
				break;

			default:
				if (($timeOrScore < $player->bestTime || $player->bestTime <= 0) && $timeOrScore > 0)
				{
					$old_best = $player->bestTime;
					$player->bestTime = $timeOrScore;
					
					$rankings = Connection::getInstance()->getCurrentRanking(-1, 0);
					$this->updateRanking($rankings);
					
					if ($player->bestTime == $timeOrScore)
					{
						// sanity checks
						$nbChecks = count($player->bestCheckpoints);
						switch ($this->gameInfos->gameMode)
						{
							case 0:
							case 2:
							case 5:
								$totalChecks = $this->currentChallenge->nbCheckpoints * $this->currentChallenge->nbLaps;
								if ($nbChecks != $totalChecks)
								{
									Console::println('Best time\'s checkpoint count does not match and was ignored!');
									Console::printPlayerBest($player);
									$player->bestTime = $old_best;
									return;
								}
								break;
								
							case 1:
							case 3:
							case 4:
								if ($nbChecks != $this->currentChallenge->nbCheckpoints)
								{
									Console::println('Best time\'s checkpoint count does not match and was ignored!');
									Console::printPlayerBest($player);
									$player->bestTime = $old_best;
									return;
								}
								break;
						}
						
						Dispatcher::dispatch(new Event($this, Event::ON_PLAYER_NEW_BEST_TIME, array($player, $old_best, $timeOrScore)));
					}
				}
				break;
		}
	}

	function onPlayerIncoherence($playerUid, $login) {}
	function onBillUpdated($billId, $state, $stateName, $transactionId) {}
	function onTunnelDataReceived($playerUid, $login, $data) {}

	function onChallengeListModified($curChallengeIndex, $nextChallengeIndex, $isListModified)
	{
		if($isListModified)
		{
			$challenges = Connection::getInstance()->getChallengeList(-1, 0);

			foreach ($challenges as $key => $challenge)
			{
				$storageKey = array_search($challenge, $this->challenges);
				if(in_array($challenge, $this->challenges))
				{
					$challenges[$key] = $this->challenges[$storageKey];
				}
				else
				{
					$this->challenges[$storageKey] = null;
				}
			}
			$this->challenges = $challenges;
		}
		$this->nextChallenge = (array_key_exists($nextChallengeIndex, $this->challenges) ? $this->challenges[$nextChallengeIndex] : null);
	}

	function onPlayerInfoChanged($playerInfo)
	{
		$keys = array_keys($playerInfo);
		$keys = array_map('lcfirst', $keys);
		$keys[] = 'forceSpectator';
		$keys[] = 'isReferee';
		$keys[] = 'isPodiumReady';
		$keys[] = 'isUsingStereoscopy';
		$keys[] = 'spectator';
		$keys[] = 'temporarySpectator';
		$keys[] = 'pureSpectator';
		$keys[] = 'autoTarget';
		$keys[] = 'currentTargetId';
		
		$playerInfo = Player::fromArray($playerInfo);
		
		if($playerInfo->spectator == 0)
		{
			if(array_key_exists($playerInfo->login, $this->players))
			{
				foreach ($keys as $key)
				{
					$this->players[$playerInfo->login]->$key = $playerInfo->$key;
				}
			}
			elseif(array_key_exists($playerInfo->login, $this->spectators))
			{
				$this->players[$playerInfo->login] = $this->spectators[$playerInfo->login];

				unset($this->spectators[$playerInfo->login]);

				foreach ($keys as $key)
				{
					$this->players[$playerInfo->login]->$key = $playerInfo->$key;
				}
				Dispatcher::dispatch(new Event($this, Event::ON_PLAYER_CHANGE_SIDE, array($this->players[$playerInfo->login], 'spectator')));
			}
		}
		else
		{
			if(array_key_exists($playerInfo->login, $this->spectators))
			{
				foreach ($keys as $key)
				{
					$this->spectators[$playerInfo->login]->$key = $playerInfo->$key;
				}
			}
			elseif(array_key_exists($playerInfo->login, $this->players))
			{
				$this->spectators[$playerInfo->login] = $this->players[$playerInfo->login];

				unset($this->players[$playerInfo->login]);

				foreach ($keys as $key)
				{
					$this->spectators[$playerInfo->login]->$key = $playerInfo->$key;
				}
				Dispatcher::dispatch(new Event($this, Event::ON_PLAYER_CHANGE_SIDE, array($this->spectators[$playerInfo->login], 'player')));
			}
		}
		unset($playerInfo);
	}

	function onManualFlowControlTransition($transition) {}
	#endRegion

	/**
	 * Give a Player Object for the corresponding login
	 * @param string $login
	 * @return \ManiaLive\DedicatedApi\Structures\Player
	 */
	function getPlayerObject($login)
	{
		if(array_key_exists($login, $this->players))
		{
			return $this->players[$login];
		}
		elseif(array_key_exists($login, $this->spectators))
		{
			return $this->spectators[$login];
		}
		else
		{
			return null;
		}
	}

	protected function updateRanking($rankings)
	{

		$changed = array();
		foreach($rankings as $ranking)
		{
			if($ranking->rank == 0)
			{
				continue;
			}
			elseif(array_key_exists($ranking->login, $this->players))
			{
				$player = $this->players[$ranking->login];
				$rank_old = $player->rank;

				$player->playerId = $ranking->playerId;
				$player->rank = $ranking->rank;
				$player->bestTime = $ranking->bestTime;
				$player->bestCheckpoints = $ranking->bestCheckpoints;
				$player->score = $ranking->score;
				$player->nbrLapsFinished = $ranking->nbrLapsFinished;
				$player->ladderScore = $ranking->ladderScore;

				if ($rank_old != $player->rank)
				{
					Dispatcher::dispatch(new Event($this, Event::ON_PLAYER_NEW_RANK, array($player, $rank_old, $player->rank)));
				}

				$this->ranking[$ranking->rank] = $this->players[$ranking->login];
			}
			elseif (array_key_exists($ranking->login, $this->spectators))
			{
				$spectator = $this->spectators[$ranking->login];

				$spectator->playerId = $ranking->playerId;
				$spectator->rank = $ranking->rank;
				$spectator->bestTime = $ranking->bestTime;
				$spectator->bestCheckpoints = $ranking->bestCheckpoints;
				$spectator->score = $ranking->score;
				$spectator->nbrLapsFinished = $ranking->nbrLapsFinished;
				$spectator->ladderScore = $ranking->ladderScore;

				$this->ranking[$ranking->rank] = $this->spectators[$ranking->login];
			}
		}
	}
}
?>