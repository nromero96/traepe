<?php

use JsonSchema\Validator;

// Composer 2.8.12 already bundles a Draft-04 validator. No new dependency is installed.
Phar::loadPhar('/usr/local/bin/composer', 'composer.phar');
require 'phar://composer.phar/vendor/autoload.php';

$directory = '/var/www/docs/api';
$document = json_decode(file_get_contents($directory.'/openapi.yaml'), flags: JSON_THROW_ON_ERROR);
$schema = json_decode(file_get_contents($directory.'/schemas/openapi-3.0-2024-10-18.json'), flags: JSON_THROW_ON_ERROR);
$validator = new Validator;
$validator->validate($document, $schema);
if (! $validator->isValid()) {
    foreach ($validator->getErrors() as $error) {
        fwrite(STDERR, $error['property'].': '.$error['message']."\n");
    }
    exit(1);
}

// Prove that the validator actually rejects a malformed OpenAPI document.
$invalid = clone $document;
unset($invalid->info);
$negative = new Validator;
$negative->validate($invalid, $schema);
if ($negative->isValid()) {
    throw new RuntimeException('OpenAPI negative validation did not fail.');
}
echo "PASS: official OpenAPI 3.0 schema; malformed document rejected.\n";

$fixtureSchema = json_decode(file_get_contents($directory.'/schemas/marketplace-local-draft-fixture-operation.v1.json'), flags: JSON_THROW_ON_ERROR);
$fixture = (object) ['profile_version' => 'synthetic-origin-a-v1', 'country_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAZ',
    'market_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB2', 'zone_public_ids' => ['01ARZ3NDEKTSV4RRFFQ69G5FAZ', '01ARZ3NDEKTSV4RRFFQ69G5FB0', '01ARZ3NDEKTSV4RRFFQ69G5FB1']];
$validator = new Validator;
$validator->validate($fixture, $fixtureSchema);
if (! $validator->isValid()) {
    throw new RuntimeException('Fixture snapshot contract rejected.');
}
foreach (['unexpected' => true, 'profile_version' => 'operational', 'country_public_id' => '11', 'zone_public_ids' => [$fixture->zone_public_ids[0], $fixture->zone_public_ids[0], $fixture->zone_public_ids[0]]] as $property => $value) {
    $invalidFixture = clone $fixture;
    $invalidFixture->{$property} = $value;
    $negative = new Validator;
    $negative->validate($invalidFixture, $fixtureSchema);
    if ($negative->isValid()) {
        throw new RuntimeException('Malformed fixture snapshot was accepted.');
    }
}
echo "PASS: fixture snapshot v1; open shape, invalid profile/reference and repeated zones rejected.\n";
