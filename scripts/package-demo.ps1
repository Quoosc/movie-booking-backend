$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$snapshotPath = Join-Path $projectRoot '.demo-deploy\demo.sql'
if (-not (Test-Path -LiteralPath $snapshotPath)) {
    throw 'Run scripts/export-demo.php first.'
}
$archivePath = Join-Path $projectRoot '.demo-deploy\cineon-cloud-demo.zip'
if (Test-Path -LiteralPath $archivePath) {
    throw 'The bundle already exists; move it aside before rebuilding.'
}
Push-Location $projectRoot
try {
    $sourceFiles = @(git -c core.quotepath=false ls-files --cached --others --exclude-standard | Sort-Object -Unique)
    if ($LASTEXITCODE -ne 0) { throw 'Could not list repository files.' }
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [IO.Compression.ZipFile]::Open($archivePath, [IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($relativePath in $sourceFiles) {
            if ($relativePath -match '(^|/)\.env($|\.)' -and $relativePath -notmatch '\.example$') {
                continue
            }
            $absolutePath = [IO.Path]::GetFullPath((Join-Path $projectRoot $relativePath))
            if (-not $absolutePath.StartsWith($projectRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
                throw "Path outside project: $relativePath"
            }
            if (Test-Path -LiteralPath $absolutePath -PathType Leaf) {
                [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $absolutePath, $relativePath.Replace('\', '/')) | Out-Null
            }
        }
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $snapshotPath, 'demo-data.sql') | Out-Null
        $providers = Join-Path $projectRoot '.demo-deploy\provider.env'
        if (Test-Path -LiteralPath $providers) {
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $providers, 'demo-provider.env') | Out-Null
        }
        # Uploaded files are ignored by Git; include public uploads explicitly.
        $uploadsRoot = Join-Path $projectRoot 'storage\app\public'
        if (Test-Path -LiteralPath $uploadsRoot) {
            foreach ($upload in Get-ChildItem -LiteralPath $uploadsRoot -File -Recurse) {
                if ($upload.Name -eq '.gitignore') { continue }
                $entryName = 'demo-uploads/' + $upload.FullName.Substring($uploadsRoot.Length + 1).Replace('\', '/')
                [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $upload.FullName, $entryName) | Out-Null
            }
        }
    } finally {
        $archive.Dispose()
    }
    Write-Output "Private deployment bundle: $archivePath"
    Write-Output 'Contains the database snapshot; do not publish this ZIP to GitHub.'
} finally {
    Pop-Location
}
