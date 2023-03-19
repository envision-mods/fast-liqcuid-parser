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

use Liquid\Liquid;
use Liquid\AbstractBlock;
use Liquid\Regexp;

/**
 * Allows output of Liquid code on a page without being parsed.
 *
 * Example:
 *
 *     {% raw %}{{ 5 | plus: 6 }}{% endraw %} is equal to 11.
 *
 *     will return:
 *     {{ 5 | plus: 6 }} is equal to 11.
 */
class TagRaw extends AbstractBlock
{
	/**
	 * @param array $tokens
	 */
	public function parse(int $i, int $n, array &$tokens)
	{
		$this->nodelist = array();

		while ($i < $n) {
			if ($tokens[$i] === null) {
				continue;
			}
			$token = $tokens[$i];
			$tokens[$i] = null;
			if (trim($token, "{%}- \n\r\t") === 'endraw') {
				break;
			}

			$this->nodelist[] = $token;
			$i++;
		}
	}

	/**
	 * Returns the name of the block
	 *
	 * @return string
	 */
	public function blockName()
	{
		return 'raw';
	}
}
