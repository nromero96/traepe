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
