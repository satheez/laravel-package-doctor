<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

final readonly class PackageMetadata
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $latestVersion,
        public ?string $latestAllowedVersion,
        public bool $isAbandoned,
        public ?string $replacementPackage,
        public ?int $downloads,
        public ?string $license,
        public ?string $repositoryUrl,
        public ?string $githubOwner,
        public ?string $githubRepo,
        public ?int $githubStars,
        public ?int $githubOpenIssues,
        public ?bool $githubArchived,
        public ?\DateTimeImmutable $githubPushedAt,
        public ?\DateTimeImmutable $latestReleaseAt,
        public ?string $documentationUrl,
    ) {}
}
