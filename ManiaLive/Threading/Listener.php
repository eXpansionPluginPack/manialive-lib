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

namespace ManiaLive\Threading;

/**
 * @author Florian Schnell
 */
interface Listener extends \ManiaLive\Event\Listener
{
	function onThreadStart($thread);
	function onThreadRestart($thread);
	function onThreadDies($thread);
	function onThreadTimesOut($thread);
}

?>