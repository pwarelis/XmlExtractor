<?php

/**
 * Class XmlItem
 *
 * An object for an XML tag extracted by XmlExtractor. Data associated with a
 * tag includes attributes, a value or descendant tags.
 *
 * @author Paul Warelis <pwarelis@gmail.com>
 */
class XmlItem extends stdClass implements Iterator {
	/** @var XmlItem|null $__attr The attribute object, null if attributes not present */
	private $__attr = null;
	/** @var string $__name The name of the tag */
	private $__name = "";
	/** @var string $__value The text value of the tag if present */
	private $__value = false;
	/** @var array $__data The tag's descendants */
	protected $__data = array();
	private $__collection = false;
	private $__current = false;

	public function __construct($name = '') {
		$this->setName($name);
	}

	private function add() {
		foreach (func_get_args() as $param) {
			$this->__data[] = $param;
		}
	}

	/**
	 * This method contains behaviour for a special xml case
	 *
	 * <Outside>
	 * 	<Inside>Value 1</Inside>
	 * </Outside>
	 *
	 * The resulting object will be:
	 *
	 * $item->Outside->Inside == "Value 1"
	 *
	 * If there are multiple tags with the same name, they will become an array
	 *
	 * <Outside>
	 * 	<Inside>Value 1</Inside>
	 * 	<Inside>Value 2</Inside>
	 * </Outside>
	 *
	 * And the resulting object will have different structure:
	 *
	 * $item->Outside->Inside[0] == "Value 1"
	 * $item->Outside->Inside[1] == "Value 2"
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function setProperty($name, $value) {
		if (array_key_exists($name, $this->__data)) {
			$item = $this->__data[$name];
			if ($item instanceof XmlItem && !empty($item->__data)) {
				$item->add($value);
			} else {
				$xml = new XmlItem($name);
				$xml->__collection = true;
				$xml->add($item, $value);
				$this->__data[$name] = $xml;
			}
		} else {
			$this->__data[$name] = $value;
		}
	}

	/**
	 * @param bool $mergeAttributes If true, will merge the attributes into the exported array as regular values
	 * @return array
	 */
	public function export($mergeAttributes = false) {
		if ($mergeAttributes && $this->__attr) {
			$export = $this->__attr->export();
		} else {
			$export = array();
		}
		foreach ($this->__data as $name => $item) {
			if ($item instanceof XmlItem) {
				$field = $this->__collection ? $name : $item->getName();
				$export[$field] = $item->export($mergeAttributes);
			} else {
				$export[$name] = $item;
			}
		}
		if ($this->__value !== false) {
			if ($mergeAttributes) {
				$export[$this->__name] = $this->__value;
			} else {
				$export = $this->__value;
			}
		} else {
			if (empty($export)) $export = "";
		}
		return $export;
	}

	/**
	 * Copy all node attributes into the XmlItem's attribute property
	 * @param XMLReader $xml The XML node
	 */
	public function copyAttributes($xml) {
		if (!$xml->hasAttributes) return;
		$this->__attr = new XmlItem;
		while($xml->moveToNextAttribute()) {
			$this->__attr->setProperty($xml->name, $xml->value);
		}
		// Reset the cursor to point back at the element
		$xml->moveToElement();
	}

	/**
	 * @param bool $unsetAttributes If true, will reset the internal attribute list
	 */
	public function mergeAttributes($unsetAttributes = true) {
		if ($this->__value) {
			$this->setProperty($this->__name, $this->__value);
			$this->__value = false;
		}
		if ($this->__attr) {
			foreach ($this->__attr as $name => $value) {
				if (!$this->__isset($name)) {
					$this->setProperty($name, $value);
				}
			}
			if ($unsetAttributes) unset($this->__attr);
		}
		foreach ($this as $item) {
			if ($item instanceof XmlItem) $item->mergeAttributes($unsetAttributes);
		}
	}

	/**
	 * Gets the value of an attribute if a name is specified. Will return all
	 * attributes if no name is supplied. Returns null if a name is not found.
	 *
	 * @param string|null $name Name of a specific attribute
	 * @return XmlItem|string|null
	 */
	public function getAttribute($name = null) {
		if (!$this->__attr) return array();
		$attr = $this->__attr->export();
		if (is_null($name)) return $attr;
		return array_key_exists($name, $attr) ? $attr[$name] : null;
	}

	public function getAttributes() {
		return $this->getAttribute();
	}

	public function isEmpty() {
		return is_null($this->__attr) && empty($this->__data);
	}

	public function setName($name) {
		$this->__name = $name;
	}

	public function getName() {
		return $this->__name;
	}

	public function setValue($value) {
		$this->__value = $value;
	}

	public function getValue() {
		return $this->__value;
	}

	// ----------------------------------------------------
	// Iterator methods
	// ----------------------------------------------------

	public function current() {
		return $this->__current = current($this->__data);
	}

	public function next() {
		return $this->__current = next($this->__data);
	}

	public function key() {
		return key($this->__data);
	}

	public function valid() {
		return !!$this->__current;
	}

	public function rewind() {
		return $this->__current = reset($this->__data);
	}

	// ----------------------------------------------------
	// Standard magic methods
	// ----------------------------------------------------

	public function __get($name) {
		return $this->__isset($name) ? $this->__data[$name] : null;
	}

	public function __isset($name) {
		return array_key_exists($name, $this->__data);
	}

}
