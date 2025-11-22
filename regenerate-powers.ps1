$root = "2. Mechanics/Classes"
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
  }
}

$entries = $entries | Sort-Object path, name
$json = $entries | ConvertTo-Json -Depth 3 -Compress
Set-Content -Path "powers-data.js" -Value "const EMBEDDED_POWERS = $json;" -Encoding UTF8 -NoNewline
Write-Host "Regenerated powers-data.js with $($entries.Count) entries."
