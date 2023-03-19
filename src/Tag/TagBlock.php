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
use Liquid\Exception\ParseException;
use Liquid\FileSystem;
use Liquid\Regexp;

/**
 * Marks a section of a template as being reusable.
 *
 * Example:
 *
 *     {% block foo %} bar {% endblock %}
 */
class TagBlock extends AbstractBlock
{
	/**
	 * The variable to assign to
	 *
	 * @var string
	 */
	private $block;

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
	 * @return \Liquid\Tag\TagBlock
	 */
	public function __construct($i, $n, $markup, array &$tokens, FileSystem $fileSystem = null)
	{
		$syntax = preg_match('/\w+/', $markup, $matches);

		if ($syntax) {
			$this->block = $matches[0];
			parent::__construct($i, $n, $markup, $tokens, $fileSystem);
		} else {
			throw new ParseException("Syntax Error in 'block' - Valid syntax: block [name]");
		}
	}

	/**
	 * Returns the name of the block
	 *
	 * @return string
	 */
	public function blockName()
	{
		return 'block';
	}
}
