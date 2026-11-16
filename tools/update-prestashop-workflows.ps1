param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot "..\.github\workflows")
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

$releaseSources = [ordered]@{
    "1.6.x" = [pscustomobject]@{
        Repository = "PrestaShop/PrestaShop-1.6"
        Pattern = '^1\.6\.\d+\.\d+$'
        Php = "5.6"
        Package = "16"
    }
    "1.7.x" = [pscustomobject]@{
        Repository = "PrestaShop/PrestaShop"
        Pattern = '^1\.7\.\d+\.\d+$'
        Php = "7.1"
        Package = "17"
    }
    "8.x" = [pscustomobject]@{
        Repository = "PrestaShop/PrestaShop"
        Pattern = '^8\.\d+\.\d+$'
        Php = "7.4"
        Package = "8"
    }
    "9.x" = [pscustomobject]@{
        Repository = "PrestaShop/PrestaShop"
        Pattern = '^9\.\d+\.\d+$'
        Php = "8.1"
        Package = "9"
    }
}

function Get-PrestaShopReleases {
    param(
        [string]$Repository,
        [string]$Pattern
    )

    $releases = [System.Collections.Generic.List[string]]::new()
    $pageNumber = 1
    do {
        $page = Invoke-RestMethod `
            -Uri "https://api.github.com/repos/$Repository/tags?per_page=100&page=$pageNumber" `
            -Headers @{ "User-Agent" = "appnuno-prestashop-workflow-generator" } `
            -TimeoutSec 30
        foreach ($tag in $page) {
            if ([string]$tag.name -match $Pattern) {
                $releases.Add([string]$tag.name)
            }
            elseif ([string]$tag.name -match '^\d+\.\d+\.\d+(\.\d+)?$') {
                $mapped = $false
                foreach ($knownSource in $releaseSources.Values) {
                    if ([string]$tag.name -match $knownSource.Pattern) {
                        $mapped = $true
                        break
                    }
                }
                if (-not $mapped) {
                    $parts = [string]$tag.name -split '\.'
                    $major = [int]$parts[0]
                    $minor = [int]$parts[1]
                    if ($major -gt 1 -or ($major -eq 1 -and $minor -ge 8)) {
                        throw "Official stable PrestaShop release $($tag.name) has no package/test mapping. Add support before generating workflows."
                    }
                }
            }
        }
        $pageNumber++
    } while ($page.Count -eq 100)

    return @($releases.ToArray() | Sort-Object { [version]$_ } -Unique)
}

function Get-DockerTags {
    param([string[]]$Prefixes)

    $tags = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
    foreach ($prefix in $Prefixes) {
        $pageUrl = "https://hub.docker.com/v2/repositories/prestashop/prestashop/tags?page_size=100&name=$([uri]::EscapeDataString($prefix))"
        while ($pageUrl) {
            $page = Invoke-RestMethod -Uri $pageUrl -TimeoutSec 30
            foreach ($tag in $page.results) {
                [void]$tags.Add([string]$tag.name)
            }
            $pageUrl = [string]$page.next
        }
    }

    return ,$tags
}

function Get-DockerTagPrefixes {
    param([string[]]$Releases)

    $prefixes = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
    foreach ($release in $Releases) {
        $parts = $release.Split(".")
        $prefix = if ($parts.Count -eq 4) {
            $parts[0..2] -join "."
        }
        else {
            $parts[0..1] -join "."
        }
        [void]$prefixes.Add($prefix)
    }

    return @($prefixes)
}

function Get-MySqlVersion {
    param([string]$Version)

    if ($Version -match '^1\.6\.0\.\d+$' -or $Version -eq "1.6.1.0") {
        return "5.6"
    }
    if ($Version -match '^1\.[67]\.') {
        return "5.7"
    }
    return "8.0"
}

function Get-ExplicitPhpDockerTag {
    param(
        [string]$Version,
        [string]$Php = "",
        [System.Collections.Generic.HashSet[string]]$DockerTags
    )

    $versionPattern = [regex]::Escape($Version)
    $matchingTags = @(
        $DockerTags |
            Where-Object { $_ -match "^$versionPattern-(\d+\.\d+)-apache$" } |
            Sort-Object {
                $tagPhp = [regex]::Match($_, "^$versionPattern-(\d+\.\d+)-apache$").Groups[1].Value
                [version]$tagPhp
            }
    )

    if ($Php) {
        $matchingTags = @(
            $matchingTags |
                Where-Object { [regex]::Match($_, "^$versionPattern-(\d+\.\d+)-apache$").Groups[1].Value -eq $Php }
        )
    }

    if ($matchingTags.Count -gt 0) {
        $tag = $matchingTags[-1]
        $phpVersion = [regex]::Match($tag, "^$versionPattern-(\d+\.\d+)-apache$").Groups[1].Value
        return [pscustomobject]@{
            Tag = $tag
            Php = $phpVersion
        }
    }

    $defaultApacheTag = "$Version-$Php"
    if ($Php -and $DockerTags.Contains($defaultApacheTag)) {
        return [pscustomobject]@{
            Tag = $defaultApacheTag
            Php = $Php
        }
    }

    return $null
}

function Get-SourceBaseImage {
    param(
        [string]$Line,
        [string[]]$Releases,
        [System.Collections.Generic.HashSet[string]]$DockerTags
    )

    if ($Line -eq "1.6.x") {
        $baseImage = "1.6-5.6-apache"
        if (-not $DockerTags.Contains($baseImage)) {
            $tags = Get-DockerTags -Prefixes @("1.6-5.6")
            foreach ($tag in $tags) {
                [void]$DockerTags.Add($tag)
            }
        }
        if (-not $DockerTags.Contains($baseImage)) {
            throw "The required official PrestaShop 1.6 PHP 5.6 base image $baseImage was not found."
        }
        return $baseImage
    }

    $source = $releaseSources[$Line]
    foreach ($release in @($Releases | Sort-Object { [version]$_ } -Descending)) {
        $candidate = Get-ExplicitPhpDockerTag -Version $release -Php $source.Php -DockerTags $DockerTags
        if ($candidate) {
            return $candidate.Tag
        }
    }

    throw "No official Apache PrestaShop base image with PHP $($source.Php) was found for line $Line."
}

function New-MatrixEntry {
    param(
        [string]$Version,
        [string]$Tag,
        [string]$Php,
        [string]$MySql,
        [string]$Kind,
        [bool]$BuildSource,
        [string]$BaseImage,
        [string]$Repository,
        [bool]$LegacyMySql
    )

    return [pscustomobject]@{
        Version = $Version
        Tag = $Tag
        Php = $Php
        MySql = $MySql
        Kind = $Kind
        BuildSource = $BuildSource
        BaseImage = $BaseImage
        Repository = $Repository
        LegacyMySql = $LegacyMySql
    }
}

function ConvertTo-MatrixYaml {
    param([object[]]$Entries)

    $lines = [System.Collections.Generic.List[string]]::new()
    foreach ($entry in $Entries) {
        $baseImage = if ($entry.BaseImage) { "prestashop/prestashop:$($entry.BaseImage)" } else { "" }
        $lines.Add("          - prestashop_version: `"$($entry.Version)`"")
        $lines.Add("            prestashop_tag: `"$($entry.Tag)`"")
        $lines.Add("            php_version: `"$($entry.Php)`"")
        $lines.Add("            mysql_tag: `"$($entry.MySql)`"")
        $lines.Add("            test_kind: `"$($entry.Kind)`"")
        $lines.Add("            build_source: $($entry.BuildSource.ToString().ToLowerInvariant())")
        $lines.Add("            base_image: `"$baseImage`"")
        $lines.Add("            source_repository: `"$($entry.Repository)`"")
        $lines.Add("            mysql_legacy: $($entry.LegacyMySql.ToString().ToLowerInvariant())")
    }
    return $lines -join [Environment]::NewLine
}

function New-WorkflowContent {
    param(
        [string]$Line,
        [string]$Package,
        [string]$Matrix,
        [bool]$CheckPhp54
    )

    $phpSyntaxStep = ""
    if ($CheckPhp54) {
        $phpSyntaxStep = @'
      - name: Check the module PHP 5.4 syntax
        run: docker run --rm -v "$PWD:/repo:ro" --entrypoint sh cyberpoison/php:5.4-apache@sha256:d3bf257f2a482571b07fcc8b2fb7a48258b06456fad9f6a9a64cb6ee98866df5 -c 'find /repo/src/prestashop/appnuno /repo/src/shared/php/appnuno-config.php /repo/tools/prestashop/tests/docker -name "*.php" -print0 | xargs -0 -r -n1 php -l'

'@
    }

    $template = @'
name: PrestaShop __LINE__ compatibility

on:
  push:
  pull_request:
  workflow_dispatch:

jobs:
  installation:
    runs-on: ubuntu-26.04
    strategy:
      fail-fast: false
      max-parallel: 4
      matrix:
        include:
__MATRIX__
    env:
      PRESTASHOP_TAG: ${{ matrix.prestashop_tag }}
      PRESTASHOP_VERSION: ${{ matrix.prestashop_version }}
      EXPECTED_PHP_VERSION: ${{ matrix.php_version }}
      APPNUNO_PRESTASHOP_LINE: __LINE__
      MYSQL_TAG: ${{ matrix.mysql_tag }}
      PRESTASHOP_BASE_IMAGE: ${{ matrix.base_image }}
      PRESTASHOP_SOURCE_REPOSITORY: ${{ matrix.source_repository }}
      COMPOSE_FILE: ${{ matrix.mysql_legacy && 'tools/prestashop/tests/docker/docker-compose.yml:tools/prestashop/tests/docker/docker-compose.mysql-legacy.yml' || 'tools/prestashop/tests/docker/docker-compose.yml' }}
    steps:
      - uses: actions/checkout@v6

      - name: Prepare PHP for the PrestaShop package build
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
          extensions: zip
          coverage: none

      - name: Build the PrestaShop __LINE__ package
        run: php tools/prestashop/build-package.php __PACKAGE__

__PHP_SYNTAX_STEP__      - name: Build the exact PrestaShop source image
        if: matrix.build_source
        run: |
          docker build \
            --file tools/prestashop/tests/docker/Dockerfile \
            --build-arg PRESTASHOP_BASE_IMAGE="$PRESTASHOP_BASE_IMAGE" \
            --build-arg PRESTASHOP_SOURCE_REPOSITORY="$PRESTASHOP_SOURCE_REPOSITORY" \
            --build-arg PRESTASHOP_SOURCE_TAG="$PRESTASHOP_VERSION" \
            --tag "prestashop/prestashop:$PRESTASHOP_TAG" tools/prestashop/tests/docker

      - name: Start PrestaShop with MySQL
        run: docker compose up -d --wait mysql prestashop

      - name: Verify the PrestaShop version and PHP version
        run: |
          actual_version=$(docker compose exec -T prestashop php -r 'require "/var/www/html/config/config.inc.php"; echo _PS_VERSION_;')
          actual_php=$(docker compose exec -T prestashop php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
          test "$actual_version" = "$PRESTASHOP_VERSION"
          test "$actual_php" = "$EXPECTED_PHP_VERSION"

      - name: Verify the PrestaShop MySQL installation
        run: docker compose run --rm installer-check

      - name: Install the AppNuno module
        run: |
          docker compose exec -T prestashop cp /scripts/prepare-module.php /tmp/appnuno-prepare-module.php
          docker compose exec -T prestashop php /tmp/appnuno-prepare-module.php

      - name: Check the AppNuno admin and storefront
        env:
          PRESTASHOP_SITE_URL: http://127.0.0.1:8088
          APPNUNO_ARTIFACT_DIR: ${{ runner.temp }}/prestashop-artifacts/${{ matrix.prestashop_version }}-${{ matrix.php_version }}-${{ matrix.test_kind }}
        run: |
          python3 -m pip install -r tools/prestashop/tests/requirements-ci.txt
          python3 -m playwright install --with-deps chromium
          python3 tools/prestashop/tests/integration/admin.py

      - name: Upload browser test artifacts
        if: always()
        uses: actions/upload-artifact@v7
        with:
          name: prestashop-${{ matrix.prestashop_version }}-${{ matrix.php_version }}-${{ matrix.test_kind }}-browser-artifacts
          path: ${{ runner.temp }}/prestashop-artifacts/${{ matrix.prestashop_version }}-${{ matrix.php_version }}-${{ matrix.test_kind }}
          if-no-files-found: warn
          retention-days: 7

      - name: Remove the temporary PrestaShop environment
        if: always()
        run: docker compose down --volumes --remove-orphans
'@

    return $template.
        Replace("__LINE__", $Line).
        Replace("__PACKAGE__", $Package).
        Replace("__MATRIX__", $Matrix).
        Replace("__PHP_SYNTAX_STEP__", $phpSyntaxStep)
}

$outputPath = [System.IO.Path]::GetFullPath($OutputDirectory)
New-Item -ItemType Directory -Path $outputPath -Force | Out-Null

$releasesByLine = [ordered]@{}
foreach ($line in $releaseSources.Keys) {
    $source = $releaseSources[$line]
    $releases = @(Get-PrestaShopReleases -Repository $source.Repository -Pattern $source.Pattern)
    if ($releases.Count -eq 0) {
        throw "No stable PrestaShop $line releases were found in the official GitHub tags."
    }
    $releasesByLine[$line] = $releases
}

$allReleases = @($releasesByLine.Values | ForEach-Object { $_ })
$dockerTags = Get-DockerTags -Prefixes (Get-DockerTagPrefixes -Releases $allReleases)
if ($dockerTags.Count -eq 0) {
    throw "No PrestaShop Docker image tags were found in the official Docker Hub repository."
}

foreach ($line in $releaseSources.Keys) {
    $source = $releaseSources[$line]
    $releases = @($releasesByLine[$line])
    $baseImage = Get-SourceBaseImage -Line $line -Releases $releases -DockerTags $dockerTags
    $matrixEntries = [System.Collections.Generic.List[object]]::new()

    foreach ($release in $releases) {
        $exactImage = Get-ExplicitPhpDockerTag -Version $release -DockerTags $dockerTags
        if ($exactImage) {
            $matrixEntries.Add((New-MatrixEntry `
                -Version $release `
                -Tag $exactImage.Tag `
                -Php $exactImage.Php `
                -MySql (Get-MySqlVersion $release) `
                -Kind "release" `
                -BuildSource $false `
                -BaseImage "" `
                -Repository $source.Repository `
                -LegacyMySql ($line -eq "1.6.x")))
        }
        else {
            $matrixEntries.Add((New-MatrixEntry `
                -Version $release `
                -Tag $release `
                -Php $source.Php `
                -MySql (Get-MySqlVersion $release) `
                -Kind "release" `
                -BuildSource $true `
                -BaseImage $baseImage `
                -Repository $source.Repository `
                -LegacyMySql ($line -eq "1.6.x")))
        }
    }

    if ($line -eq "1.6.x") {
        $latestPrestaShop161 = @(
            $releases |
            Where-Object { $_ -match '^1\.6\.1\.\d+$' } |
            Sort-Object { [version]$_ } -Descending
        )[0]
        if (-not $latestPrestaShop161) {
            throw "No stable PrestaShop 1.6.1 release was found for PHP boundary coverage."
        }

        foreach ($boundary in @(
            [pscustomobject]@{ Php = "5.5"; Image = "1.6-5.5-apache"; Kind = "php-lower-boundary" }
            [pscustomobject]@{ Php = "7.1"; Image = "1.6-7.1-apache"; Kind = "php-upper-boundary" }
        )) {
            if (-not $dockerTags.Contains($boundary.Image)) {
                $boundaryTags = Get-DockerTags -Prefixes @($boundary.Image)
                foreach ($tag in $boundaryTags) {
                    [void]$dockerTags.Add($tag)
                }
            }
            if (-not $dockerTags.Contains($boundary.Image)) {
                throw "The official PrestaShop PHP boundary image $($boundary.Image) was not found."
            }
            $matrixEntries.Add((New-MatrixEntry `
                -Version $latestPrestaShop161 `
                -Tag "$latestPrestaShop161-php-$($boundary.Php)-boundary" `
                -Php $boundary.Php `
                -MySql "5.7" `
                -Kind $boundary.Kind `
                -BuildSource $true `
                -BaseImage $boundary.Image `
                -Repository $source.Repository `
                -LegacyMySql $true))
        }
    }

    if ($matrixEntries.Count -gt 256) {
        throw "PrestaShop $line requires $($matrixEntries.Count) matrix jobs, exceeding GitHub Actions' 256-job matrix limit. Split the line into bounded workflow shards before generation."
    }

    $workflowContent = New-WorkflowContent `
        -Line $line `
        -Package $source.Package `
        -Matrix (ConvertTo-MatrixYaml -Entries $matrixEntries.ToArray()) `
        -CheckPhp54 ($line -eq "1.6.x")
    $workflowPath = Join-Path $outputPath "prestashop-$line.yml"
    [System.IO.File]::WriteAllText($workflowPath, $workflowContent, [System.Text.UTF8Encoding]::new($false))
    Write-Output "Generated PrestaShop $line workflow with $($releases.Count) stable releases and $($matrixEntries.Count - $releases.Count) compatibility boundary tests."
}
