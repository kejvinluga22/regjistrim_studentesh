# Builds the portable (USB) version: program + minimal PHP from XAMPP + START.bat, as a folder and a ZIP.
#   powershell -ExecutionPolicy Bypass -File portable\build_portable.ps1
#   (optional)  -PhpDir C:\xampp\php  -OutDir portable\build
param(
    [string]$PhpDir = 'C:\xampp\php',
    [string]$OutDir = ''
)
$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$root = Split-Path -Parent $here
if (-not $OutDir) { $OutDir = Join-Path $here 'build' }
$out = Join-Path $OutDir 'Regjistrimi_Studenteve'

if (-not (Test-Path "$PhpDir\php.exe")) { throw "PHP not found in $PhpDir (install XAMPP or pass -PhpDir)" }
if (Test-Path $out) { Remove-Item -Recurse -Force $out }
New-Item -ItemType Directory -Force "$out\app", "$out\php\ext" | Out-Null

# Program files (no student data: data\, students\, settings.json stay out)
Copy-Item "$root\*.php", "$root\.htaccess" "$out\app\"
Copy-Item -Recurse "$root\assets", "$root\templates", "$root\database" "$out\app\"

# Minimal PHP: php.exe + zip, mbstring, pdo_mysql extensions
Copy-Item "$PhpDir\php.exe", "$PhpDir\php8ts.dll" "$out\php\"
Copy-Item "$PhpDir\ext\php_zip.dll", "$PhpDir\ext\php_mbstring.dll", "$PhpDir\ext\php_pdo_mysql.dll" "$out\php\ext\"
# Visual C++ runtime, so it also runs on PCs without it
foreach ($dll in 'vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll') {
    if (Test-Path "$env:WINDIR\System32\$dll") { Copy-Item "$env:WINDIR\System32\$dll" "$out\php\" }
}
Copy-Item "$here\php.ini" "$out\php\php.ini"

# Launchers, instructions and the database file
Copy-Item "$here\START.bat", "$here\RESET_ADMIN.bat", "$here\LEXO-MUA.txt" "$out\"
Copy-Item "$root\database\regjistrimi_cit.sql" "$out\DATABAZA_regjistrimi_cit.sql"

$zip = Join-Path $OutDir 'Regjistrimi_Studenteve.zip'
Compress-Archive -Path $out -DestinationPath $zip -Force
"Built: $out"
"ZIP:   $zip  ({0:N1} MB)" -f ((Get-Item $zip).Length / 1MB)
