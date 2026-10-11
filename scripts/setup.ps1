<#
  로컬 개발 환경 설치 (관리자 권한 불필요)
  - .runtime/php   : PHP 8.3 (NTS x64) 포터블
  - .runtime/mysql : MySQL 8.4 LTS 포터블
  - .runtime/data  : MySQL 데이터
  사용법: setup.bat  [-MySqlPort 3307] [-NoSeed] [-Force]
#>
param(
    [int]$MySqlPort = 3306,
    [string]$PhpVersion = '8.3',
    [string]$MySqlSeries = '8.4',
    [switch]$NoSeed,
    [switch]$NoHeidi,
    [switch]$Force
)

. "$PSScriptRoot\common.ps1"

New-Item -ItemType Directory -Force $Runtime, $LogDir, $TmpDir, (Join-Path $TmpDir 'sessions'), (Join-Path $TmpDir 'uploads') | Out-Null
$dl = Join-Path $Runtime 'downloads'
New-Item -ItemType Directory -Force $dl | Out-Null

# ---------------------------------------------------------------- PHP
if ($Force -or -not (Test-Path $PhpExe)) {
    Write-Step "PHP $PhpVersion 다운로드"
    $releases = Invoke-RestMethod 'https://windows.php.net/downloads/releases/releases.json'
    $rel = $releases.$PhpVersion
    if (-not $rel) { throw "PHP $PhpVersion 릴리스를 찾을 수 없습니다." }
    $build = $rel.PSObject.Properties | Where-Object { $_.Name -like 'nts-*-x64' } | Select-Object -First 1
    $zipName = $build.Value.zip.path
    $zipPath = Join-Path $dl $zipName
    if (-not (Test-Path $zipPath)) { Invoke-Download "https://windows.php.net/downloads/releases/$zipName" $zipPath }
    if (Test-Path $PhpDir) { Remove-Item -Recurse -Force $PhpDir }
    Expand-Archive $zipPath $PhpDir -Force
    Write-Ok "PHP $($rel.version) 설치됨"
} else {
    Write-Ok 'PHP 이미 설치됨 (재설치: -Force)'
}

Write-Step 'php.ini 작성'
$phpIni = @"
[PHP]
extension_dir = "$(To-SlashPath (Join-Path $PhpDir 'ext'))"
extension=pdo_mysql
extension=mysqli
extension=mbstring
extension=fileinfo
extension=openssl
extension=curl
extension=gd

display_errors = On
display_startup_errors = On
error_reporting = E_ALL
log_errors = On
error_log = "$(To-SlashPath (Join-Path $LogDir 'php-error.log'))"

file_uploads = On
upload_tmp_dir = "$(To-SlashPath (Join-Path $TmpDir 'uploads'))"
upload_max_filesize = 100M
post_max_size = 120M
max_file_uploads = 20
memory_limit = 256M
max_execution_time = 120
default_charset = "UTF-8"

[Date]
date.timezone = Asia/Seoul

[Session]
session.save_path = "$(To-SlashPath (Join-Path $TmpDir 'sessions'))"
"@
# 경로는 항상 영문($Runtime, 필요하면 정션 경로)이라 php.ini 내용은 ASCII 이다.
[IO.File]::WriteAllText((Join-Path $PhpDir 'php.ini'), $phpIni, (New-Object Text.UTF8Encoding $false))
$mods = & $PhpExe -m
foreach ($ext in 'pdo_mysql', 'mbstring', 'fileinfo') {
    if ($mods -notcontains $ext) { throw "PHP 확장 $ext 를 불러오지 못했습니다." }
}
Write-Ok 'pdo_mysql, mbstring, fileinfo 활성화 확인'

# ---------------------------------------------------------------- MySQL
if ($Force -or -not (Test-Path $Mysqld)) {
    Write-Step "MySQL $MySqlSeries 최신 버전 확인"
    $url = $null
    for ($n = 40; $n -ge 0; $n--) {
        $try = "https://cdn.mysql.com/Downloads/MySQL-$MySqlSeries/mysql-$MySqlSeries.$n-winx64.zip"
        try { Invoke-WebRequest $try -Method Head -UseBasicParsing | Out-Null; $url = $try; break } catch { }
    }
    if (-not $url) { throw "MySQL $MySqlSeries 다운로드 주소를 찾지 못했습니다." }
    $zipName = Split-Path $url -Leaf
    $zipPath = Join-Path $dl $zipName
    Write-Step "MySQL 다운로드: $zipName (약 280MB, 시간이 걸릴 수 있습니다)"
    if (-not (Test-Path $zipPath)) { Invoke-Download $url $zipPath }

    Write-Step 'MySQL 압축 해제'
    $extract = Join-Path $Runtime 'mysql-extract'
    if (Test-Path $extract) { Remove-Item -Recurse -Force $extract }
    Expand-Archive $zipPath $extract -Force
    if (Test-Path $MySqlDir) { Remove-Item -Recurse -Force $MySqlDir }
    $inner = Get-ChildItem $extract -Directory | Select-Object -First 1
    Move-Item $inner.FullName $MySqlDir
    Remove-Item -Recurse -Force $extract
    # 실행에 필요 없는 디버그 심볼 제거 (수백 MB 절약)
    Get-ChildItem $MySqlDir -Recurse -Filter *.pdb | Remove-Item -Force
    Write-Ok "MySQL 설치됨 ($MySqlDir)"
} else {
    Write-Ok 'MySQL 이미 설치됨 (재설치: -Force)'
}

Write-Step "my.ini 작성 (포트 $MySqlPort)"
$myIniText = @"
[mysqld]
basedir=$(To-SlashPath $MySqlDir)
datadir=$(To-SlashPath $DataDir)
port=$MySqlPort
bind-address=127.0.0.1
mysqlx=OFF
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
max_allowed_packet=64M
log-error=$(To-SlashPath (Join-Path $LogDir 'mysql.err'))

[client]
port=$MySqlPort
default-character-set=utf8mb4
"@
[IO.File]::WriteAllText($MyIni, $myIniText, (New-Object Text.UTF8Encoding $false))

if (-not (Test-Path (Join-Path $DataDir 'mysql'))) {
    Write-Step 'MySQL 데이터 디렉터리 초기화 (root 비밀번호 없음 - 로컬 전용)'
    if (Test-Path $DataDir) { Remove-Item -Recurse -Force $DataDir }
    $initLog = Join-Path $LogDir 'mysql-init.log'
    cmd /c "`"$Mysqld`" --defaults-file=`"$MyIni`" --initialize-insecure --console > `"$initLog`" 2>&1"
    if (-not (Test-Path (Join-Path $DataDir 'mysql'))) {
        throw "MySQL 초기화 실패. 로그 확인: $LogDir\mysql-init.log"
    }
    Write-Ok '초기화 완료'
}

# ---------------------------------------------------------------- DB
Write-Step 'MySQL 시작'
Start-MySql
try {
    Import-Database -WithSeed:(-not $NoSeed)
} finally {
    Stop-MySql
}

# ---------------------------------------------------------------- 앱 설정
Write-Step 'config.local.php 작성'
$local = @"
<?php
// scripts/setup.ps1 이 생성한 로컬 개발용 설정 (config.php 값을 덮어씀)
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => $MySqlPort,
        'name' => '$DbName',
        'user' => 'root',
        'pass' => '',
    ],
];
"@
[IO.File]::WriteAllText((Join-Path $Root 'config.local.php'), $local, (New-Object Text.UTF8Encoding $false))
Write-Ok 'config.local.php 생성'

# ---------------------------------------------------------------- HeidiSQL
if (-not $NoHeidi) {
    Install-HeidiSql -Force:$Force
    Set-HeidiSession
}

Remove-Item -Recurse -Force $dl -ErrorAction SilentlyContinue

Write-Host ''
Write-Host '설치가 완료되었습니다!' -ForegroundColor Green
Write-Host '  실행: start.bat   (http://localhost:8000)'
Write-Host '  종료: stop.bat'
if (-not $NoHeidi) { Write-Host '  DB 보기: heidisql.bat' }
if (-not $NoSeed) { Write-Host '  데모 계정: minjun@example.com / password' }
