<?php

declare(strict_types=1);

use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\DTO\PackageHealthResult;
use Satheez\PackageDoctor\DTO\PackageRecommendation;
use Satheez\PackageDoctor\DTO\ProjectHealthReport;
use Satheez\PackageDoctor\DTO\ProjectInfo;
use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\RecommendationType;
use Satheez\PackageDoctor\Enums\UpgradeType;
use Satheez\PackageDoctor\Output\ConsoleReportRenderer;
use Satheez\PackageDoctor\Output\JsonReportRenderer;
use Symfony\Component\Console\Output\BufferedOutput;

function makeReport(array $options = []): ProjectHealthReport
{
    $package = new InstalledPackage(
        name: 'vendor/package',
        version: '1.0.0',
        dependencyType: DependencyType::Direct,
        constraint: '^1.0',
        sourceUrl: null,
        distUrl: null,
        requires: [],
        extra: [],
    );

    $result = new PackageHealthResult(
        package: $package,
        metadata: null,
        score: $options['score'] ?? 95,
        status: $options['status'] ?? PackageStatus::Healthy,
        latestVersion: '1.1.0',
        latestAllowedVersion: null,
        upgradeType: UpgradeType::Minor,
        isConstraintBlocked: false,
        issues: [],
        recommendation: new PackageRecommendation(
            type: RecommendationType::SafeUpgrade,
            message: 'A safe upgrade is available — run composer update.',
        ),
    );

    return new ProjectHealthReport(
        project: new ProjectInfo(
            phpVersion: '8.2.0',
            laravelVersion: '11.0.0',
            composerVersion: null,
            basePath: '/app',
        ),
        results: [$result],
        summary: [
            'total_packages' => 1,
            'project_score' => $options['score'] ?? 95,
            'healthy_count' => 1,
            'watch_count' => 0,
            'risky_count' => 0,
            'critical_count' => 0,
            'safe_upgrade_count' => 1,
        ],
        warnings: $options['warnings'] ?? [],
    );
}

// JsonReportRenderer

test('JSON output is valid JSON', function (): void {
    $renderer = new JsonReportRenderer;
    $json = $renderer->render(makeReport());

    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
});

test('JSON output has required top-level keys', function (): void {
    $renderer = new JsonReportRenderer;
    $decoded = json_decode($renderer->render(makeReport()), true);

    expect($decoded)->toHaveKeys(['project', 'summary', 'packages', 'warnings', 'metadata']);
});

test('JSON output package has required fields', function (): void {
    $renderer = new JsonReportRenderer;
    $decoded = json_decode($renderer->render(makeReport()), true);

    $pkg = $decoded['packages'][0];

    expect($pkg)->toHaveKeys([
        'name', 'current_version', 'latest_version', 'latest_allowed_version',
        'upgrade_type', 'constraint_blocked', 'dependency_type',
        'score', 'status', 'issues', 'recommendation',
    ]);
});

test('JSON output recommendation has type and message', function (): void {
    $renderer = new JsonReportRenderer;
    $decoded = json_decode($renderer->render(makeReport()), true);

    $rec = $decoded['packages'][0]['recommendation'];

    expect($rec)->toHaveKeys(['type', 'message']);
    expect($rec['type'])->toBe('safe_upgrade');
});

test('JSON output metadata has generated_at', function (): void {
    $renderer = new JsonReportRenderer;
    $decoded = json_decode($renderer->render(makeReport()), true);

    expect($decoded['metadata']['generated_at'])->toBeString()->not->toBeEmpty();
});

test('JSON output includes warnings array', function (): void {
    $renderer = new JsonReportRenderer;
    $decoded = json_decode($renderer->render(makeReport(['warnings' => ['test warning']])), true);

    expect($decoded['warnings'])->toContain('test warning');
});

// ConsoleReportRenderer

test('Console output contains package name', function (): void {
    $renderer = new ConsoleReportRenderer;
    $output = new BufferedOutput;

    $renderer->render(makeReport(), $output, []);

    expect($output->fetch())->toContain('vendor/package');
});

test('Console output contains summary information', function (): void {
    $renderer = new ConsoleReportRenderer;
    $output = new BufferedOutput;

    $renderer->render(makeReport(), $output, []);

    $content = $output->fetch();

    expect($content)->toContain('Summary');
    expect($content)->toContain('Project score');
});

test('Console output contains PHP and Laravel versions', function (): void {
    $renderer = new ConsoleReportRenderer;
    $output = new BufferedOutput;

    $renderer->render(makeReport(), $output, []);

    $content = $output->fetch();

    expect($content)->toContain('8.2.0');
    expect($content)->toContain('11.0.0');
});

test('Console output shows warnings when present', function (): void {
    $renderer = new ConsoleReportRenderer;
    $output = new BufferedOutput;

    $renderer->render(makeReport(['warnings' => ['a test warning']]), $output, []);

    expect($output->fetch())->toContain('a test warning');
});

test('Console output shows no packages message when empty report', function (): void {
    $renderer = new ConsoleReportRenderer;
    $output = new BufferedOutput;

    $emptyReport = new ProjectHealthReport(
        project: new ProjectInfo('8.2.0', '11.0.0', null, '/app'),
        results: [],
        summary: [
            'total_packages' => 0,
            'project_score' => 100,
            'healthy_count' => 0,
            'watch_count' => 0,
            'risky_count' => 0,
            'critical_count' => 0,
            'safe_upgrade_count' => 0,
        ],
        warnings: [],
    );

    $renderer->render($emptyReport, $output, []);

    expect($output->fetch())->toContain('No packages');
});
