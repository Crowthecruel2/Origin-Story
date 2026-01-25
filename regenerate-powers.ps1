$getDescription = {
  param([string]$Text)
  if (-not $Text) { return "" }
  $lines = $Text -split "`r?`n"

  # Prefer explicit Description heading or prefix
  $descIndex = -1
  for ($i = 0; $i -lt $lines.Length; $i++) {
    $trim = $lines[$i].Trim()
    if ($trim -match '^#{1,3}\s*description\b' -or $trim -match '^description\s*:') {
      $descIndex = $i
      break
    }
  }
  if ($descIndex -ge 0) {
    $chunk = @()
    for ($j = $descIndex + 1; $j -lt $lines.Length; $j++) {
      $line = $lines[$j]
      if ($line.Trim() -match '^#{1,6}\s+\S') { break }
      $chunk += $line
      if ([string]::IsNullOrWhiteSpace($line) -and $chunk.Count -gt 1) { break }
    }
    $desc = ($chunk -join "`n").Trim()
    if ($desc) { return $desc }
  }

  # Fallback: first non-heading, non-table paragraph
  $filtered = $lines | Where-Object { -not ($_.Trim().StartsWith("|")) }
  foreach ($line in $filtered) {
    $trim = $line.Trim()
    if (-not $trim) { continue }
    if ($trim -match '^#{1,6}\s+\S') { continue }
    if ($trim -match '^Lv\.?\s*\d+') { continue }
    if ($trim -match '^\* ' -or $trim -match '^- ') { continue }
    return $trim
  }
  return ""
}

$root = "vault/2. Mechanics/Classes"
$rootPath = (Resolve-Path $root).Path + [IO.Path]::DirectorySeparatorChar
$files = Get-ChildItem -Path $root -Recurse -Filter *.md
$entries = @()

foreach ($f in $files) {
  $parent = Split-Path $f.DirectoryName -Leaf
  $base = [IO.Path]::GetFileNameWithoutExtension($f.Name)
  # Skip index files where filename == parent folder
  if ($parent -and ($base -ieq $parent)) { continue }

  $content = [string](Get-Content -LiteralPath $f.FullName -Raw -ErrorAction SilentlyContinue)
  $rel = $f.FullName.Replace($rootPath, "").Replace("\", "/")
  $entries += [pscustomobject]@{
    name    = $base
    path    = $rel
    content = $content
    description = & $getDescription $content
  }
}

$entries = $entries | Sort-Object path, name
$json = $entries | ConvertTo-Json -Depth 3 -Compress
Set-Content -Path "powers-data.js" -Value "const EMBEDDED_POWERS = $json;" -Encoding UTF8 -NoNewline
Write-Host "Regenerated powers-data.js with $($entries.Count) entries."

