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

use Liquid\AbstractTag;
use Liquid\Exception\ParseException;
use Liquid\Liquid;
use Liquid\Context;
use Liquid\FileSystem;
use Liquid\Regexp;

/**
 * Used to decrement a counter into a template
 *
 * Example:
 *
 *     {% decrement value %}
 *
 * @author Viorel Dram
 */
class TagDecrement extends AbstractTag
{
	/** @var int Name of the variable to decrement */
	private $toDecrement;

	/**
	 * Constructor
	 *
	 * @param string $markup
	 * @param array $tokens
	 * @param FileSystem $fileSystem
	 * @param mixed $i
	 * @param mixed $n
	 *
	 * @throws \Liquid\Exception\ParseException
	 */
	public function __construct($i, $n, $markup, array &$tokens, FileSystem $fileSystem = null)
	{
		$syntax = preg_match('/(' . Liquid::$config['VARIABLE_NAME'] . ')/', $markup, $matches);

		if ($syntax) {
			$this->toDecrement = $matches[0];
		} else {
			throw new ParseException("Syntax Error in 'decrement' - Valid syntax: decrement [var]");
		}
	}

	/**
	 * Renders the tag
	 *
	 * @param Context $context
	 *
	 * @return string|void
	 */
	public function render(Context $context)
	{
		// if the value is not set in the environment check to see if it
		// exists in the context, and if not set it to 0
		if (!isset($context->environments[0][$this->toDecrement])) {
			// check for a context value
			$fromContext = $context->get($this->toDecrement);

			// we already have a value in the context
			$context->environments[0][$this->toDecrement] = (null !== $fromContext) ? $fromContext : 0;
		}

		// decrement the environment value
		$context->environments[0][$this->toDecrement]--;

		return '';
	}

	/**
	 * Returns the name of the block
	 *
	 * @return string
	 */
	public function blockName()
	{
		return 'decrement';
	}
}
