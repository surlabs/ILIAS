<?php

/* Copyright (c) 2015 Richard Klees <richard.klees@concepts-and-training.de> Extended GPL, see docs/LICENSE */

namespace ILIAS\UI\Component\Button;

use ILIAS\UI\Component\Glyph\Glyph;

/**
 * This describes commonalities between standard and primary buttons. 
 */
interface Button extends \ILIAS\UI\Component\Component {
	/**
	 * Get the label on the button.
	 *
	 * @return	string|null
	 */
	public function getLabel();

	/**
	 * Get a button like this, but with an additional/replaced label.
	 *
	 * @param	string	$label
	 * @return	Button
	 */
	public function withLabel($label);

	/**
	 * Get the glyph on the button.
	 *
	 * @return	Glyph|null
	 */
	public function getGlyph();

	/**
	 * Get a button like this, but with an additonal/replaced Glyph.
	 *
	 * @param	Glyph	$glyph
	 * @return	Button
	 */
	public function withGlyph(Glyph $glyph);

	/**
	 * Get the action of the button
	 *
	 * @return	string
	 */
	public function getAction();

	/**
	 * Get to know if the button is activated.
	 *
	 * @return 	bool
	 */
	public function isActivated();

	/**
	 * Get a button like this, but action should be unavailable atm.
	 *
	 * The button will still have an action afterwards, this might be usefull
	 * at some point where we want to reactivate the button client side.
	 *
	 * @return Button
	 */
	public function withUnavailableAction();
}
