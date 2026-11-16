param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot "..\.github\workflows")
)

$ErrorActionPreference = "Stop"

& (Join-Path $PSScriptRoot "update-workflows.ps1") -OutputDirectory $OutputDirectory -Platform OpenCart
