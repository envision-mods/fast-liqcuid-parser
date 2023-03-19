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
use Liquid\Liquid;
use Liquid\Context;
use Liquid\FileSystem;
use Liquid\Regexp;

/**
 * Loops over an array, assigning the current value to a given variable
 *
 * Example:
 *
 *     {%for item in array%} {{item}} {%endfor%}
 *
 *     With an array of 1, 2, 3, 4, will return 1 2 3 4
 *
 *     or
 *
 *     {%for i in (1..10)%} {{i}} {%endfor%}
 *     {%for i in (1..variable)%} {{i}} {%endfor%}
 *
 */
class TagFor extends AbstractBlock
{
	/**
	 * @var array The collection to loop over
	 */
	private $collectionName;

	/**
	 * @var string The variable name to assign collection elements to
	 */
	private $variableName;

	/**
	 * @var string The name of the loop, which is a compound of the collection and variable names
	 */
	private $name;

	/**
	 * @var bool Whether the loop specifies a range to expand
	 */
	private $isRange;

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
		parent::__construct($i, $n, $markup, $tokens, $fileSystem);

		$syntax = preg_match('/(\w+)\s+in\s+(?|\((\d+|(?P>label))\s*\.\.\s*(\d+|(?P>label))\)|((?P>label)))(?(DEFINE)(?<label>[a-zA-Z_][a-zA-Z0-9_.-]*))/', $markup, $matches, PREG_UNMATCHED_AS_NULL);

		if ($syntax) {
			$this->isRange = $matches[3] !== null;
			$this->variableName = $matches[1];
			$this->extractAttributes($markup);
			if ($this->isRange) {
				$this->start = $matches[2];
				$this->collectionName = $matches[3];
				$this->name = $matches[1].'-range';
			} else {
				$this->collectionName = $matches[2];
				$this->name = $matches[1] . '-' . $matches[2];
			}
		} else {
			throw new ParseException("Syntax Error in 'for loop' - Valid syntax: for [item] in [collection]");
		}
	}

	/**
	 * Renders the tag
	 *
	 * @param Context $context
	 *
	 * @return null|string
	 */
	public function render(Context $context)
	{
		if (!isset($context->registers['for'])) {
			$context->registers['for'] = array();
		}

		if ($this->isRange) {
			return $this->renderDigit($context);
		}

		return $this->renderCollection($context);
	}

	private function renderCollection(Context $context)
	{
		$collection = $context->get($this->collectionName);

		if ($collection instanceof \Generator && !$collection->valid()) {
			return '';
		}

		if ($collection instanceof \Traversable) {
			$collection = iterator_to_array($collection);
		}

		if (is_null($collection) || !is_array($collection) || count($collection) == 0) {
			return '';
		}

		$start = 0;
		$end = count($collection);

		if (isset($this->attributes['limit']) || isset($this->attributes['offset'])) {
			if (isset($this->attributes['offset'])) {
				$start = ($this->attributes['offset'] == 'continue') ? $context->registers['for'][$this->name] : $context->get($this->attributes['offset']);
			}

			$limit = (isset($this->attributes['limit'])) ? $context->get($this->attributes['limit']) : null;
			$end = $limit ? $limit : $end - $start;

			$context->registers['for'][$this->name] = $end + $start;
		}

		$result = '';
		$segment = array_slice($collection, $start, $end);
		if (!count($segment)) {
			return null;
		}

		$context->push();
		$length = count($segment);

		$index = 0;
		foreach ($segment as $key => $item) {
			$value = is_numeric($key) ? $item : array($key, $item);
			$context->set($this->variableName, $value);
			$context->set('forloop', array(
					'name' => $this->name,
					'length' => $length,
					'index' => $index + 1,
					'index0' => $index,
					'rindex' => $length - $index,
					'rindex0' => $length - $index - 1,
					'first' => (int)($index == 0),
					'last' => (int)($index == $length - 1)
			));

			$result .= $this->renderAll($this->nodelist, $context);

			$index++;

			if (isset($context->registers['break'])) {
				unset($context->registers['break']);
				break;
			}
			if (isset($context->registers['continue'])) {
				unset($context->registers['continue']);
			}
		}

		$context->pop();

		return $result;
	}

	private function renderDigit(Context $context)
	{
		$start = $this->start;
		if (!is_integer($this->start)) {
			$start = $context->get($this->start);
		}

		$end = $this->collectionName;
		if (!is_integer($this->collectionName)) {
			$end = $context->get($this->collectionName);
		}

		$context->push();
		$result = '';
		$index = 0;
		$length = $end - $start;
		for ($i = $start; $i <= $end; $i++) {
			$context->set($this->variableName, $i);
			$context->set('forloop', array(
				'name'		=> $this->name,
				'length'	=> $length,
				'index'		=> $index + 1,
				'index0'	=> $index,
				'rindex'	=> $length - $index,
				'rindex0'	=> $length - $index - 1,
				'first'		=> (int)($index == 0),
				'last'		=> (int)($index == $length - 1)
			));

			$result .= $this->renderAll($this->nodelist, $context);

			$index++;

			if (isset($context->registers['break'])) {
				unset($context->registers['break']);
				break;
			}
			if (isset($context->registers['continue'])) {
				unset($context->registers['continue']);
			}
		}

		$context->pop();

		return $result;
	}

	/**
	 * Returns the name of the block
	 *
	 * @return string
	 */
	public function blockName()
	{
		return 'for';
	}
}
