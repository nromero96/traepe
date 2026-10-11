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

$commerceSchema = json_decode(file_get_contents($directory.'/schemas/marketplace-local-draft-commerce-operation.v1.json'), flags: JSON_THROW_ON_ERROR);
$commerce = json_decode('{"schema_version":1,"merchant":{"public_id":"01ARZ3NDEKTSV4RRFFQ69G5FAZ","legal_name":" Synthetic Legal ","trade_name":"Synthetic Trade","status":"draft","version":1},"branch":{"public_id":"01ARZ3NDEKTSV4RRFFQ69G5FB0","market_public_id":"01ARZ3NDEKTSV4RRFFQ69G5FB1","name":"Synthetic Branch","longitude":0.5,"latitude":1.5,"timezone":"Etc/UTC","status":"draft","version":1}}', flags: JSON_THROW_ON_ERROR);
$validator = new Validator;
$validator->validate($commerce, $commerceSchema);
if (! $validator->isValid()) {
    throw new RuntimeException('Commerce snapshot contract rejected.');
}
foreach ([['unexpected', true], ['schema_version', 2], ['merchant.status', 'active'], ['merchant.public_id', '11'], ['merchant.legal_name', ' '], ['merchant.trade_name', "control\n"], ['branch.longitude', 181], ['branch.latitude', '1.5'], ['branch.timezone', 'America/Lima'], ['branch.version', 0], ['branch.id', 11]] as [$path, $value]) {
    $invalidCommerce = json_decode(json_encode($commerce, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    $parts = explode('.', $path);
    if (count($parts) === 1) {
        $invalidCommerce->{$parts[0]} = $value;
    } else {
        $invalidCommerce->{$parts[0]}->{$parts[1]} = $value;
    }
    $negative = new Validator;
    $negative->validate($invalidCommerce, $commerceSchema);
    if ($negative->isValid()) {
        throw new RuntimeException('Malformed commerce snapshot was accepted.');
    }
}
echo "PASS: commerce snapshot v1; open shape, invalid references/state/names/coordinates/timezone rejected.\n";
