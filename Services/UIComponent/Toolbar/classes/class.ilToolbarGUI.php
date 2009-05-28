<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2009 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/

/**
* Toolbar. The toolbar currently only supports a list of buttons as links.
*
* A default toolbar object is available in the $ilToolbar global object.
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id$
* @ingroup ServicesUIComponent
*/
class ilToolbarGUI
{
	var $items = array();

	function __construct()
	{
	
	}
	
	/**
	* Set form mode
	*
	* @param	boolean	form mode
	*/
	function setFormMode($a_val)
	{
		$this->form_mode = $a_val;
	}
	
	/**
	* Get form mode
	*
	* @return	boolean	form mode
	*/
	function getFormMode()
	{
		return $this->form_mode;
	}
	
	/**
	* Set leading image
	*/
	function setLeadingImage($a_img, $a_alt)
	{
		$this->lead_img = array("img" => $a_img, "alt" => $a_alt);
	}
	
	/**
	* Add item to toolbar
	*
	* @param	string		text
	* @param	string		link href / submit command
	* @param	string		frame target
	* @param	string		access key
	*/
	function addButton($a_txt, $a_cmd, $a_target = "", $a_acc_key = "")
	{
		$this->items[] = array("type" => "button", "txt" => $a_txt, "cmd" => $a_cmd,
			"target" => $a_target, "acc_key" => $a_acc_key);
	}
	
	/**
	* Get toolbar html
	*/
	function getHTML()
	{
		global $lng;
		
		$tpl = new ilTemplate("tpl.buttons.html", true, true);
		if (count($this->items) > 0)
		{
			foreach($this->items as $item)
			{
				if (!$this->getFormMode())
				{
					$tpl->setCurrentBlock("btn_cell");
					$tpl->setVariable("BTN_TXT", $item["txt"]);
					$tpl->setVariable("BTN_LINK", $item["cmd"]);
					if ($item["target"] != "")
					{
						$tpl->setVariable("BTN_TARGET", 'target="'.$item["target"].'"');
					}
					if ($item["acc_key"] != "")
					{
						include_once("./Services/Accessibility/classes/class.ilAccessKeyGUI.php");
						$tpl->setVariable("BTN_ACC_KEY",
							ilAccessKeyGUI::getAttribute($item["acc_key"]));
					}
					$tpl->parseCurrentBlock();
				}
				else
				{
					$tpl->setCurrentBlock("btn_sub");
					$tpl->setVariable("SUB_TXT", $item["txt"]);
					$tpl->setVariable("SUB_CMD", $item["cmd"]);
					$tpl->parseCurrentBlock();
				}
			}
			
			$tpl->setCurrentBlock("btn_row");
			$tpl->parseCurrentBlock();

			$tpl->setVariable("TXT_FUNCTIONS", $lng->txt("functions"));
			if ($this->lead_img["img"] != "")
			{
				$tpl->setVariable("IMG_SRC", $this->lead_img["img"]);
				$tpl->setVariable("IMG_ALT", $this->lead_img["alt"]);
			}
			
			return $tpl->get();
		}
		return "";
	}
}
