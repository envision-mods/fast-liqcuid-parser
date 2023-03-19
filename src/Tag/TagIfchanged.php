<?php

/*
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid\Tag;

use Liquid\AbstractBlock;
use Liquid\Context;

/**
 * Quickly create a table from a collection
 */
class TagIfchanged extends AbstractBlock
{
	/** @var string The last value */
	private $lastValue = '';

	/**
	 * Renders the block
	 *
	 * @param Context $context
	 *
	 * @return string
	 */
	public function render(Context $context)
	{
		$output = parent::render($context);

		if ($this->lastValue == $output) {
			return '';
		}
		$this->lastValue = $output;
		return $this->lastValue;
	}

	/**
	 * Returns the name of the block
	 *
	 * @return string
	 */
	public function blockName()
	{
		return 'ifchanged';
	}
}
