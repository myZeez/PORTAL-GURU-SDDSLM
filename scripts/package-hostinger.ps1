[CmdletBinding()]
param(
    [string]$ReleaseName = 'portal-guru-hostinger.zip'
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$outputRoot = Join-Path $projectRoot 'output'
$releasePath = Join-Path $outputRoot $ReleaseName

Push-Location $projectRoot

try {
    Write-Host 'Membangun aset frontend...'
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) {
        throw 'Build Vite gagal.'
    }

    if (-not (Test-Path (Join-Path $projectRoot 'vendor/autoload.php'))) {
        throw 'Folder vendor tidak ditemukan. Jalankan composer install terlebih dahulu.'
    }

    $deployItems = @(
        'app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'storage', 'vendor',
        'artisan', 'composer.json', 'composer.lock', '.htaccess', '.env.production.example'
    )

    $files = [System.Collections.Generic.List[System.IO.FileInfo]]::new()

    foreach ($item in $deployItems) {
        $source = Join-Path $projectRoot $item

        if (-not (Test-Path $source)) {
            throw "Berkas deployment tidak ditemukan: $item"
        }

        if (Test-Path $source -PathType Leaf) {
            $files.Add((Get-Item -LiteralPath $source))
            continue
        }

        Get-ChildItem -LiteralPath $source -File -Recurse | ForEach-Object {
            $relativePath = $_.FullName.Substring($projectRoot.Length + 1).Replace('\', '/')

            if ($relativePath -notlike 'storage/logs/*' -and $relativePath -notlike 'bootstrap/cache/*.php') {
                $files.Add($_)
            }
        }
    }

    if (Test-Path $releasePath) {
        Remove-Item -LiteralPath $releasePath -Force
    }

    Add-Type -AssemblyName System.IO.Compression
    $zipStream = [System.IO.File]::Open($releasePath, [System.IO.FileMode]::CreateNew)
    $archive = [System.IO.Compression.ZipArchive]::new($zipStream, [System.IO.Compression.ZipArchiveMode]::Create, $false)

    try {
        foreach ($file in $files) {
            $entryName = $file.FullName.Substring($projectRoot.Length + 1).Replace('\', '/')
            $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::NoCompression)
            $entryStream = $entry.Open()
            $sourceStream = [System.IO.File]::OpenRead($file.FullName)

            try {
                $sourceStream.CopyTo($entryStream)
            }
            finally {
                $sourceStream.Dispose()
                $entryStream.Dispose()
            }
        }
    }
    finally {
        $archive.Dispose()
        $zipStream.Dispose()
    }

    Write-Host "Paket siap di: $releasePath"
    Write-Host 'Paket tidak menyertakan .env, node_modules, data pengujian, atau log.'
}
finally {
    Pop-Location
}
