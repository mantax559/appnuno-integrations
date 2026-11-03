param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot "..\..\.github\workflows")
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
    param(
        [string]$Version,
        [hashtable]$KnownPhpVersions,
        [string[]]$KnownVersions
    )

    if ($KnownPhpVersions.ContainsKey($Version)) {
        return $KnownPhpVersions[$Version]
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

    $closestVersion = @(
        $KnownVersions |
            Where-Object { (ConvertTo-ComparableVersion $_) -le (ConvertTo-ComparableVersion $Version) } |
            Sort-Object { ConvertTo-ComparableVersion $_ } -Descending |
            Select-Object -First 1
    )

    if ($closestVersion.Count -gt 0 -and $KnownPhpVersions.ContainsKey($closestVersion[0])) {
        return $KnownPhpVersions[$closestVersion[0]]
    }

    throw "Could not determine the PHP version for OpenCart $Version from release metadata or existing workflow entries."
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
    $lines.Add('      - name: Install WordPress ${{ matrix.wordpress_version }} and WooCommerce ${{ matrix.woocommerce_version }} using PHP ${{ matrix.php_version }}')
    $lines.Add('        run: docker compose -f tools/wordpress/tests/docker/docker-compose.yml up --build --abort-on-container-exit --exit-code-from installer installer')
    $lines.Add('')
    $lines.Add('      - name: Remove the temporary WordPress environment')
    $lines.Add('        if: always()')
    $lines.Add('        run: docker compose -f tools/wordpress/tests/docker/docker-compose.yml down --volumes --remove-orphans')

    [System.IO.File]::WriteAllText((Join-Path $outputPath $fileName), ($lines -join [Environment]::NewLine) + [Environment]::NewLine, [System.Text.UTF8Encoding]::new($false))
}

Get-ChildItem -Path $outputPath -Filter 'wordpress-woocommerce-*.x.yml' -File |
    Where-Object { $_.Name -notin $generatedWordPressFiles } |
    Remove-Item -Force

$openCartReleases = @(Get-OpenCartReleases)
if ($openCartReleases.Count -eq 0) {
    throw "No stable OpenCart releases were found in the official GitHub release archive."
}

$generatedOpenCartFiles = [System.Collections.Generic.List[string]]::new()
$openCartTemplates = @(Get-ChildItem -Path $outputPath -Filter 'opencart-*.yml' -File)

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
        $phpVersion = Get-OpenCartPhpVersion -Version $version -KnownPhpVersions $knownPhpVersions -KnownVersions @($knownVersions)
        $workflowLines.Add("          - opencart-version: '$version'")
        $workflowLines.Add("            php-version: '$phpVersion'")
    }

    $workflowLines.AddRange([string[]]$suffix)
    [System.IO.File]::WriteAllText($template.FullName, ($workflowLines -join [Environment]::NewLine) + [Environment]::NewLine, [System.Text.UTF8Encoding]::new($false))
    $generatedOpenCartFiles.Add($template.Name)
}

if ($generatedOpenCartFiles.Count -eq 0) {
    throw "No OpenCart workflow templates were updated."
}

Write-Output "Generated $($generatedWordPressFiles.Count) WordPress workflows for $($woocommerceVersions.Count) WooCommerce releases and refreshed $($generatedOpenCartFiles.Count) OpenCart workflows from $($openCartReleases.Count) official releases."
