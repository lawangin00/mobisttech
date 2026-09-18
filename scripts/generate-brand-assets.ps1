param(
    [ValidateSet('backend','website','both')]
    [string]$Target = 'both'
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$repo = Split-Path -Parent $PSScriptRoot
$brand = Join-Path $repo 'brand'
$logoSvg = Join-Path $brand 'Logo\Logo_mobiST_Master_Editable.svg'
$logoMaster = Join-Path $brand 'Logo\Logo_mobiST_Master.png'
$logoSquare = Join-Path $brand 'Logo\Logo_mobiST_Square.png'
$wordmarkSvg = Join-Path $brand 'Wordmark\Wordmark_mobiST_Editable.svg'
$wordmarkMaster = Join-Path $brand 'Wordmark\Wordmark_mobiST_Master.png'

foreach ($required in @($logoSvg, $logoMaster, $logoSquare, $wordmarkSvg, $wordmarkMaster)) {
    if (-not (Test-Path $required -PathType Leaf)) {
        throw "Required canonical brand master is missing: $required"
    }
}

function Resize-Png([string]$Source, [string]$Destination, [int]$MaxWidth, [int]$MaxHeight) {
    $image = [System.Drawing.Image]::FromFile($Source)
    try {
        $scale = [Math]::Min($MaxWidth / $image.Width, $MaxHeight / $image.Height)
        $width = [Math]::Max(1, [int][Math]::Round($image.Width * $scale))
        $height = [Math]::Max(1, [int][Math]::Round($image.Height * $scale))
        $bitmap = New-Object System.Drawing.Bitmap($width, $height, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
        try {
            $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
            try {
                $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
                $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
                $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
                $graphics.DrawImage($image, 0, 0, $width, $height)
            } finally { $graphics.Dispose() }
            $bitmap.Save($Destination, [System.Drawing.Imaging.ImageFormat]::Png)
        } finally { $bitmap.Dispose() }
    } finally { $image.Dispose() }
}

function New-Favicon([string]$Source, [string]$Destination) {
    $sizes = @(16, 32, 48, 64, 128, 256)
    $images = @()
    foreach ($size in $sizes) {
        $temporary = [System.IO.Path]::ChangeExtension([System.IO.Path]::GetTempFileName(), '.png')
        Resize-Png $Source $temporary $size $size
        $images += ,([System.IO.File]::ReadAllBytes($temporary))
        Remove-Item $temporary -Force
    }

    $stream = [System.IO.File]::Create($Destination)
    $writer = New-Object System.IO.BinaryWriter($stream)
    try {
        $writer.Write([UInt16]0)
        $writer.Write([UInt16]1)
        $writer.Write([UInt16]$images.Count)
        $offset = 6 + (16 * $images.Count)
        for ($index = 0; $index -lt $images.Count; $index++) {
            $dimension = if ($sizes[$index] -ge 256) { 0 } else { $sizes[$index] }
            $writer.Write([byte]$dimension)
            $writer.Write([byte]$dimension)
            $writer.Write([byte]0)
            $writer.Write([byte]0)
            $writer.Write([UInt16]1)
            $writer.Write([UInt16]32)
            $writer.Write([UInt32]$images[$index].Length)
            $writer.Write([UInt32]$offset)
            $offset += $images[$index].Length
        }
        foreach ($image in $images) { $writer.Write($image) }
    } finally {
        $writer.Dispose()
        $stream.Dispose()
    }
}

function Generate-Target([string]$Root) {
    $public = Join-Path $Root 'public'
    $out = Join-Path $public 'brand'
    New-Item -ItemType Directory -Force $out | Out-Null

    Copy-Item $wordmarkSvg (Join-Path $out 'mobist-wordmark.svg') -Force
    Copy-Item $logoSvg (Join-Path $out 'mobist-mark.svg') -Force
    Resize-Png $wordmarkMaster (Join-Path $out 'mobist-wordmark-print.png') 1800 1100
    Resize-Png $logoMaster (Join-Path $out 'mobist-mark-watermark.png') 1200 800
    Resize-Png $logoSquare (Join-Path $out 'mobist-icon-512.png') 512 512
    Resize-Png $logoSquare (Join-Path $public 'apple-touch-icon.png') 180 180
    Resize-Png $logoSquare (Join-Path $public 'icon-192.png') 192 192
    Resize-Png $logoSquare (Join-Path $public 'icon-512.png') 512 512
    New-Favicon $logoSquare (Join-Path $public 'favicon.ico')
}

if ($Target -in @('backend','both')) {
    Generate-Target (Join-Path $repo 'backend')
    Copy-Item (Join-Path $brand 'design-tokens.css') (Join-Path $repo 'backend\resources\css\brand-tokens.css') -Force
}
if ($Target -in @('website','both')) {
    Generate-Target (Join-Path $repo 'website')
    Copy-Item (Join-Path $brand 'design-tokens.css') (Join-Path $repo 'website\src\app\brand-tokens.css') -Force
}

Write-Output "Generated $Target runtime brand derivatives from C:\mobisttech\brand."
