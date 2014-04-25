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

namespace ManiaLive\Application;

use Maniaplanet\DedicatedServer\Connection;
use ManiaLive\Config\Loader;
use ManiaLive\Event\Dispatcher;

const VERSION = '4.0.0';

class Application extends \ManiaLib\Utils\Singleton
{
	const CYCLES_PER_SECOND = 60;

	static $startTime;
	/** @var bool */
	protected $running = true;
	/** @var Connection */
	protected $connection;

	protected function __construct()
	{
		set_error_handler('\ManiaLive\Application\ErrorHandling::createExceptionFromError');
		if(extension_loaded('pcntl'))
		{
			pcntl_signal(SIGTERM, array($this, 'kill'));
			pcntl_signal(SIGINT, array($this, 'kill'));
			declare(ticks = 1);
		}

		try
		{
			$configFile = CommandLineInterpreter::preConfigLoad();

			// load configuration file
			$loader = Loader::getInstance();
			$loader->setINIConfigFilename(\ManiaLib\Utils\Path::getInstance()->getConfig(true).$configFile);
			$loader->load();

			// load configureation from the command line ...
			CommandLineInterpreter::postConfigLoad();

			// add logfile prefix ...
			$manialiveConfig = \ManiaLive\Config\Config::getInstance();
			$serverConfig = \ManiaLive\DedicatedApi\Config::getInstance();
			if($manialiveConfig->logsPrefix != null)
			{
				$manialiveConfig->logsPrefix = str_replace('%ip%', str_replace('.', '-', $serverConfig->host), $manialiveConfig->logsPrefix);
				$manialiveConfig->logsPrefix = str_replace('%port%', $serverConfig->port, $manialiveConfig->logsPrefix);
			}

			// disable logging?
			/*if(!$manialiveConfig->runtimeLog)
				\ManiaLive\Utilities\Logger::getLog('runtime')->disableLog();*/
		}
		catch(\Exception $e)
		{
			// exception on startup ...
			ErrorHandling::processStartupException($e);
		}
	}

	protected function init()
	{
		\ManiaLive\Utilities\Console::println('Running ManiaLive '.VERSION.'...');
			
		new \ManiaLive\Features\Tick\Ticker();
		$config = \ManiaLive\DedicatedApi\Config::getInstance();
		$this->connection = Connection::factory(
				$config->host,
				$config->port,
				$config->timeout,
				$config->user,
				$config->password
			);
		$this->connection->enableCallbacks(true);
		\ManiaLive\Data\Storage::getInstance();
		\ManiaLive\Features\ChatCommand\Interpreter::getInstance();
		\ManiaLive\Features\EchoHandler::getInstance();
		\ManiaLive\Gui\GuiHandler::getInstance();
		\ManiaLive\PluginHandler\PluginHandler::getInstance();

		Dispatcher::dispatch(new Event(Event::ON_INIT));
	}

	final function run()
	{
		try
		{
			$this->init();

			Dispatcher::dispatch(new Event(Event::ON_RUN));
			self::$startTime = microtime(true);
			$nextCycleStart = self::$startTime;
			$cycleTime = 1 / static::CYCLES_PER_SECOND;
		}
		catch(\Exception $e)
		{
			ErrorHandling::processRuntimeException($e);
		}

		try
		{
			while($this->running)
			{
				Dispatcher::dispatch(new Event(Event::ON_PRE_LOOP));
				$calls = $this->connection->executeCallbacks();
				if(!empty($calls))
				{
					foreach($calls as $call)
					{
						$method = preg_replace('/^[[:alpha:]]+\./', '', $call[0]); // remove trailing "Whatever."
						$params = (array) $call[1];
						Dispatcher::dispatch(new \ManiaLive\DedicatedApi\Callback\Event($method, $params));
					}
				}
				$this->connection->executeMulticall();
				Dispatcher::dispatch(new Event(Event::ON_POST_LOOP));

				$endCycleTime = microtime(true) + $cycleTime / 10;
				do
				{
					$nextCycleStart += $cycleTime;
				}
				while($nextCycleStart < $endCycleTime);
				@time_sleep_until($nextCycleStart);
			}
		}
		catch(\Exception $e)
		{
			ErrorHandling::processRuntimeException($e);
		}

		Dispatcher::dispatch(new Event(Event::ON_TERMINATE));
	}

	function kill()
	{
		if($this->connection) $this->connection->manualFlowControlEnable(false);
		$this->running = false;
	}

}

?>