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
namespace ManiaLivePlugins\Standard\Menubar\Gui\Controls;

use ManiaLive\Gui\Windowing\Container;
use ManiaLib\Gui\Layouts\Column;
use ManiaLib\Gui\Elements\Bgs1;
use ManiaLib\Gui\Elements\Icons128x128_1;
use ManiaLib\Gui\Elements\BgsPlayerCard;
use ManiaLib\Gui\Elements\Label;

class Subitem extends \ManiaLive\Gui\Windowing\Control
{
	private $name;
	private $label;
	private $background;
	private $action;

	function initializeComponents()
	{
		$this->name = $this->getParam(0, '');
		$this->sizeX = $this->getParam(1, 18);
		$this->sizeY = $this->getParam(2, 4);

		$this->action = array();

		$this->background = new Bgs1();
		$this->background->setSize($this->getSizeX(), $this->getSizeY());
		$this->background->setSubStyle(Bgs1::NavButton);
		$this->addComponent($this->background);

		$this->label = new Label();
		$this->addComponent($this->label);
	}

	function beforeDraw()
	{
		$this->label->setValign('center');
		$this->label->setText('$i'.$this->name);
		$this->label->setPositionY($this->getSizeY() / 2 - 0.2);
		$this->label->setHalign('left');
		$this->label->setPositionX(1);

		$this->background->setAction($this->callback('onClick'));
		$this->background->setSubStyle(Bgs1::NavButtonBlink);
	}

	function afterDraw() {}

	function setName($name)
	{
		$this->name = $name;
	}

	function getName()
	{
		return $this->name;
	}

	function setAction($action)
	{
		$this->action = $action;
	}

	function onClick($login)
	{
		if (is_callable($this->action))
		{
			if ($this->getPlayerValue('active'))
			{
				$this->getPlayerValue('active')->toggleSubitems($login);
				call_user_func_array($this->action, array($login));
			}
		}
	}

	function destroy()
	{
		$this->action = null;
		parent::destroy();
	}
}