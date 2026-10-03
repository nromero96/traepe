<?php

use Tests\Integration\PostgresTestCase;
use Tests\TestCase;

// Existing PHPUnit classes remain supported. New closure-based feature and
// integration tests receive the same application setup and isolated database.
pest()->extend(TestCase::class)->in('Feature');
pest()->extend(PostgresTestCase::class)->in('Integration');
