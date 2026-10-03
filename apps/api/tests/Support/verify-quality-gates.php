<?php

use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);
$directory = $root.'/storage/framework/quality-probe-'.bin2hex(random_bytes(8));
mkdir($directory, 0770, true);
$fixtures = [
    'QualityFailureTest.php' => "<?php\ntest('controlled failure', function () { expect(true)->toBeFalse(); });\n",
    'FormatFailure.php' => "<?php\nfunction qualityFormatProbe( ): string{return 'probe';}\n",
    'AnalysisFailure.php' => "<?php\nfunction qualityAnalysisProbe(): string { return 123; }\n",
];

try {
    foreach ($fixtures as $name => $source) {
        file_put_contents($directory.'/'.$name, $source);
    }
    $checks = [
        'Pest' => [[PHP_BINARY, 'vendor/bin/pest', '--ci', '--colors=never', $directory.'/QualityFailureTest.php'], '1 failed'],
        'Pint' => [[PHP_BINARY, 'vendor/bin/pint', '--test', $directory.'/FormatFailure.php'], 'FAIL'],
        'PHPStan' => [[PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--no-progress', '--error-format=raw', '--memory-limit=1G', $directory.'/AnalysisFailure.php'], 'should return string but returns int'],
    ];
    foreach ($checks as $name => [$command, $expected]) {
        $process = new Process($command, $root, timeout: 120);
        $process->run();
        $output = $process->getOutput().$process->getErrorOutput();
        if ($process->getExitCode() !== 1 || ! str_contains($output, $expected)) {
            throw new RuntimeException('Unexpected gate result: '.$name);
        }
        echo "PASS: {$name} rejects its controlled failure with exit code 1.\n";
    }
} finally {
    foreach (array_keys($fixtures) as $name) {
        if (is_file($directory.'/'.$name)) {
            unlink($directory.'/'.$name);
        }
    }
    rmdir($directory);
}
