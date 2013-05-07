<?php

class XmlExtractorTest extends PHPUnit_Framework_TestCase {
	protected static $source;

	/**
	 * @param string $xml XML document
	 * @param string $tags XmlExtractor parameter
	 * @param bool $ra XmlExtractor parameter
	 * @param bool $ma XmlExtractor parameter
	 * @return XmlExtractor
	 */
	private function getExtractor($xml, $tags = "root/tag", $ra = false, $ma = false) {
		$source = new XmlExtractor($tags, null, $ra, $ma);
		$source->loadXml($xml);
		return $source;
	}

	public function testSingleRootTag() {
		$xml = "<root></root>";
		$source = $this->getExtractor($xml, "root");

		foreach ($source as $i => $row) {
			/** @var XmlItem $row */
			$this->assertTrue($row->isEmpty());
			$this->assertEquals("root", $row->getName());
			$this->assertEquals(0, $i);
			$this->assertFalse($row->getValue());
		}

		foreach ($source as $i => $row) {
			$data = $row->export();
			$this->assertEmpty($data);

		}

		$this->assertEquals(1, $source->count());
	}

	public function testSkipRootTag() {
		$xml = "<root><tag></tag></root>";
		$source = $this->getExtractor($xml);
		foreach ($source as $i => $row) {
			/** @var XmlItem $row */
			$this->assertTrue($row->isEmpty());
			$this->assertEquals(0, $i);
			$this->assertTrue(isset($row->tag));
			$this->assertEquals("", $row->tag);
		}
		$this->assertEquals(1, $source->count());
	}

}

