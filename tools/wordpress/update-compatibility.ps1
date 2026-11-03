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

$wordpressArchiveUrl = "https://wordpress.org/download/releases/"
$woocommerceArchiveUrl = "https://developer.woocommerce.com/releases/"

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

$outputPath = [System.IO.Path]::GetFullPath($OutputDirectory)
New-Item -ItemType Directory -Path $outputPath -Force | Out-Null
$generatedFileNames = [System.Collections.Generic.List[string]]::new()

foreach ($woocommerceMajor in $workflowEntriesByWooCommerceMajor.Keys | Sort-Object { [int]$_ }) {
    $fileName = "wordpress-woocommerce-$woocommerceMajor.x.yml"
    $generatedFileNames.Add($fileName)
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
    $lines.Add('        run: docker compose -f tools/wordpress/tests/docker-compose.yml up --build --abort-on-container-exit --exit-code-from installer installer')
    $lines.Add('')
    $lines.Add('      - name: Remove the temporary WordPress environment')
    $lines.Add('        if: always()')
    $lines.Add('        run: docker compose -f tools/wordpress/tests/docker-compose.yml down --volumes --remove-orphans')

    [System.IO.File]::WriteAllText((Join-Path $outputPath $fileName), ($lines -join [Environment]::NewLine) + [Environment]::NewLine, [System.Text.UTF8Encoding]::new($false))
}

Get-ChildItem -Path $outputPath -Filter 'wordpress-woocommerce-*.x.yml' -File |
    Where-Object { $_.Name -notin $generatedFileNames } |
    Remove-Item -Force

Write-Output "Generated $($generatedFileNames.Count) GitHub Actions workflow files grouped by WooCommerce major release for $($woocommerceVersions.Count) WooCommerce releases in $outputPath."
