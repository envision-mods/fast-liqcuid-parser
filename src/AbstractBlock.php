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

use Liquid\Exception\ParseException;
use Liquid\Exception\RenderException;

/**
 * Base class for blocks.
 */
class AbstractBlock extends AbstractTag
{
	/** @var AbstractTag[]|Variable[]|string[] */
	protected $nodelist = array();

	/** @var bool Whenever next token should be ltrim'med. */
	protected static $trimWhitespace = false;

	/**
	 * @return array
	 */
	public function getNodelist()
	{
		return $this->nodelist;
	}

	/**
	 * Parses the given tokens
	 *
	 * @param array $tokens
	 *
	 * @throws \Liquid\LiquidException
	 * @return void
	 */
	public function parse(int $i, int $n, array &$tokens)
	{
		$this->nodelist = array();
		$tags = Template::getTags();

		for (; $i < $n; $i++) {
			if ($tokens[$i] === null) {
				continue;
			}
			$token = $tokens[$i];
			$tokens[$i] = null;

			if (isset($token[1]) && $token[0] === '{' && $token[1] === '%') {
				if (isset($token[-2]) && $token[-2] === '%' && $token[-1] === '}') {
					$this->whitespaceHandler($token);
					$tokenParts = preg_split('/\s/', trim($token, "{%}- \n\r\t"), 2);

					// If we found the proper block delimitor just end parsing here and let the outer block proceed
					if ($tokenParts[0] === $this->blockDelimiter()) {
						$this->endTag();
						return;
					}

					$tagName = $tags[$tokenParts[0]] ?? null;
					if ($tagName !== null) {
						$name = $tokenParts[1] ?? '';
						$obj = new $tagName($i + 1, $n, $name, $tokens, $this->fileSystem);
						$this->nodelist[] = $obj;
						if ($tokenParts[0] === 'extends') {
							return;
						}
					} else {
						$this->unknownTag($tokenParts[0], $tokenParts[1] ?? '', $tokens);
					}
				} else {
					throw new ParseException("Tag {strtok($token, '')} was not properly terminated");
				}
			} elseif (isset($token[-2]) && $token[0] === '{' && $token[1] === '{') {
				if (isset($token[-2]) && $token[-2] === '}' && $token[-1] === '}') {
					$this->whitespaceHandler($token);
					$this->nodelist[] = new Variable(trim($token, "{}- \n\r\t"));
				} else {
					throw new ParseException("Variable $token was not properly terminated");
				}
			} else {
				// This is neither a tag or a variable, proceed with an ltrim
				$this->nodelist[] = self::$trimWhitespace ? ltrim($token) : $token;
				self::$trimWhitespace = false;
			}
		}
		$this->assertMissingDelimitation();
	}

	/**
	 * Handle the whitespace.
	 *
	 * @param string $token
	 */
	protected function whitespaceHandler($token)
	{
		if ($token[2] == '-') {
			$previousToken = end($this->nodelist);
			if (is_string($previousToken)) { // this can also be a tag or a variable
				$this->nodelist[key($this->nodelist)] = rtrim($previousToken);
			}
		}

		self::$trimWhitespace = $token[-3] === '-';
	}

	/**
	 * Render the block.
	 *
	 * @param Context $context
	 *
	 * @return string
	 */
	public function render(Context $context)
	{
		return $this->renderAll($this->nodelist, $context);
	}

	/**
	 * Renders all the given nodelist's nodes
	 *
	 * @param array $list
	 * @param Context $context
	 *
	 * @return string
	 */
	protected function renderAll(array $list, Context $context)
	{
		$result = '';

		foreach ($list as $token) {
			if (is_object($token) && method_exists($token, 'render')) {
				$value = $token->render($context);
			} else {
				$value = $token;
			}

			if (is_array($value)) {
				$value = htmlspecialchars(implode($value));
			}

			$result .= $value;

			if (isset($context->registers['break'])) {
				break;
			}
			if (isset($context->registers['continue'])) {
				break;
			}

			$context->tick();
		}

		return $result;
	}

	/**
	 * An action to execute when the end tag is reached
	 */
	protected function endTag()
	{
		// Do nothing by default
	}

	/**
	 * Handler for unknown tags
	 *
	 * @param string $tag
	 * @param string $params
	 * @param array $tokens
	 *
	 * @throws \Liquid\Exception\ParseException
	 */
	protected function unknownTag($tag, $params, array $tokens)
	{
		switch ($tag) {
			case 'else':
				throw new ParseException($this->blockName() . " does not expect else tag");
			case 'end':
				throw new ParseException("'end' is not a valid delimiter for " . $this->blockName() . " tags. Use " . $this->blockDelimiter());
			default:
				throw new ParseException("Unknown tag $tag");
		}
	}

	/**
	 * This method is called at the end of parsing, and will throw an error unless
	 * this method is subclassed, like it is for Document
	 *
	 * @throws \Liquid\Exception\ParseException
	 * @return bool
	 */
	protected function assertMissingDelimitation()
	{
		throw new ParseException($this->blockName() . " tag was never closed");
	}

	/**
	 * Returns the string that delimits the end of the block
	 *
	 * @return string
	 */
	protected function blockDelimiter()
	{
		return "end" . $this->blockName();
	}
}
