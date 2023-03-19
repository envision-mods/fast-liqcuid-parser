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
 * Implements a template variable.
 */
class Variable
{
	/** @var array The filters to execute on the variable */
	private $filters;

	/** @var string The name of the variable */
	private $name;

	/**
	 * Constructor
	 *
	 * @param string $markup
	 */
	public function __construct($markup)
	{
		$syntax = preg_match('/((["\'])?(?(2)(?:(?!\2).)*+\2|[a-zA-Z_0-9.\-[\]]+))\s*(\|)?/', $markup, $matches, PREG_UNMATCHED_AS_NULL | PREG_OFFSET_CAPTURE);
		$this->filters = [];
		if ($syntax) {
			$this->name = $matches[1][0];

			if ($matches[3][0] !== null) {
				$filters = preg_split('/\|\s*(\w+)\s*:?\s*/', $markup, $matches[3][1], PREG_SPLIT_DELIM_CAPTURE);

				for ($i = 1, $n = count($filters); $i < $n; $i += 2) {
					if ($filters[$i + 1] !== '') {
						preg_match_all('/(?:(\w+)\s*\:\s*)?((["\'])?(?(3)(?:(?!\3).)*+\3|[a-zA-Z_0-9.-]+))/', $filters[$i + 1] ?? '', $args, PREG_UNMATCHED_AS_NULL);
					}
					$this->filters[] = [$filters[$i], $args ?? []];
				}
			}
		}

		if (Liquid::$config['ESCAPE_BY_DEFAULT']) {
			// if auto_escape is enabled, and
			// - there's no raw filter, and
			// - no escape filter
			// - no other standard html-adding filter
			// then
			// - add a mandatory escape filter

			$addEscapeFilter = true;

			foreach ($this->filters as $filter) {
				if (in_array($filter[0], array('escape', 'escape_once', 'raw', 'newline_to_br'))) {
					$addEscapeFilter = false;
					break;
				}
			}

			if ($addEscapeFilter) {
				$this->filters[] = array('escape', array());
			}
		}
	}

	/**
	 * Renders the variable with the data in the context
	 *
	 * @param Context $context
	 *
	 * @return mixed|string
	 */
	public function render(Context $context)
	{
		$output = $context->get($this->name);
		if ($this->filters !== null) {
			foreach ($this->filters as $filter) {
				list($filtername, $arg) = $filter;

				$filterArgValues = array();
				$keywordArgValues = array();

				if (isset($arg[1])) {
					for ($i = 0, $n = count($arg[1]); $i < $n; $i++) {
						if ($arg[1][$i] !== null) {
							$keywordArgValues[$arg[1][$i]] = $context->get($arg[2][$i]);
						} else {
							$filterArgValues[] = $context->get($arg[2][$i]);
						}
					}
				}
				if ($keywordArgValues !== []) {
					$filterArgValues[] = $keywordArgValues;
				}

				$output = $context->invoke($filtername, $output, $filterArgValues);
			}
		}
		return $output;
	}
}
