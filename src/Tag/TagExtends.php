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
use Liquid\Document;
use Liquid\Exception\MissingFilesystemException;
use Liquid\Exception\ParseException;
use Liquid\Liquid;
use Liquid\Context;
use Liquid\FileSystem;
use Liquid\Regexp;
use Liquid\Template;

/**
 * Extends a template by another one.
 *
 * Example:
 *
 *     {% extends "base" %}
 */
class TagExtends extends AbstractTag
{
	/**
	 * @var string The name of the template
	 */
	private $templateName;

	/**
	 * @var Document The Document that represents the included template
	 */
	private $document;

	/**
	 * @var string The Source Hash
	 */
	protected $hash;

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
		$regex = preg_match('/(["\'])((?:(?!\1).)+)\1/', $markup, $matches);

		if ($regex) {
			$this->templateName = $matches[2];
		} else {
			throw new ParseException("Error in tag 'extends' - Valid syntax: extends '[template name]'");
		}

		parent::__construct($i, $n, $markup, $tokens, $fileSystem);
	}

	/**
	 * @param array $tokens
	 *
	 * @return array
	 */
	private function findBlocks(int $i, int $n, array $tokens)
	{
		$b = array();
		$name = null;

		for (; $i < $n; $i++) {
			if ($tokens[$i] === null) {
				continue;
			}
			$tokenParts = preg_split('/\s/', trim($tokens[$i], "{%}- \n\r\t"), 2);

			if ($tokenParts[0] === 'block') {
				$name = $tokenParts[1];
				$b[$name] = array();
			} elseif ($tokenParts[0] === 'endblock') {
				$name = null;
			} elseif ($name !== null) {
				$b[$name][] = $tokens[$i];
			}
		}

		return $b;
	}

	/**
	 * Parses the tokens
	 *
	 * @param array $tokens
	 *
	 * @throws \Liquid\Exception\MissingFilesystemException
	 */
	public function parse(int $i, int $n, array &$tokens)
	{
		if ($this->fileSystem === null) {
			throw new MissingFilesystemException("No file system");
		}

		// read the source of the template and create a new sub document
		$source = $this->fileSystem->readTemplateFile($this->templateName);
		$maintokens = Template::tokenize($source);

		foreach ($maintokens as $maintoken) {
			$tokenParts = preg_split('/\s/', trim($maintoken, "{%}- \n\r\t"), 2);

			if ($tokenParts[0] === 'extends') {
				$m = true;
				break;
			}
		}

		if (isset($m)) {
			$rest = array_merge($maintokens, $tokens);
		} else {
			$childtokens = $this->findBlocks($i, $n, $tokens);

			$rest = array();
			$keep = false;

			for ($i = 0; $i < count($maintokens); $i++) {
				$tokenParts = preg_split('/\s/', trim($maintokens[$i], "{%}- \n\r\t"), 2);

				if ($tokenParts[0] === 'block') {
					$name = $tokenParts[1];

					if (isset($childtokens[$name])) {
						$keep = true;
						array_push($rest, $maintokens[$i]);
						foreach ($childtokens[$name] as $item) {
							array_push($rest, $item);
						}
					}
				}
				if (!$keep) {
					array_push($rest, $maintokens[$i]);
				}

				if ($tokenParts[0] === 'endblock' && $keep === true) {
					$keep = false;
					array_push($rest, $maintokens[$i]);
				}
			}
		}

		$cache = Template::getCache();

		if (!$cache) {
			$this->document = new Document(0, count($rest), '', $rest, $this->fileSystem);
			return;
		}

		$this->hash = md5($source);

		$this->document = $cache->read($this->hash);

		if ($this->document == false || $this->document->hasIncludes() == true) {
			$this->document = new Document(0, count($rest), '', $rest, $this->fileSystem);
			$cache->write($this->hash, $this->document);
		}
	}

	/**
	 * Check for cached includes; if there are - do not use cache
	 *
	 * @see Document::hasIncludes()
	 * @return boolean
	 */
	public function hasIncludes()
	{
		if ($this->document->hasIncludes() == true) {
			return true;
		}

		$source = $this->fileSystem->readTemplateFile($this->templateName);

		if (Template::getCache()->exists(md5($source)) && $this->hash === md5($source)) {
			return false;
		}

		return true;
	}

	/**
	 * Renders the node
	 *
	 * @param Context $context
	 *
	 * @return string
	 */
	public function render(Context $context)
	{
		$context->push();
		$result = $this->document->render($context);
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
		return 'extends';
	}
}
