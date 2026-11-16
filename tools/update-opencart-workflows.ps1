param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot "..\.github\workflows")
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function ConvertTo-ComparableVersion {
    param([string]$Version)

    $parts = @($Version.Split('.') | ForEach-Object { [int]$_ })

    while ($parts.Count -lt 4) {
        $parts += 0
    }

    return [version]::new($parts[0], $parts[1], $parts[2], $parts[3])
}

function Get-OpenCartWorkflowKey {
    param([string]$Version)

    $parts = $Version.Split('.')
    if ($parts[0] -eq '2') {
        return "$($parts[0]).$($parts[1]).x"
    }

    return "$($parts[0]).x"
}

function Get-OpenCartPhpVersion {
    param([string]$Version)

    if ($Version -match '^2\.[0-3]\.' -or $Version -match '^3\.0\.0\.' -or $Version -match '^3\.0\.[1-2]\.\d+$' -or $Version -match '^3\.0\.3\.[0-3]$') {
        return '5.4'
    }

    if ($Version -match '^3\.0\.3\.4$') {
        return '7.1'
    }

    if ($Version -match '^3\.0\.(3\.[5-9]|4\.[0-1])\.') {
        return '7.3'
    }

    if ($Version -match '^3\.0\.5\.' -or $Version -match '^4\.1\.0\.4$') {
        return '8.1'
    }

    if ($Version -match '^4\.0\.' -or $Version -match '^4\.1\.0\.[0-3]$') {
        return '8.0'
    }

    try {
        $composer = Invoke-RestMethod -Uri "https://raw.githubusercontent.com/opencart/opencart/$Version/composer.json" -TimeoutSec 20
        $phpConstraint = [string]$composer.require.php
        $phpMatch = [regex]::Match($phpConstraint, '(\d+\.\d+)')

        if ($phpMatch.Success) {
            return $phpMatch.Groups[1].Value
        }
    }
    catch {
    }

    throw "Could not determine the PHP version for OpenCart $Version from release metadata."
}

function Get-OpenCartReleases {
    $releases = [System.Collections.Generic.List[string]]::new()
    $page = 1

    do {
        $pageReleases = Invoke-RestMethod `
            -Uri "https://api.github.com/repos/opencart/opencart/releases?per_page=100&page=$page" `
            -Headers @{ 'User-Agent' = 'appnuno-opencart-workflow-generator' } `
            -TimeoutSec 30

        foreach ($release in $pageReleases) {
            if (-not $release.draft -and -not $release.prerelease -and $release.tag_name -match '^\d+\.\d+\.\d+\.\d+$') {
                $releases.Add([string]$release.tag_name)
            }
        }

        $page++
    } while ($pageReleases.Count -eq 100 -and $page -le 10)

    return @($releases | Select-Object -Unique | Sort-Object { ConvertTo-ComparableVersion $_ })
}

$outputPath = [System.IO.Path]::GetFullPath($OutputDirectory)
New-Item -ItemType Directory -Path $outputPath -Force | Out-Null

$openCartReleases = @(Get-OpenCartReleases)
if ($openCartReleases.Count -eq 0) {
    throw "No stable OpenCart releases were found in the official GitHub release archive."
}

$generatedOpenCartFiles = [System.Collections.Generic.List[string]]::new()
$legacyOpenCartVersions = @{
    '2.0.x' = @('2.0.0.0', '2.0.1.0', '2.0.1.1', '2.0.2.0', '2.0.3.0', '2.0.3.1')
    '2.1.x' = @('2.1.0.0', '2.1.0.1', '2.1.0.2')
    '2.2.x' = @('2.2.0.0')
    '2.3.x' = @('2.3.0.0', '2.3.0.1', '2.3.0.2')
    '3.x' = @('3.0.0.0', '3.0.0.2', '3.0.1.1', '3.0.1.2', '3.0.2.0', '3.0.3.0', '3.0.3.1', '3.0.3.2', '3.0.3.3', '3.0.3.4', '3.0.3.5', '3.0.3.6', '3.0.3.7', '3.0.3.8', '3.0.3.9', '3.0.4.0', '3.0.4.1', '3.0.5.0', '3.0.5.1')
    '4.x' = @('4.0.0.0', '4.0.1.0', '4.0.1.1', '4.0.2.0', '4.0.2.1', '4.0.2.2', '4.0.2.3', '4.1.0.0', '4.1.0.1', '4.1.0.2', '4.1.0.3', '4.1.0.4')
}
$openCartTemplateDefinitions = @(
    @{ Key = '2.0.x'; Version = '2.0.3.1'; Php = '5.4'; Package = '2.0'; Extensions = 'curl, ftp, gd, mysqli, zip, mcrypt, mbstring, xml' }
    @{ Key = '2.1.x'; Version = '2.1.0.2'; Php = '5.4'; Package = '2.1'; Extensions = 'curl, ftp, gd, mysqli, zip, mcrypt, mbstring, xml' }
    @{ Key = '2.2.x'; Version = '2.2.0.0'; Php = '5.4'; Package = '2.2'; Extensions = 'curl, ftp, gd, mysqli, zip, mcrypt, mbstring, xml' }
    @{ Key = '2.3.x'; Version = '2.3.0.2'; Php = '5.4'; Package = '2.3'; Extensions = 'curl, ftp, gd, mysqli, zip, mcrypt, mbstring, xml' }
    @{ Key = '3.x'; Version = '3.0.5.1'; Php = '8.1'; Package = '3.0'; Extensions = 'curl, ftp, gd, mysqli, zip, mbstring, xml' }
    @{ Key = '4.x'; Version = '4.1.0.4'; Php = '8.1'; Package = '4'; Extensions = 'curl, gd, mysqli, zip, mbstring, xml' }
)

$openCartWorkflowTemplate = @'
name: OpenCart __KEY__ compatibility

on:
  push:
  pull_request:
  workflow_dispatch:

jobs:
  integration:
    runs-on: ubuntu-26.04
    strategy:
      fail-fast: false
      matrix:
        include:
          - opencart-version: '__VERSION__'
            php-version: '__PHP__'
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: opencart
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h 127.0.0.1 -proot"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=10
    steps:
      - uses: actions/checkout@v6

      - name: Check out storefront SDK source
        uses: actions/checkout@v6
        with:
          repository: mantax559/appnuno-platform
          ref: production
          path: appnuno-platform
          token: ${{ secrets.APPNUNO_PLATFORM_REPOSITORY_TOKEN }}

      - name: Set up PHP for the package build
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.3'
          extensions: zip, mbstring, xml
          coverage: none

      - name: Set up Python
        uses: actions/setup-python@v6
        with:
          python-version: '3.15'

      - name: Install browser test dependencies
        run: |
          python -m pip install -r tools/opencart/tests/requirements-ci.txt
          python -m playwright install --with-deps chromium

      - name: Build and validate extension package reproducibly
        env:
          TZ: UTC
        run: |
          php tools/opencart/build-package.php
          first_hash=$(sha256sum downloads/opencart/__KEY__/appnuno-opencart-__KEY__.ocmod.zip | cut -d ' ' -f 1)
          php tools/opencart/build-package.php
          second_hash=$(sha256sum downloads/opencart/__KEY__/appnuno-opencart-__KEY__.ocmod.zip | cut -d ' ' -f 1)
          test "$first_hash" = "$second_hash"
          php tools/opencart/tests/package-validation.php __PACKAGE__
          php tools/opencart/tests/config-validation.php
          php tools/opencart/tests/language-validation.php

      - name: Set up PHP ${{ matrix.php-version }} for the OpenCart store
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: __EXTENSIONS__
          coverage: none

      - name: Install OpenCart ${{ matrix.opencart-version }} and run browser integration test
        env:
          OPENCART_VERSION: ${{ matrix.opencart-version }}
          OPENCART_DB_HOST: 127.0.0.1
          OPENCART_DB_USER: root
          OPENCART_DB_PASSWORD: root
          OPENCART_DB_NAME: opencart
          OPENCART_ARTIFACT_DIR: ${{ runner.temp }}/opencart-artifacts/${{ matrix.opencart-version }}
          APPNUNO_SDK_SOURCE: ${{ github.workspace }}/appnuno-platform/public/sdk/v1/appnuno.js
        run: python tools/opencart/tests/integration.py

      - name: Upload browser test artifacts
        if: always()
        uses: actions/upload-artifact@v7
        with:
          name: opencart-${{ matrix.opencart-version }}-browser-artifacts
          path: ${{ runner.temp }}/opencart-artifacts/${{ matrix.opencart-version }}
          if-no-files-found: warn
          retention-days: 7
'@

$openCartTemplates = [System.Collections.Generic.List[System.IO.FileInfo]]::new()
foreach ($definition in $openCartTemplateDefinitions) {
    $templateContent = $openCartWorkflowTemplate.Replace('__KEY__', $definition.Key).
        Replace('__VERSION__', $definition.Version).
        Replace('__PHP__', $definition.Php).
        Replace('__PACKAGE__', $definition.Package).
        Replace('__EXTENSIONS__', $definition.Extensions)
    $templatePath = Join-Path $outputPath "opencart-$($definition.Key).yml"
    [System.IO.File]::WriteAllText($templatePath, $templateContent.TrimStart(), [System.Text.UTF8Encoding]::new($false))
    $openCartTemplates.Add((Get-Item $templatePath))
}

foreach ($template in $openCartTemplates) {
    $workflowKey = [System.IO.Path]::GetFileNameWithoutExtension($template.Name).Substring('opencart-'.Length)
    $templateLines = @([System.IO.File]::ReadAllLines($template.FullName) | Where-Object { $_ -notmatch '^\s*#' })
    $includeIndex = [Array]::FindIndex($templateLines, [Predicate[string]]{ param($line) $line -ceq '        include:' })
    $servicesIndex = [Array]::FindIndex($templateLines, [Predicate[string]]{ param($line) $line -ceq '    services:' })

    if ($servicesIndex -lt 0) {
        throw "Could not locate the service configuration in $($template.Name)."
    }

    if ($includeIndex -lt 0) {
        $matrixIndex = [Array]::FindIndex($templateLines, [Predicate[string]]{ param($line) $line -match '^        opencart-version:\s*\[' })

        if ($matrixIndex -lt 0 -or $servicesIndex -le $matrixIndex) {
            throw "Could not locate the OpenCart version list in $($template.Name)."
        }

        $versionsForWorkflow = [System.Collections.Generic.List[string]]::new()
        foreach ($release in $openCartReleases) {
            if ((Get-OpenCartWorkflowKey $release) -eq $workflowKey) {
                $versionsForWorkflow.Add($release)
            }
        }

        foreach ($versionMatch in [regex]::Matches($templateLines[$matrixIndex], "'([^']+)'")) {
            if ($versionMatch.Groups[1].Value -notin $versionsForWorkflow) {
                $versionsForWorkflow.Add($versionMatch.Groups[1].Value)
            }
        }

        if ($versionsForWorkflow.Count -eq 0) {
            continue
        }

        $sortedVersionsForWorkflow = @($versionsForWorkflow | Sort-Object { ConvertTo-ComparableVersion $_ })
        $quotedVersions = @($sortedVersionsForWorkflow | ForEach-Object { "'$_'" })
        $workflowLines = [System.Collections.Generic.List[string]]::new()
        $workflowLines.AddRange([string[]]$templateLines[0..($matrixIndex - 1)])
        $workflowLines.Add("        opencart-version: [$($quotedVersions -join ', ')]")
        $workflowLines.AddRange([string[]]$templateLines[$servicesIndex..($templateLines.Count - 1)])
        [System.IO.File]::WriteAllText($template.FullName, ($workflowLines -join [Environment]::NewLine) + [Environment]::NewLine, [System.Text.UTF8Encoding]::new($false))
        $generatedOpenCartFiles.Add($template.Name)
        continue
    }

    if ($servicesIndex -le $includeIndex) {
        throw "Could not locate the version matrix in $($template.Name)."
    }

    $versionsForWorkflow = [System.Collections.Generic.List[string]]::new()
    foreach ($release in $openCartReleases) {
        if ((Get-OpenCartWorkflowKey $release) -eq $workflowKey) {
            $versionsForWorkflow.Add($release)
        }
    }

    foreach ($version in $legacyOpenCartVersions[$workflowKey]) {
        if ($version -notin $versionsForWorkflow) {
            $versionsForWorkflow.Add($version)
        }
    }

    foreach ($line in $templateLines[$includeIndex..($servicesIndex - 1)]) {
        $versionMatch = [regex]::Match($line, '^\s*- opencart-version: ''([^'']+)''$')

        if ($versionMatch.Success -and $versionMatch.Groups[1].Value -notin $versionsForWorkflow) {
            $versionsForWorkflow.Add($versionMatch.Groups[1].Value)
        }
    }

    if ($versionsForWorkflow.Count -eq 0) {
        continue
    }

    $sortedVersionsForWorkflow = @($versionsForWorkflow | Sort-Object { ConvertTo-ComparableVersion $_ })
    $prefix = @($templateLines[0..$includeIndex])
    $suffix = @($templateLines[$servicesIndex..($templateLines.Count - 1)])
    $workflowLines = [System.Collections.Generic.List[string]]::new()
    $workflowLines.AddRange([string[]]$prefix)

    foreach ($version in $sortedVersionsForWorkflow) {
        $phpVersion = Get-OpenCartPhpVersion -Version $version
        $workflowLines.Add("          - opencart-version: '$version'")
        $workflowLines.Add("            php-version: '$phpVersion'")
    }

    $workflowLines.AddRange([string[]]$suffix)
    [System.IO.File]::WriteAllText($template.FullName, ($workflowLines -join [Environment]::NewLine) + [Environment]::NewLine, [System.Text.UTF8Encoding]::new($false))
    $generatedOpenCartFiles.Add($template.Name)
}

if ($generatedOpenCartFiles.Count -eq 0) {
    throw "No OpenCart workflows were generated."
}

Write-Output "Generated $($generatedOpenCartFiles.Count) OpenCart workflows from $($openCartReleases.Count) official releases."
