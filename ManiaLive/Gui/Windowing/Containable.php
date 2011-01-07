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

namespace ManiaLive\Gui\Windowing;

/**
 * This will provide automatic call of the onIsAdded method
 * when stored in an object of type Container.
 * 
 * @author Florian Schnell
 */
interface Containable
{
	/**
	 * This method is invoked when adding an object of this type
	 * to a Container class object.
	 * @param Container $target Reference to the target Container.
	 */
	function onIsAdded(Container $target);
}

?>