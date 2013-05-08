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

		foreach ($source as $row) {
			$data = $row->export();
			$this->assertEmpty($data);
		}

		$this->assertCount(1, $source);
	}

	public function testSkipRootTag() {
		$xml = "<root><tag></tag></root>";
		$source = $this->getExtractor($xml);
		foreach ($source as $i => $row) {
			/** @var XmlItem $row */
			$this->assertTrue($row->isEmpty());
			$this->assertEquals(0, $i);
			$this->assertEmpty($row->export());
		}
		$this->assertCount(1, $source);

		// Check root tags
		$roots = $source->getRootTags();
		$this->assertCount(1, $roots);
		$this->assertTrue(isset($roots['root']));
		$this->assertInstanceOf("XmlItem", $roots['root']);
		$this->assertTrue($roots['root']->isEmpty());
		$this->assertEmpty($roots['root']->export());
	}

	public function testClosedTag() {
		$xml = "<root><tag/><tag><field>value</field></tag></root>";
		$source = $this->getExtractor($xml);
		foreach ($source as $i => $row) {
			/** @var XmlItem $row */

			if ($i == 0) {
				$this->assertTrue($row->isEmpty());
				$this->assertEmpty($row->export());
			} else {
				$this->assertFalse($row->isEmpty());
				$this->assertEquals(array(
					"field" => "value"
				), $row->export());
			}
		}
		$this->assertCount(2, $source);
	}

	public function testTagAttributes() {
		$xml = <<<XML

	<root source='file' total='1'>
		<tag size='large' color='blue' field="Tag attribute field value">
			<field name="inside">Field value</field>
		</tag>
	</root>

XML;
		$source = $this->getExtractor($xml);
		foreach ($source as $row) {
			/** @var XmlItem $row */
			$this->assertFalse($row->isEmpty());

			$array = $row->export();
			$this->assertEquals(array("field" => "Field value"), $array);
			$this->assertEquals(1, count($array));

			// Merge attributes with export
			$array = $row->export(true);
			$this->assertEquals(array(
				"size" => "large",
				"color" => "blue",
				"field" => array(
					"field" => "Field value",
					"name" => "inside"
				)
			), $array);

			$this->assertEquals(array(
				"size" => "large",
				"color" => "blue",
				"field" => "Tag attribute field value"
			), $row->getAttribute());

			$this->assertEquals("large", $row->getAttribute("size"));
			$this->assertEquals("blue", $row->getAttribute("color"));
			$this->assertEquals("Tag attribute field value", $row->getAttribute("field"));

			// Merge the attributes permanently
			$row->mergeAttributes();
			$this->assertEmpty($row->getAttribute());
			$this->assertEquals(array(
				"size" => "large",
				"color" => "blue",
				"field" => array(
					"field" => "Field value",
					"name" => "inside"
				)
			), $row->export(true));
			$this->assertEquals($row->export(true), $row->export());

		}
		$this->assertCount(1, $source);


		// Check root tags
		$roots = $source->getRootTags();
		$this->assertCount(1, $roots);
		$this->assertTrue(isset($roots['root']));
		$root = $roots['root'];
		$this->assertInstanceOf("XmlItem", $root);
		$this->assertFalse($root->isEmpty());

		$this->assertEmpty($root->export());
		$this->assertEquals(array(
			"source" => "file",
			"total" => "1"
		), $root->export(true));
	}

	public function testReturningArrays() {
		$xml = <<<XML

	<root>
		<tag>
			<field>Field value</field>
		</tag>
	</root>

XML;
		$source = $this->getExtractor($xml, "root/tag", true);
		foreach ($source as $row) {
			$this->assertEquals(array(
				"field" => "Field value"
			), $row);
		}
		$this->assertCount(1, $source);
	}

	public function testReturnArrayWithMergedAttributesNoConflicts() {
		$xml = <<<XML
	<root>
		<tag size='large' color='blue'>
			<field>Field value</field>
		</tag>
	</root>
XML;
		$source = $this->getExtractor($xml, "root/tag", true, true);
		foreach ($source as $row) {
			$this->assertEquals(array(
				"field" => "Field value",
				"size" => "large",
				"color" => "blue"
			), $row);
		}
		$this->assertCount(1, $source);
	}

	public function testReturnArrayWithMergedAttributesWithConflicts() {
		$xml = <<<XML
	<root>
		<tag size='large' color='blue' field="Conflicted attribute">
			<field>Field value</field>
		</tag>
	</root>
XML;
		$source = $this->getExtractor($xml, "root/tag", true, true);
		foreach ($source as $row) {
			$this->assertEquals(array(
				"field" => "Field value",
				"size" => "large",
				"color" => "blue"
			), $row);
		}
		$this->assertCount(1, $source);
	}

	public function testReturnArrayWithMergedNestedAttributesWithConflicts() {
		$xml = <<<XML
	<root>
		<tag size='large' color='blue' field="Conflicted attribute">
			<field name="inside">Field value</field>
		</tag>
	</root>
XML;
		$source = $this->getExtractor($xml, "root/tag", true, true);
		foreach ($source as $row) {
			$this->assertEquals(array(
				"size" => "large",
				"color" => "blue",
				"field" => array(
					"field" => "Field value",
					"name" => "inside"
				)
			), $row);

		}
		$this->assertCount(1, $source);
	}

	public function testMergeAttributesOnly() {
		$xml = <<<XML
	<root>
		<tag size='large' color='blue' field="Conflicted attribute">
			<field name="inside">Field value</field>
		</tag>
	</root>
XML;
		$source = $this->getExtractor($xml, "root/tag", false, true);
		foreach ($source as $row) {
			/** @var XmlItem $row */
			$this->assertInstanceOf("XmlItem", $row);
			$this->assertEmpty($row->getAttribute());
			$this->assertEquals(array(
				"size" => "large",
				"color" => "blue",
				"field" => array(
					"field" => "Field value",
					"name" => "inside"
				)
			), $row->export());
			$this->assertEquals($row->export(true), $row->export());
		}
		$this->assertCount(1, $source);
	}

	public function testReadingFile() {

		$source = new XmlExtractor("root/item", __DIR__."/data/testfile.xml");

		foreach ($source as $row) {
			/** @var XmlItem $row */
			$this->assertInstanceOf("XmlItem", $row);
			$this->assertTrue(isset($row->multi));
			$this->assertInstanceOf("XmlItem", $row->multi);
			$this->assertEquals(array(
				"field1" => "Field 1",
				"field2" => "Field 2",
				"multi" => array(
					'0' => "Multi value 1",
					'1' => "Multi value 1",
					'2' => "Multi value 2"
				)
			), $row->export());
		}
		$this->assertCount(1, $source);
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage File doesn't exist: not_existent_file
	 */
	public function testFileDoesNotExist() {
		new XmlExtractor("", "not_existent_file");
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage Unspecified root tag found in file: root_tag
	 */
	public function testRootTagDoesNotExist() {
		$xml = "<root_tag><tag></tag></root_tag>";
		$source = $this->getExtractor($xml);
		foreach ($source as $i => $row) {}
	}

}

