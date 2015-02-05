<?php

// Quick tests on display type behaviour - move into behat as tests to
// run at the beginning?

require_once(__DIR__ . '/Record.php');
require_once(__DIR__ . '/utils.php');

$collection_xml = file_get_contents(__DIR__ . '/../fixtures/collection-example-01.xml');
$metadata_xml = file_get_contents(__DIR__ . '/../fixtures/enter_metadata-example-01.xml');

$r = fezapi\client\get_actions($collection_xml);
print_r($r);

$disp = fezapi\client\Record::createFromMetadataXml($metadata_xml);
print_r($disp);
echo $disp->toXml() . PHP_EOL;
print_r($disp->requiredFields()) . PHP_EOL;
