<?php

declare(strict_types=1);

use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\DTO\PackageHealthResult;
use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\DTO\PackageRecommendation;
use Satheez\PackageDoctor\DTO\ProjectHealthReport;
use Satheez\PackageDoctor\DTO\ProjectInfo;
use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\RecommendationType;
use Satheez\PackageDoctor\Enums\UpgradeType;
use Satheez\PackageDoctor\Output\ConsoleReportRenderer;
use Satheez\PackageDoctor\Output\CsvReportRenderer;
use Satheez\PackageDoctor\Output\JsonReportRenderer;
use Symfony\Component\Console\Output\BufferedOutput;

function makeReport(array $options = []): ProjectHealthReport
{
    $status = $options['status'] ?? PackageStatus::Healthy;
    $upgradeType = $options['upgradeType'] ?? UpgradeType::Minor;

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
        status: $status,
        latestVersion: '1.1.0',
        latestAllowedVersion: null,
        upgradeType: $upgradeType,
        isConstraintBlocked: false,
        issues: $options['issues'] ?? [],
        recommendation: new PackageRecommendation(
            type: $options['recommendationType'] ?? RecommendationType::SafeUpgrade,
            message: $options['recommendationMessage'] ?? 'A safe upgrade is available — run composer update.',
        ),
        changelogUrl: $options['changelogUrl'] ?? null,
        replacementPackage: $options['replacementPackage'] ?? null,
    );

    return new ProjectHealthReport(
        project: new ProjectInfo(
            phpVersion: '8.2.0',
            laravelVersion: '11.0.0',
            composerVersion: null,
            basePath: '/app',
        ),
        results: [$result],
        summary: $options['summary'] ?? [
            'total_packages' => 1,
            'project_score' => $options['score'] ?? 95,
            'healthy_count' => $status === PackageStatus::Healthy ? 1 : 0,
            'watch_count' => $status === PackageStatus::Watch ? 1 : 0,
            'risky_count' => $status === PackageStatus::Risky ? 1 : 0,
            'critical_count' => $status === PackageStatus::Critical ? 1 : 0,
            'ignored_count' => $status === PackageStatus::Ignored ? 1 : 0,
            'safe_upgrade_count' => $upgradeType === UpgradeType::Patch || $upgradeType === UpgradeType::Minor ? 1 : 0,
        ],
        warnings: $options['warnings'] ?? [],
    );
}

function parseCsv(string $csv): array
{
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $csv);
    rewind($handle);

    $rows = [];
    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
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

test('JSON output includes changelog and replacement package fields', function (): void {
    $renderer = new JsonReportRenderer;
    $decoded = json_decode($renderer->render(makeReport([
        'changelogUrl' => 'https://github.com/vendor/package/releases',
        'replacementPackage' => 'vendor/new-package',
    ])), true);

    $pkg = $decoded['packages'][0];

    expect($pkg['changelog_url'])->toBe('https://github.com/vendor/package/releases');
    expect($pkg['replacement_package'])->toBe('vendor/new-package');
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

// CsvReportRenderer

test('CSV output has expected header', function (): void {
    $renderer = new CsvReportRenderer;
    $rows = parseCsv($renderer->render(makeReport()));

    expect($rows[0])->toBe([
        'package',
        'current_version',
        'latest_version',
        'latest_allowed_version',
        'upgrade_type',
        'constraint_blocked',
        'dependency_type',
        'score',
        'status',
        'issue_count',
        'issue_codes',
        'issue_severities',
        'issue_score_impacts',
        'issue_messages',
        'recommendation_type',
        'recommendation_message',
        'changelog_url',
        'replacement_package',
    ]);
});

test('CSV output includes one row per package', function (): void {
    $renderer = new CsvReportRenderer;
    $rows = parseCsv($renderer->render(makeReport()));

    expect($rows)->toHaveCount(2);
    expect($rows[1][0])->toBe('vendor/package');
    expect($rows[1][1])->toBe('1.0.0');
    expect($rows[1][8])->toBe('healthy');
});

test('CSV output flattens issues and escapes values', function (): void {
    $renderer = new CsvReportRenderer;
    $rows = parseCsv($renderer->render(makeReport([
        'issues' => [
            new PackageIssue(
                code: 'constraint_blocked',
                severity: IssueSeverity::Risk,
                message: "Latest version is blocked, review\ncomposer.json.",
                scoreImpact: -15,
            ),
        ],
        'recommendationMessage' => "Review changelog, then update\nconstraint.",
    ])));

    expect($rows[1][9])->toBe('1');
    expect($rows[1][10])->toBe('constraint_blocked');
    expect($rows[1][11])->toBe('risk');
    expect($rows[1][12])->toBe('-15');
    expect($rows[1][13])->toBe("Latest version is blocked, review\ncomposer.json.");
    expect($rows[1][15])->toBe("Review changelog, then update\nconstraint.");
});

test('CSV output for empty reports contains header only', function (): void {
    $renderer = new CsvReportRenderer;
    $report = new ProjectHealthReport(
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

    $rows = parseCsv($renderer->render($report));

    expect($rows)->toHaveCount(1);
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

test('Console output shows unchanged project score trend', function (): void {
    $renderer = new ConsoleReportRenderer;
    $output = new BufferedOutput;
    $report = makeReport(['score' => 95]);
    $report = new ProjectHealthReport(
        project: $report->project,
        results: $report->results,
        summary: [
            ...$report->summary,
            'previous_score' => 95,
        ],
        warnings: [],
    );

    $renderer->render($report, $output, []);

    expect($output->fetch())->toContain('95 (± 0)/100');
});

test('Console output shows ignored package count and score placeholder', function (): void {
    $renderer = new ConsoleReportRenderer;
    $output = new BufferedOutput;
    $report = makeReport([
        'score' => 100,
        'status' => PackageStatus::Ignored,
        'upgradeType' => UpgradeType::None,
        'recommendationType' => RecommendationType::IgnoreConfigured,
        'recommendationMessage' => 'Ignored: Internal package',
    ]);

    $renderer->render($report, $output, []);

    $content = $output->fetch();

    expect($content)->toContain('Ignored: 1');
    expect((bool) preg_match('/vendor\/package\s+\|\s+1\.0\.0\s+\|\s+1\.1\.0\s+\|\s+none\s+\|\s+-\s+\|\s+Ignored\s+\|\s+Ignored: Internal package/', $content))->toBeTrue();
});
