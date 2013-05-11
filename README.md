XmlExtractor
============

The XmlExtractor is a class that will parse XML very efficiently with the XMLReader object and produce an object (or array) for every item desired. This class can be used to read **very large** (read GB) XML files

How to Use
----------

In general, this is the pattern you would use to parse XML with XmlExtractor

```php
$source = new XmlExtractor("root/item", "/path/to/file.xml");
foreach ($source as $item) {
  echo $item->tag1;
  echo $item->tag2->subtag1;
}
```

### Options

There are four parameters you can pass the constructor

	XmlExtractor($rootTags, $filename, $returnArray, $mergeAttributes)

- `$rootTags` Specify how deep to go into the structure before extracting objects. Examples are below
- `$filename` Path to the xml file you want to parse. This is optional as you can pass an xml string with `loadXml()` method
- `$returnArray` If true, every iteration will return items as an associative array. Default is false
- `$mergeAttributes` If true, any attributes on extracted tags will be included in the returned record as additional tags. Examples below

### Methods

	XmlExtractor.loadXml($xml)

Loads XML structure from a php string

	XmlExtractor.getRootTags()

This will return the skipped root tags as objects as soon as they are available

	XmlItem.export($mergeAttributes = false)

Convert this xml record into an array. If `$mergeAttributes` is true, any attributes are merged into the array returned

	XmlItem.getAttribute($name)

Returns this record's named attribute

	XmlItem.getAttributes()

Returns this record's attributes if any

	XmlItem.mergeAttributes($unsetAttributes = false)

Merges the record's attributes with the rest of the tags so they are accessible as regular tags. If `unsetAttributes` is true, the internal attribute object will be removed

Examples
----------

### Iterating over xml items

Simple xml structure and straight forward php.

```xml
<earth>
	<people>
		<person>
			<name>
				<first>Paul</first>
				<last>Warelis</last>
			</name>
			<gender>Male</gender>
			<skill>Javascript</skill>
			<skill>PHP</skill>
			<skill>Beer</skill>
		</person>
	</people>
</earth>
```

```php
$source = new XmlExtractor("earth/people/person", "/path/to/above.xml");
foreach ($source as $person) {
  echo $person->name->first; // Paul
  echo $person->gender; // Male
  foreach ($person->skill as $skill) {
    echo $skill;
  }
  $record = $person->export();
}
```

The first constructor argument is a slash separated tag list that communicates to XmlExtractor that you want to extract "person" records (last tag entry) from earth -> people structure.
The export method on the `$person` object returns it in array form, which will look like this:

```php
array(
  'name' => array(
    'first' => 'Paul',
    'last' => 'Warelis'
  ),
  'gender' => 'Male'
  'skill' => array(
    '0' => 'Javascript',
    '1' => 'PHP',
    '2' => 'Beer'
  )
)
```

It's important to note that the repeating tag "skill" turned into an array.

### Dealing with attributes

This example demonstrates how to deal with attributes.

```xml
<office address="123 Main Street">
	<items total="2">
		<item name="desk">
			<size width="120" height="33" length="70">large</size>
			<image>desk.png</image>
		</item>
		<item image="cubicle.jpg">
			<name>cubicle</name>
			<size>
				<width>120</width>
				<height>33</height>
				<length>60</length>
				<size>large</size>
			</size>
		</item>
	</items>
</office>
```

There are a number of things going on with the above xml.
The two root tags that we have to skip to get to our items have information attached.
We can get at these with the `getRootTags()` method. The next issue is that both items are using attributes to define their data.
This example is a bit contrived, but it will show the functionality behind the **mergeAttributes** feature.
By the end of this example, we will have two items with identical structure.

```php
$office = new XmlExtractor("office/items/item", "/path/to/above.xml");
foreach ($office as $item) {
  $compressed = $item->export(true); // true = merge attributes into the item
  var_dump($compressed);
}
foreach ($office->getRootTags as $name => $tag) {
  echo "Tag name: {$name}";
  var_dump($tag->getAttributes());
}
```

Once "compressed" (exported with merged attributes) the structure of both items is the same.
In the event of an  attribute having the same name as the tag, the tag takes precedence and is never overwritten.
The two items will end up looking like this:

```php
array(
  'name' => 'desk',
  'size' => array(
    'width' => '120',
    'height' => '33',
    'length' => '70',
    'size' => 'large'
  ),
  'image' => 'desk.png'
)
array(
  'image' => 'cubicle.jpg'
  'name' => 'cubicle',
  'size' => array(
    'width' => '120',
    'height' => '33',
    'length' => '70',
    'size' => 'large'
  )
)
```

The root tags bit will come up with this:

```php
Tag name: office
array(
  'address' => '123 Main Street'
)
Tag name: items
array(
  'total' => '2'
)
```

Enjoy!

Contact
-------

pwarelis at gmail dot com
