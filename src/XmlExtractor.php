<?php

/**
 * Class XmlExtractor
 *
 * Extracts records from XML.
 *
 * @author Paul Warelis <pwarelis@gmail.com>
 */
class XmlExtractor implements Iterator, Countable {
	/** @var int $count The total number of items in the file */
	private $count;

	/** @var int $position Index of the current record */
	private $position = 0;

	/** @var XmlItem|array $record The current record we're dealing with */
	private $record;

	/** @var string $tag The target tag that we are retrieving */
	private $tag;

	/** @var string $file The absolute path to the file we're reading. Urls are legit. */
	private $filename;

	/** @var string $raw The raw XML to parse */
	private $raw = null;

	/** @var XmlItem[] $tags The tags that we're going into. This will hold the attributes */
	private $tags = array();

	/** @var boolean $returnArray If true, returns the request data as arrays, otherwise XmlItem */
	private $returnArray;

	/** @var bool $mergeAttributes If true, the attributes are merged into the data (doesn't overwrite existing properties) */
	private $mergeAttributes;

	/** @var XMLReader $reader */
	private $reader = null;

	/** @var integer $depth */
	private $depth;

	private $valid = array(XMLReader::ELEMENT, XMLReader::END_ELEMENT, XMLReader::TEXT, XMLReader::CDATA);

	public function __construct($tag, $filename = null, $returnArray = false, $mergeAttributes = false) {
		if ($filename && !file_exists($filename)) {
			throw new Exception("File doesn't exist: {$filename}");
		}
		$this->filename = $filename;
		$this->returnArray = $returnArray;
		$this->mergeAttributes = $mergeAttributes;
		$tags = explode("/", $tag);
		$this->tag = array_pop($tags);
		// If we're using wildcards, it means pickup anything in the root tag path
		if ($this->tag == '*') $this->tag = true;
		foreach ($tags as $tag) $this->tags[$tag] = null;
	}

	public function getRootTags() {
		return $this->tags;
	}

	public function __destruct() {
		if ($this->reader) $this->reader->close();
		unset($this->record);
	}

	public function loadXml($xml) {
		$this->raw = $xml;
	}

	private function isRootTag($name) {
		if ($this->depth > 0 || empty($this->tags)) return false;

		$rootsDone = true;
		foreach ($this->tags as $tag) {
			if (!is_null($tag)) continue;
			$rootsDone = false;
			break;
		}

		if (!$rootsDone) {
			// Not all root tags are accounted for, see if this is one of them
			if (array_key_exists($name, $this->tags) && is_null($this->tags[$name])) {
				return true;
			} else {
				throw new Exception("Unspecified root tag found in file: {$name}");
			}
		}

		// We're accepting all tags inside the root structure
		if ($this->tag === true) return false;

		// We're only accepting a specific tag in the root structure, throw an exception if it's different
		if ($this->tag === $name) return false;
		throw new Exception("Loaded tag ({$name}) does not match expected tag ({$this->tag})");
	}

	private function getRecord($xml = null, $skipRead = false) {
		/** @var XmlItem $data */
		$data = null;
		$xml = $xml ?: $this->reader;

		$child = null;
		$continue = true;
		do {
			if (!$skipRead) {
				$continue = $xml->read();
				if (!$continue) break;
			}
			$skipRead = false;

			$name = $xml->name;
			$type = $xml->nodeType;

			// Some tags we don't care about
			if (!in_array($type, $this->valid)) continue;
			if ($type == XMLReader::END_ELEMENT) break;

			// If the tag is one that we're going into, just save the attributes
			if ($this->isRootTag($name)) {
				$this->tags[$name] = new XmlItem($name);
				$this->tags[$name]->copyAttributes($xml);
			} else {

				if (is_null($data)) {
					$data = new XmlItem($name);
					$data->copyAttributes($xml);

					$this->depth++;

				} elseif ($type == XMLReader::ELEMENT) {

					// In here we compile the tags descendants
					$child = $this->getRecord($xml, true);

					if ($child->isEmpty()) {
						// Simple value, no value is always an empty string
						$value = $child->getValue() ?: "";
						$data->setProperty($child->getName(), $value);
					} else {
						// Complex value (array or has attributes)
						$data->setProperty($child->getName(), $child);
					}
				}

				if ($xml->hasValue) $data->setValue($xml->value);

				if ($child) continue;
				if ($xml->isEmptyElement) break;
			}

		} while ($continue);

		if ($data) {
			$this->depth--;
			if ($this->depth == 0) {
				if ($this->returnArray) {
					$data = $data->export($this->mergeAttributes);
				} elseif ($this->mergeAttributes) {
					$data->mergeAttributes();
				}
			}
		}
		return $data;
	}

	public final function current() {
		return $this->record;
	}

	public final function key() {
		return $this->position;
	}

	public final function next() {
		++$this->position;
		$this->depth = 0;
		$this->record = $this->getRecord();
		if ($this->record) $this->count++;
		return $this->record;
	}
	
	public final function rewind() {
		if ($this->reader) $this->reader->close();

		$this->reader = new XMLReader();
		if (is_null($this->filename)) {
			$this->reader->xml($this->raw, "UTF8");
		} else {
			$this->reader->open($this->filename, "UTF8");
		}

		$this->count = 0;
		$this->position = -1;
		$this->next();
	}
	
	public final function valid()	 {
		return !is_null($this->record);
	}

	public function count() {
		return $this->count;
	}
}
