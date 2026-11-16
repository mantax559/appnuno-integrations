param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot "..\.github\workflows")
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Get-PrestaShop16Releases {
    $releases = [System.Collections.Generic.List[string]]::new()
    $pageNumber = 1

    do {
        $pageReleases = Invoke-RestMethod `
            -Uri "https://api.github.com/repos/PrestaShop/PrestaShop-1.6/tags?per_page=100&page=$pageNumber" `
            -Headers @{ 'User-Agent' = 'appnuno-prestashop-workflow-generator' } `
            -TimeoutSec 30

        foreach ($release in $pageReleases) {
            if ($release.name -match '^1\.6\.\d+\.\d+$') {
                $releases.Add([string]$release.name)
            }
        }

        $pageNumber++
    } while ($pageReleases.Count -eq 100)

    return @($releases.ToArray() | Sort-Object { [version]$_ } -Unique)
}

function Get-PrestaShopDockerTags {
    $tags = [System.Collections.Generic.List[string]]::new()
    $pageUrl = "https://hub.docker.com/v2/repositories/prestashop/prestashop/tags?page_size=100&name=1.6"

    while ($pageUrl) {
        $page = Invoke-RestMethod -Uri $pageUrl -TimeoutSec 30
        foreach ($tag in $page.results) {
            $tags.Add([string]$tag.name)
        }
        $pageUrl = [string]$page.next
    }

    return $tags
}

function Get-PrestaShopPhpVersion {
    param([string]$ImageTag)

    $versionMatch = [regex]::Match($ImageTag, '^1\.6-(\d+\.\d+)-apache$')
    if ($versionMatch.Success) {
        return $versionMatch.Groups[1].Value
    }

    return "5.6"
}

function Get-PrestaShopMySqlVersion {
    param([string]$Version)

    if ($Version.StartsWith("1.6.0.") -or $Version -eq "1.6.1.0") {
        return "5.6"
    }

    return "5.7"
}

$outputPath = [System.IO.Path]::GetFullPath($OutputDirectory)
New-Item -ItemType Directory -Path $outputPath -Force | Out-Null

$releases = @(Get-PrestaShop16Releases)
if ($releases.Count -eq 0) {
    throw "No stable PrestaShop 1.6 releases were found in the official GitHub tags."
}

$dockerTags = Get-PrestaShopDockerTags
if ($dockerTags.Count -eq 0) {
    throw "No PrestaShop 1.6 Docker image tags were found in the official Docker Hub repository."
}

$latestPrestaShop161 = @(
    $releases |
    Where-Object { $_ -match '^1\.6\.1\.\d+$' } |
        Sort-Object { [version]$_ } -Descending
)[0]

$matrixEntries = [System.Collections.Generic.List[object]]::new()
foreach ($release in $releases) {
    $imageTag = $release
    if (-not $dockerTags.Contains($imageTag)) {
        if ($release -eq $latestPrestaShop161 -and $dockerTags.Contains("1.6-5.6-apache")) {
            $imageTag = "1.6-5.6-apache"
        }
        else {
            throw "The official PrestaShop Docker image for release $release was not found."
        }
    }

    $matrixEntries.Add([pscustomobject]@{
        Version = $release
        Image = $imageTag
        Php = Get-PrestaShopPhpVersion $imageTag
        MySql = Get-PrestaShopMySqlVersion $release
        Kind = "release"
    })
}

foreach ($boundary in @(
    @{ Image = "1.6-5.5-apache"; Php = "5.5"; Kind = "php-lower-boundary" }
    @{ Image = "1.6-7.1-apache"; Php = "7.1"; Kind = "php-upper-boundary" }
)) {
    if (-not $dockerTags.Contains($boundary.Image)) {
        throw "The official PrestaShop PHP boundary image $($boundary.Image) was not found."
    }

    $matrixEntries.Add([pscustomobject]@{
        Version = $latestPrestaShop161
        Image = $boundary.Image
        Php = $boundary.Php
        MySql = "5.7"
        Kind = $boundary.Kind
    })
}

$matrixLines = [System.Collections.Generic.List[string]]::new()
foreach ($entry in $matrixEntries) {
    $matrixLines.Add("          - prestashop_version: `"$($entry.Version)`"")
    $matrixLines.Add("            prestashop_tag: `"$($entry.Image)`"")
    $matrixLines.Add("            php_version: `"$($entry.Php)`"")
    $matrixLines.Add("            mysql_tag: `"$($entry.MySql)`"")
    $matrixLines.Add("            test_kind: `"$($entry.Kind)`"")
}

$workflowTemplate = @'
name: PrestaShop 1.6.x compatibility

on:
  push:
  pull_request:
  workflow_dispatch:

jobs:
  installation:
    runs-on: ubuntu-26.04
    strategy:
      fail-fast: false
      matrix:
        include:
__MATRIX__
    env:
      PRESTASHOP_TAG: ${{ matrix.prestashop_tag }}
      PRESTASHOP_VERSION: ${{ matrix.prestashop_version }}
      EXPECTED_PHP_VERSION: ${{ matrix.php_version }}
      APPNUNO_PRESTASHOP_LINE: 1.6.x
      MYSQL_TAG: ${{ matrix.mysql_tag }}
    steps:
      - uses: actions/checkout@v6

      - name: Prepare PHP for the PrestaShop package build
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
          extensions: zip
          coverage: none

      - name: Build the PrestaShop 1.6.x package
        run: php tools/prestashop/build-package.php 16

      - name: Check the module PHP 5.4 syntax
        run: docker run --rm -v "$PWD:/repo:ro" --entrypoint sh cyberpoison/php:5.4-apache@sha256:d3bf257f2a482571b07fcc8b2fb7a48258b06456fad9f6a9a64cb6ee98866df5 -c 'find /repo/src/prestashop/appnuno /repo/src/shared/php/appnuno-config.php /repo/tools/prestashop/tests/docker -name "*.php" -print0 | xargs -0 -r -n1 php -l'

      - name: Start PrestaShop with MySQL
        run: docker compose -f tools/prestashop/tests/docker/docker-compose.yml -f tools/prestashop/tests/docker/docker-compose.mysql-legacy.yml up -d --wait mysql prestashop

      - name: Verify the PrestaShop PHP version
        run: |
          actual_php=$(docker compose -f tools/prestashop/tests/docker/docker-compose.yml -f tools/prestashop/tests/docker/docker-compose.mysql-legacy.yml exec -T prestashop php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
          test "$actual_php" = "$EXPECTED_PHP_VERSION"

      - name: Verify the PrestaShop MySQL installation
        run: docker compose -f tools/prestashop/tests/docker/docker-compose.yml -f tools/prestashop/tests/docker/docker-compose.mysql-legacy.yml run --rm installer-check

      - name: Install the AppNuno module
        run: |
          docker compose -f tools/prestashop/tests/docker/docker-compose.yml -f tools/prestashop/tests/docker/docker-compose.mysql-legacy.yml exec -T prestashop cp /scripts/prepare-module.php /tmp/appnuno-prepare-module.php
          docker compose -f tools/prestashop/tests/docker/docker-compose.yml -f tools/prestashop/tests/docker/docker-compose.mysql-legacy.yml exec -T prestashop php /tmp/appnuno-prepare-module.php

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
        run: docker compose -f tools/prestashop/tests/docker/docker-compose.yml -f tools/prestashop/tests/docker/docker-compose.mysql-legacy.yml down --volumes --remove-orphans
'@

$workflowContent = $workflowTemplate.Replace("__MATRIX__", ($matrixLines -join [Environment]::NewLine))
$workflowPath = Join-Path $outputPath "prestashop-1.6.x.yml"
[System.IO.File]::WriteAllText($workflowPath, $workflowContent, [System.Text.UTF8Encoding]::new($false))

Write-Output "Generated the PrestaShop 1.6.x workflow for $($releases.Count) stable releases and 2 PHP boundary tests."
