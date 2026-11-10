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

function ConvertTo-PlainText {
    param([string]$Value)

    return [System.Net.WebUtility]::HtmlDecode(($Value -replace '<[^>]+>', '').Trim())
}

function ConvertTo-BranchVersion {
    param([string]$Version)

    $parts = $Version.Split('.')
    return "$($parts[0]).$($parts[1])"
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
            -Headers @{ 'User-Agent' = 'appnuno-workflow-generator' } `
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

$wordpressArchiveUrl = "https://wordpress.org/download/releases/"
$woocommerceArchiveUrl = "https://developer.woocommerce.com/releases/"
$outputPath = [System.IO.Path]::GetFullPath($OutputDirectory)
$openCartReleases = @(Get-OpenCartReleases)

if ($openCartReleases.Count -eq 0) {
    throw "No stable OpenCart releases were found in the official GitHub release archive."
}

$openCartTemplates = @()

$wordpressHtml = (Invoke-WebRequest -UseBasicParsing -Uri $wordpressArchiveUrl -TimeoutSec 30).Content
$woocommerceHtml = (Invoke-WebRequest -UseBasicParsing -Uri $woocommerceArchiveUrl -TimeoutSec 30).Content
$latestWordPressByBranch = @{}
$branchPattern = '(?s)<div class="wp-block-wporg-release-tables__section" id="branch-(\d+)"[^>]*>(.*?)(?=<div class="wp-block-wporg-release-tables__section"|$)'

foreach ($branchMatch in [regex]::Matches($wordpressHtml, $branchPattern)) {
    $branchNumber = $branchMatch.Groups[1].Value

    if ($branchNumber.Length -lt 2) {
        continue
    }

    $branchVersion = "$($branchNumber.Substring(0, 1)).$($branchNumber.Substring(1))"
    $branchReleases = @(
        [regex]::Matches($branchMatch.Groups[2].Value, '(?s)<th[^>]*cell-version[^>]*>(.*?)</th>') |
            ForEach-Object { ConvertTo-PlainText $_.Groups[1].Value } |
            Where-Object { $_ -match '^\d+(?:\.\d+){1,2}$' } |
            Sort-Object { ConvertTo-ComparableVersion $_ } -Descending
    )

    if ($branchReleases.Count -gt 0) {
        $latestWordPressByBranch[$branchVersion] = $branchReleases[0]
    }
}

if ($latestWordPressByBranch.Count -eq 0) {
    throw "No WordPress releases were found in the official archive."
}

$woocommerceVersions = @(
    [regex]::Matches($woocommerceHtml, '(?s)<td>(?:<a[^>]*>)?(\d+(?:\.\d+){1,3})(?:</a>)?(?:\s*<strong>\(stable\)</strong>)?</td>') |
        ForEach-Object { $_.Groups[1].Value } |
        Select-Object -Unique |
        Sort-Object { ConvertTo-ComparableVersion $_ }
)

if ($woocommerceVersions.Count -eq 0) {
    throw "No stable WooCommerce releases were found in the official archive."
}

$fetchMetadata = {
    param([string[]]$Versions)

    foreach ($version in $Versions) {
        $readme = $null

        foreach ($url in @(
            ("https://plugins.svn.wordpress.org/woocommerce/tags/{0}/readme.txt" -f $version),
            ("https://raw.githubusercontent.com/woocommerce/woocommerce/{0}/readme.txt" -f $version)
        )) {
            try {
                $readme = (Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 30).Content
                break
            }
            catch {
                $readme = $null
            }
        }

        if ($null -eq $readme) {
            throw "Could not retrieve WooCommerce $version readme metadata."
        }

        $requiresMatch = [regex]::Match($readme, '(?im)^Requires at least:\s*(\d+(?:\.\d+){1,3})')
        $testedMatch = [regex]::Match($readme, '(?im)^Tested up to:\s*(\d+(?:\.\d+){1,3})')
        $phpMatch = [regex]::Match($readme, '(?im)^Requires PHP:\s*(\d+(?:\.\d+){1,3})')

        if (-not $requiresMatch.Success -or -not $testedMatch.Success) {
            throw "WooCommerce $version readme is missing compatibility metadata."
        }

        [pscustomobject]@{
            Version = $version
            RequiresAtLeast = $requiresMatch.Groups[1].Value
            TestedUpTo = $testedMatch.Groups[1].Value
            RequiresPhp = if ($phpMatch.Success) { $phpMatch.Groups[1].Value } else { "8.3" }
        }
    }
}

$metadataJobs = [System.Collections.Generic.List[System.Management.Automation.Job]]::new()
$batchSize = 20

try {
    for ($index = 0; $index -lt $woocommerceVersions.Count; $index += $batchSize) {
        $endIndex = [Math]::Min($index + $batchSize - 1, $woocommerceVersions.Count - 1)
        $batch = @($woocommerceVersions[$index..$endIndex])
        $metadataJobs.Add((Start-Job -ScriptBlock $fetchMetadata -ArgumentList (,$batch)))
    }

    $metadataJobs | Wait-Job | Out-Null
    $failedJobs = @($metadataJobs | Where-Object { $_.State -ne 'Completed' })

    if ($failedJobs.Count -gt 0) {
        $failureDetails = $failedJobs | ForEach-Object { Receive-Job -Job $_ 2>&1 | Out-String }
        throw "WooCommerce metadata retrieval failed: $($failureDetails -join [Environment]::NewLine)"
    }

    $woocommerceMetadata = @($metadataJobs | ForEach-Object { Receive-Job -Job $_ })
}
finally {
    $metadataJobs | Remove-Job -Force -ErrorAction SilentlyContinue
}

if ($woocommerceMetadata.Count -ne $woocommerceVersions.Count) {
    throw "Expected metadata for $($woocommerceVersions.Count) WooCommerce releases but received $($woocommerceMetadata.Count)."
}

$workflowEntriesByWooCommerceMajor = @{}

foreach ($release in $woocommerceMetadata) {
    $requiresBranch = ConvertTo-BranchVersion $release.RequiresAtLeast
    $testedBranch = ConvertTo-BranchVersion $release.TestedUpTo
    $woocommerceMajor = $release.Version.Split('.')[0]

    if (-not $latestWordPressByBranch.ContainsKey($requiresBranch)) {
        throw "WordPress $requiresBranch from WooCommerce $($release.Version) Requires at least metadata was not found in the official release archive."
    }

    if (-not $latestWordPressByBranch.ContainsKey($testedBranch)) {
        throw "WordPress $testedBranch from WooCommerce $($release.Version) Tested up to metadata was not found in the official release archive."
    }

    if (-not $workflowEntriesByWooCommerceMajor.ContainsKey($woocommerceMajor)) {
        $workflowEntriesByWooCommerceMajor[$woocommerceMajor] = [System.Collections.Generic.List[object]]::new()
    }

    $targets = @(
        [pscustomobject]@{
            Version = $latestWordPressByBranch[$requiresBranch]
            Target = "requires-at-least"
        }
    )

    if ($testedBranch -ne $requiresBranch) {
        $targets += [pscustomobject]@{
            Version = $latestWordPressByBranch[$testedBranch]
            Target = "tested-up-to"
        }
    }

    foreach ($target in $targets) {
        $workflowEntriesByWooCommerceMajor[$woocommerceMajor].Add([pscustomobject]@{
            WordPressVersion = $target.Version
            WooCommerceVersion = $release.Version
            PhpVersion = $release.RequiresPhp
            CompatibilityTarget = $target.Target
        })
    }
}

New-Item -ItemType Directory -Path $outputPath -Force | Out-Null
Get-ChildItem -Path $outputPath -Filter 'wordpress-woocommerce-*.yml' -File | Remove-Item -Force
$generatedWordPressFiles = [System.Collections.Generic.List[string]]::new()

foreach ($woocommerceMajor in $workflowEntriesByWooCommerceMajor.Keys | Sort-Object { [int]$_ }) {
    $fileName = "wordpress-woocommerce-$woocommerceMajor.x.yml"
    $generatedWordPressFiles.Add($fileName)
    $lines = [System.Collections.Generic.List[string]]::new()
    $lines.Add("name: WordPress and WooCommerce $woocommerceMajor.x compatibility")
    $lines.Add('')
    $lines.Add('on:')
    $lines.Add('  push:')
    $lines.Add('  pull_request:')
    $lines.Add('  workflow_dispatch:')
    $lines.Add('')
    $lines.Add('jobs:')
    $lines.Add('  install:')
    $lines.Add('    runs-on: ubuntu-26.04')
    $lines.Add('    strategy:')
    $lines.Add('      fail-fast: false')
    $lines.Add('      matrix:')
    $lines.Add('        include:')

    foreach ($entry in $workflowEntriesByWooCommerceMajor[$woocommerceMajor] | Sort-Object { ConvertTo-ComparableVersion $_.WooCommerceVersion }, { $_.CompatibilityTarget }) {
        $lines.Add("          - wordpress_version: `"$($entry.WordPressVersion)`"")
        $lines.Add("            woocommerce_version: `"$($entry.WooCommerceVersion)`"")
        $lines.Add("            php_version: `"$($entry.PhpVersion)`"")
        $lines.Add("            compatibility_target: `"$($entry.CompatibilityTarget)`"")
    }

    $lines.Add('    env:')
    $lines.Add('      WORDPRESS_VERSION: ${{ matrix.wordpress_version }}')
    $lines.Add('      WOOCOMMERCE_VERSION: ${{ matrix.woocommerce_version }}')
    $lines.Add('      PHP_VERSION: ${{ matrix.php_version }}')
    $lines.Add('')
    $lines.Add('    steps:')
    $lines.Add('      - uses: actions/checkout@v6')
    $lines.Add('')
    if ([int]$woocommerceMajor -eq 11) {
        $browserCheck = 'matrix.wordpress_version == ''7.1.4'' && matrix.woocommerce_version == ''11.2.1'' && matrix.compatibility_target == ''tested-up-to'''
        $lines.Add('      - name: Prepare PHP for the WooCommerce package build')
        $lines.Add('        uses: shivammathur/setup-php@v2')
        $lines.Add('        with:')
        $lines.Add('          php-version: "8.3"')
        $lines.Add('          extensions: zip')
        $lines.Add('          coverage: none')
        $lines.Add('')
        $lines.Add('      - name: Build the WooCommerce 11.x package')
        $lines.Add('        run: php tools/wordpress/build-package.php')
        $lines.Add('')
        $lines.Add('      - name: Install WordPress ${{ matrix.wordpress_version }} and WooCommerce ${{ matrix.woocommerce_version }} using PHP ${{ matrix.php_version }}')
        $lines.Add('        if: ${{ !(' + $browserCheck + ') }}')
        $lines.Add('        env:')
        $lines.Add('          APPNUNO_INSTALL_PLUGIN: "1"')
        $lines.Add('        run: docker compose -f tools/wordpress/tests/docker/docker-compose.yml up --build --abort-on-container-exit --exit-code-from installer installer')
        $lines.Add('')
        $lines.Add('      - name: Install WordPress 7.1.4 and WooCommerce 11.2.1 and keep the store running')
        $lines.Add('        if: ${{ ' + $browserCheck + ' }}')
        $lines.Add('        env:')
        $lines.Add('          APPNUNO_INSTALL_PLUGIN: "1"')
        $lines.Add('        run: docker compose -f tools/wordpress/tests/docker/docker-compose.yml -f tools/wordpress/tests/docker/docker-compose.live.yml up -d --build mysql wordpress && docker compose -f tools/wordpress/tests/docker/docker-compose.yml -f tools/wordpress/tests/docker/docker-compose.live.yml run --rm installer')
        $lines.Add('')
        $lines.Add('      - name: Check the AppNuno admin and storefront')
        $lines.Add('        if: ${{ ' + $browserCheck + ' }}')
        $lines.Add('        run: |')
        $lines.Add('          python3 -m pip install -r tools/wordpress/tests/requirements-ci.txt')
        $lines.Add('          python3 -m playwright install --with-deps chromium')
        $lines.Add('          python3 tools/wordpress/tests/integration/admin.py')
        $lines.Add('')
        $lines.Add('      - name: Remove the temporary WordPress environment')
        $lines.Add('        if: always()')
        $lines.Add('        run: docker compose -f tools/wordpress/tests/docker/docker-compose.yml -f tools/wordpress/tests/docker/docker-compose.live.yml down --volumes --remove-orphans')
    } else {
        $lines.Add('      - name: Install WordPress ${{ matrix.wordpress_version }} and WooCommerce ${{ matrix.woocommerce_version }} using PHP ${{ matrix.php_version }}')
        $lines.Add('        run: docker compose -f tools/wordpress/tests/docker/docker-compose.yml up --build --abort-on-container-exit --exit-code-from installer installer')
        $lines.Add('')
        $lines.Add('      - name: Remove the temporary WordPress environment')
        $lines.Add('        if: always()')
        $lines.Add('        run: docker compose -f tools/wordpress/tests/docker/docker-compose.yml down --volumes --remove-orphans')
    }

    [System.IO.File]::WriteAllText((Join-Path $outputPath $fileName), ($lines -join [Environment]::NewLine) + [Environment]::NewLine, [System.Text.UTF8Encoding]::new($false))
}

Get-ChildItem -Path $outputPath -Filter 'wordpress-woocommerce-*.x.yml' -File |
    Where-Object { $_.Name -notin $generatedWordPressFiles } |
    Remove-Item -Force

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
          python-version: '3.14'

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

        foreach ($versionMatch in [regex]::Matches($templateLines[$matrixIndex], "'([^']+)'") ) {
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

    $knownPhpVersions = @{}
    $knownVersions = [System.Collections.Generic.List[string]]::new()

    foreach ($lineIndex in 0..($templateLines.Count - 2)) {
        $versionMatch = [regex]::Match($templateLines[$lineIndex], '^\s*- opencart-version: ''([^'']+)''$')

        if ($versionMatch.Success) {
            $phpMatch = [regex]::Match($templateLines[$lineIndex + 1], '^\s*php-version: ''([^'']+)''$')

            if ($phpMatch.Success) {
                $knownPhpVersions[$versionMatch.Groups[1].Value] = $phpMatch.Groups[1].Value
                $knownVersions.Add($versionMatch.Groups[1].Value)
            }
        }
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

Write-Output "Generated $($generatedWordPressFiles.Count) WordPress workflows for $($woocommerceVersions.Count) WooCommerce releases and generated $($generatedOpenCartFiles.Count) OpenCart workflows from $($openCartReleases.Count) official releases."
