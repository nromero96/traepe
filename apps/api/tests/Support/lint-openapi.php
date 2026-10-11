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

$catalogSchema = json_decode(file_get_contents($directory.'/schemas/catalog-local-draft-operation.v1.json'), flags: JSON_THROW_ON_ERROR);
$catalog = json_decode('{"schema_version":1,"merchant_public_id":"01ARZ3NDEKTSV4RRFFQ69G5FAZ","catalog":{"public_id":"01ARZ3NDEKTSV4RRFFQ69G5FB0","name":" Synthetic Catalog ","status":"draft","version":1},"product":{"public_id":"01ARZ3NDEKTSV4RRFFQ69G5FB1","catalog_public_id":"01ARZ3NDEKTSV4RRFFQ69G5FB0","name":"Synthetic Product","description":null,"brand":null,"status":"draft","version":1}}', flags: JSON_THROW_ON_ERROR);
foreach ([[null, null], [str_repeat('á', 3999)."\n", str_repeat('ñ', 255)], ['', 'Synthetic Brand']] as [$description, $brand]) {
    $catalog->product->description = $description;
    $catalog->product->brand = $brand;
    $validator = new Validator;
    $validator->validate($catalog, $catalogSchema);
    if (! $validator->isValid()) {
        throw new RuntimeException('Catalog snapshot contract rejected.');
    }
}
foreach ([['unexpected', true], ['schema_version', 2], ['merchant_public_id', '11'], ['catalog.status', 'active'], ['catalog.name', ' '], ['product.public_id', "01ARZ3NDEKTSV4RRFFQ69G5FB1\n"], ['product.name', "control\n"], ['product.description', "\tcontrol"], ['product.description', str_repeat('á', 4001)], ['product.brand', ' '], ['product.brand', 1], ['product.product_type', 'unrestricted'], ['product.id', 11]] as [$path, $value]) {
    $invalidCatalog = json_decode(json_encode($catalog, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    $parts = explode('.', $path);
    if (count($parts) === 1) {
        $invalidCatalog->{$parts[0]} = $value;
    } else {
        $invalidCatalog->{$parts[0]}->{$parts[1]} = $value;
    }
    $negative = new Validator;
    $negative->validate($invalidCatalog, $catalogSchema);
    if ($negative->isValid()) {
        throw new RuntimeException('Malformed catalog snapshot was accepted.');
    }
}
echo "PASS: catalog snapshot v1; nullable and Unicode limits accepted; open shape, invalid references/state/text rejected.\n";

$productId = '01ARZ3NDEKTSV4RRFFQ69G5FB1';
$catalogId = '01ARZ3NDEKTSV4RRFFQ69G5FB0';
$productOperation = json_decode('{"schema_version":1,"merchant_public_id":"01ARZ3NDEKTSV4RRFFQ69G5FAZ","catalog":{"public_id":"01ARZ3NDEKTSV4RRFFQ69G5FB0","name":"Synthetic","status":"draft","version_before":1,"version_after":2},"product":{"public_id":"01ARZ3NDEKTSV4RRFFQ69G5FB1","catalog_public_id":"01ARZ3NDEKTSV4RRFFQ69G5FB0","name":"Synthetic","description":null,"brand":null,"status":"draft","version":1}}', flags: JSON_THROW_ON_ERROR);
$productProfile = (object) ['schema_version' => 1, 'merchant_public_id' => $productOperation->merchant_public_id, 'name' => 'Synthetic', 'status' => 'draft', 'version' => 2];
$productPage = (object) ['data' => [(object) ['type' => 'local_draft_product', 'id' => $productId, 'attributes' => (object) ['schema_version' => 1, 'catalog_public_id' => $catalogId, 'name' => 'Synthetic', 'description' => null, 'brand' => null, 'status' => 'draft', 'version' => 1]]],
    'meta' => (object) ['correlation_id' => $catalogId, 'catalog_version' => 2, 'next_cursor' => null, 'has_more' => false, 'filters' => (object) ['catalog_public_id' => $catalogId, 'limit' => 20]]];
foreach (['catalog-local-draft-product-operation.v1.json' => $productOperation, 'catalog-local-draft-profile.v1.json' => $productProfile, 'catalog-local-draft-products-page.v1.json' => $productPage] as $file => $sample) {
    $contract = json_decode(file_get_contents($directory.'/schemas/'.$file), flags: JSON_THROW_ON_ERROR);
    $check = new Validator;
    $check->validate($sample, $contract);
    if (! $check->isValid()) {
        throw new RuntimeException('Product contract sample rejected.');
    }
    $bad = json_decode(json_encode($sample, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    $bad->unexpected = true;
    $reject = new Validator;
    $reject->validate($bad, $contract);
    if ($reject->isValid()) {
        throw new RuntimeException('Open product contract accepted.');
    }
}
$bad = clone $productOperation;
$bad->product = clone $productOperation->product;
$bad->product->description = "\tcontrol";
$reject = new Validator;
$reject->validate($bad, json_decode(file_get_contents($directory.'/schemas/catalog-local-draft-product-operation.v1.json')));
if ($reject->isValid()) {
    throw new RuntimeException('Invalid product description accepted.');
}
$bad = clone $productPage;
$bad->meta = clone $productPage->meta;
$bad->meta->next_cursor = 'invalid cursor';
$reject = new Validator;
$reject->validate($bad, json_decode(file_get_contents($directory.'/schemas/catalog-local-draft-products-page.v1.json')));
if ($reject->isValid()) {
    throw new RuntimeException('Invalid cursor contract accepted.');
}
echo "PASS: product operation/profile/page v1; closed shape, text and cursor negatives rejected.\n";

$changeSchema = json_decode(file_get_contents($directory.'/schemas/catalog-local-draft-product-change-operation.v1.json'), flags: JSON_THROW_ON_ERROR);
$change = clone $productOperation;
$change->product = (object) ['public_id' => $productId, 'catalog_public_id' => $catalogId,
    'before' => (object) ['name' => 'Synthetic', 'description' => null, 'brand' => null, 'status' => 'draft', 'version' => 1],
    'after' => (object) ['name' => 'Changed', 'description' => '', 'brand' => null, 'status' => 'draft', 'version' => 2]];
$check = new Validator;
$check->validate($change, $changeSchema);
if (! $check->isValid()) {
    throw new RuntimeException('Change snapshot rejected.');
}
foreach ([['unexpected', true], ['product.before.version', 0], ['product.after.version', 2147483648], ['product.before.status', 'active'], ['product.after.name', ' '], ['product.after.description', "\tbad"], ['product.before.brand', 1], ['product.after.actor', 'private'], ['product.catalog_public_id', "invalid\n"]] as [$path, $value]) {
    $bad = json_decode(json_encode($change, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    $node = $bad;
    $parts = explode('.', $path);
    $leaf = array_pop($parts);
    foreach ($parts as $part) {
        $node = $node->{$part};
    }
    $node->{$leaf} = $value;
    $reject = new Validator;
    $reject->validate($bad, $changeSchema);
    if ($reject->isValid()) {
        throw new RuntimeException('Invalid change snapshot accepted.');
    }
}
echo "PASS: product change snapshot v1; closed before/after, text and revision limits verified.\n";
