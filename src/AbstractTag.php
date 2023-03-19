<?php

/*
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid;

/**
 * Base class for tags.
 */
abstract class AbstractTag
{
	/**
	 * The markup for the tag
	 *
	 * @var string
	 */
	protected $markup;

	/**
	 * Filesystem object is used to load included template files
	 *
	 * @var FileSystem
	 */
	protected $fileSystem;

	/**
	 * Additional attributes
	 *
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * Constructor.
	 *
	 * @param string $markup
	 * @param array $tokens
	 * @param FileSystem $fileSystem
	 * @param mixed $i
	 * @param mixed $n
	 */
	public function __construct($i, $n, $markup, array &$tokens, FileSystem $fileSystem = null)
	{
		$this->markup = $markup;
		$this->fileSystem = $fileSystem;
		$this->parse($i, $n, $tokens);
	}

	/**
	 * Parse the given tokens.
	 *
	 * @param array $tokens
	 */
	public function parse(int $i, int $n, array &$tokens)
	{
		// Do nothing by default
	}

	/**
	 * Render the tag with the given context.
	 *
	 * @param Context $context
	 *
	 * @return string
	 */
	abstract public function render(Context $context);

	/**
	 * Extracts tag attributes from a markup string.
	 *
	 * @param string $markup
	 */
	protected function extractAttributes($markup)
	{
		$this->attributes = array();

		$regex = preg_match_all('/(\w+)\s*\:\s*(' . Liquid::get('QUOTED_FRAGMENT') . ')/', $markup, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$this->attributes[$match[1]] = $match[2];
		}
	}

	/**
	 * Returns the name of the tag.
	 *
	 * @return string
	 */
	protected function name()
	{
		return strtolower(get_class($this));
	}
}
