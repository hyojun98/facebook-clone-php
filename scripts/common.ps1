# 공통 경로 / 헬퍼 (다른 스크립트에서 dot-source 해서 사용)
$ErrorActionPreference = 'Stop'
# VS Code 터미널 등에서 한글이 깨지지 않도록
try { [Console]::OutputEncoding = [Text.Encoding]::UTF8 } catch { }
$ProgressPreference = 'SilentlyContinue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$Root           = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$ProjectRuntime = Join-Path $Root '.runtime'   # 실제 파일이 저장되는 위치 (항상 프로젝트 안)
$Runtime        = $ProjectRuntime              # 프로그램을 실행할 때 쓰는 경로

# ---------------------------------------------------------------------------
# 프로젝트 경로에 한글 등 비 ASCII 문자가 있을 때
#   MySQL 서버는 설치/데이터 경로에 비 ASCII 문자가 있으면 시작하지 못하고(자기 경로를 'D:\workspace\' 처럼
#   잘라서 인식), PHP 도 php.ini 의 비 ASCII extension_dir 에서 확장 DLL 을 불러오지 못한다.
#   그래서 영문 경로(C:\ProgramData\fb-clone\rt-<해시>)에 .runtime 을 가리키는 정션(폴더 바로가기)을 만들고
#   모든 프로그램을 그 경로로 실행한다. 파일은 그대로 프로젝트의 .runtime 에 저장된다. (관리자 권한 불필요)
# ---------------------------------------------------------------------------
function Test-Ascii([string]$s) { return $s -match '^[\x20-\x7E]*$' }

if (-not (Test-Ascii $Root)) {
    $base = $env:ProgramData
    if (-not $base -or -not (Test-Ascii $base)) { $base = [IO.Path]::GetPathRoot($Root) }
    $sha = [Security.Cryptography.SHA1]::Create()
    $hash = (($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($Root.ToLowerInvariant())) | Select-Object -First 4) | ForEach-Object { $_.ToString('x2') }) -join ''
    $Runtime = Join-Path $base "fb-clone\rt-$hash"

    New-Item -ItemType Directory -Force $ProjectRuntime | Out-Null
    $link = Get-Item -LiteralPath $Runtime -Force -ErrorAction SilentlyContinue
    if ($link -and $link.LinkType -ne 'Junction') {
        throw "정션을 만들 위치에 다른 폴더가 있습니다: $Runtime"
    }
    if ($link -and (@($link.Target)[0] -ne $ProjectRuntime)) {
        # 같은 해시를 쓰는 예전 정션이 다른 곳을 가리키면 다시 만든다 (폴더 이동 등)
        cmd /c "rmdir `"$Runtime`"" | Out-Null
        $link = $null
    }
    if (-not $link) {
        New-Item -ItemType Directory -Force (Split-Path $Runtime) | Out-Null
        New-Item -ItemType Junction -Path $Runtime -Target $ProjectRuntime | Out-Null
    }
}

$PhpDir    = Join-Path $Runtime 'php'
$PhpExe    = Join-Path $PhpDir 'php.exe'
$MySqlDir  = Join-Path $Runtime 'mysql'
$Mysqld    = Join-Path $MySqlDir 'bin\mysqld.exe'
$MysqlCli  = Join-Path $MySqlDir 'bin\mysql.exe'
$MysqlAdm  = Join-Path $MySqlDir 'bin\mysqladmin.exe'
$DataDir   = Join-Path $Runtime 'data'
$MyIni     = Join-Path $Runtime 'my.ini'
$LogDir    = Join-Path $Runtime 'logs'
$TmpDir    = Join-Path $Runtime 'tmp'
$DbName    = 'fb_clone'

function Write-Step([string]$msg) { Write-Host "==> $msg" -ForegroundColor Cyan }
function Write-Ok([string]$msg)   { Write-Host "    $msg" -ForegroundColor Green }
function Write-Warn2([string]$msg) { Write-Host "    $msg" -ForegroundColor Yellow }

function To-SlashPath([string]$p) { return $p -replace '\\', '/' }

function Test-Port([int]$port) {
    $client = New-Object Net.Sockets.TcpClient
    try { $client.Connect('127.0.0.1', $port); return $true } catch { return $false } finally { $client.Close() }
}

function Find-Chrome {
    $candidates = @(
        (Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\chrome.exe' -ErrorAction SilentlyContinue).'(default)',
        (Get-ItemProperty 'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\chrome.exe' -ErrorAction SilentlyContinue).'(default)',
        "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
        "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
        "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
    )
    return $candidates | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
}

# Chrome 이 있으면 Chrome 으로, 없으면 기본 브라우저로 연다
function Open-Browser([string]$url) {
    $chrome = Find-Chrome
    if ($chrome) { Start-Process -FilePath $chrome -ArgumentList $url } else { Start-Process $url }
}

# VS Code 디버거가 붙을 수 있도록 원격 디버깅 포트를 연 Chrome 을 별도 프로필로 실행
function Open-DebugChrome([string]$url, [int]$port) {
    $chrome = Find-Chrome
    if (-not $chrome) { throw 'Chrome 을 찾을 수 없습니다. Chrome 을 설치하거나 Edge 설정으로 실행하세요.' }
    $profileDir = Join-Path $Runtime 'chrome-debug-profile'
    New-Item -ItemType Directory -Force $profileDir | Out-Null
    Start-Process -FilePath $chrome -ArgumentList @(
        "--remote-debugging-port=$port",
        "--user-data-dir=`"$profileDir`"",
        '--no-first-run',
        '--no-default-browser-check',
        '--new-window',
        '--start-maximized',
        $url
    ) | Out-Null
    Write-Ok "디버그용 Chrome 실행 (포트 $port)"
}

function Get-MySqlPort {
    if (Test-Path $MyIni) {
        $m = Select-String -Path $MyIni -Pattern '^\s*port\s*=\s*(\d+)' | Select-Object -First 1
        if ($m) { return [int]$m.Matches[0].Groups[1].Value }
    }
    return 3306
}

function Test-Installed {
    return (Test-Path $PhpExe) -and (Test-Path $Mysqld) -and (Test-Path (Join-Path $DataDir 'mysql'))
}

# PowerShell 5.1 에서는 네이티브 프로그램의 stderr 출력이 Stop 모드에서 예외가 되므로
# 네이티브 호출 함수 안에서는 Continue 로 두고 종료 코드로 판단한다.

function Invoke-Download([string]$url, [string]$out) {
    $ErrorActionPreference = 'Continue'
    $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
    if ($curl) {
        & $curl.Source -L --fail --retry 3 -o $out $url
        if ($LASTEXITCODE -ne 0) { throw "다운로드 실패: $url" }
    } else {
        Invoke-WebRequest $url -OutFile $out -UseBasicParsing
    }
}

function Invoke-MySql([string[]]$arguments) {
    $ErrorActionPreference = 'Continue'
    $port = Get-MySqlPort
    & $MysqlCli -u root -h 127.0.0.1 -P $port --default-character-set=utf8mb4 @arguments
    if ($LASTEXITCODE -ne 0) { throw "mysql 명령 실패 (exit $LASTEXITCODE)" }
}

# SQL 파일 실행
# mysql 의 "source 경로" 명령은 한글 등 비 ASCII 경로의 파일을 열지 못하므로,
# 파일은 cmd 의 입력 리다이렉션(<)으로 Windows 가 직접 열어서 mysql 표준 입력으로 넘긴다.
# (PowerShell 파이프로 넘기면 PS 5.1 이 한글 내용을 ASCII 로 바꿔 깨뜨리므로 쓰지 않는다)
function Invoke-MySqlFile([string]$sqlPath) {
    $ErrorActionPreference = 'Continue'
    $port = Get-MySqlPort
    cmd /c "`"$MysqlCli`" -u root -h 127.0.0.1 -P $port --default-character-set=utf8mb4 < `"$sqlPath`""
    if ($LASTEXITCODE -ne 0) { throw "SQL 파일 실행 실패 (exit $LASTEXITCODE): $sqlPath" }
}

function Start-MySql {
    $port = Get-MySqlPort
    if (Test-Port $port) {
        Write-Ok "MySQL 이미 실행 중 (포트 $port)"
        return
    }
    New-Item -ItemType Directory -Force $LogDir | Out-Null
    Start-Process -FilePath $Mysqld -ArgumentList "--defaults-file=`"$MyIni`"" -WindowStyle Hidden | Out-Null
    for ($i = 0; $i -lt 60; $i++) {
        if (Test-Port $port) { Write-Ok "MySQL 시작됨 (127.0.0.1:$port)"; return }
        Start-Sleep -Milliseconds 500
    }
    throw "MySQL 이 시작되지 않았습니다. 로그를 확인하세요: $LogDir\mysql.err"
}

function Stop-MySql {
    $port = Get-MySqlPort
    if (-not (Test-Port $port)) { return }
    $ErrorActionPreference = 'Continue'
    & $MysqlAdm -u root -h 127.0.0.1 -P $port shutdown 2>$null
    for ($i = 0; $i -lt 40; $i++) {
        if (-not (Test-Port $port)) { Write-Ok 'MySQL 종료됨'; return }
        Start-Sleep -Milliseconds 500
    }
    Get-Process mysqld -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $Mysqld } | Stop-Process -Force
    Write-Ok 'MySQL 강제 종료됨'
}

# ---------------------------------------------------------------- HeidiSQL (포터블)
$HeidiDir      = Join-Path $Runtime 'heidisql'
$HeidiExe      = Join-Path $HeidiDir 'heidisql.exe'
$HeidiSettings = Join-Path $HeidiDir 'portable_settings.txt'
$HeidiSession  = 'fb_clone (local)'

function Install-HeidiSql([switch]$Force) {
    if ((Test-Path $HeidiExe) -and -not $Force) { Write-Ok 'HeidiSQL 이미 설치됨'; return }
    Write-Step 'HeidiSQL 최신 버전 확인'
    $rel = Invoke-RestMethod 'https://api.github.com/repos/HeidiSQL/HeidiSQL/releases/latest' -Headers @{ 'User-Agent' = 'fb-clone-setup' }
    $asset = $rel.assets | Where-Object { $_.name -match '^HeidiSQL_.*_64_Portable\.zip$' } | Select-Object -First 1
    if (-not $asset) { throw 'HeidiSQL 포터블 zip 을 찾지 못했습니다.' }
    $dl = Join-Path $Runtime 'heidisql-dl'
    New-Item -ItemType Directory -Force $dl | Out-Null
    $zip = Join-Path $dl $asset.name
    Write-Step "HeidiSQL 다운로드: $($asset.name)"
    Invoke-Download $asset.browser_download_url $zip
    $backup = $null
    if (Test-Path $HeidiSettings) { $backup = [IO.File]::ReadAllText($HeidiSettings) }
    if (Test-Path $HeidiDir) { Remove-Item -Recurse -Force $HeidiDir }
    Expand-Archive $zip $HeidiDir -Force
    if ($backup) { [IO.File]::WriteAllText($HeidiSettings, $backup, (New-Object Text.UTF8Encoding $false)) }
    Remove-Item -Recurse -Force $dl
    Write-Ok "HeidiSQL $($rel.tag_name) 설치됨"
}

# portable_settings.txt 에 접속 세션 저장 (기존 UI 설정은 유지)
function Set-HeidiSession {
    $D = '<|||>'
    $prefix = "Servers\$HeidiSession\"
    $keep = @()
    if (Test-Path $HeidiSettings) {
        $keep = [IO.File]::ReadAllLines($HeidiSettings) | Where-Object {
            $_ -and -not $_.StartsWith($prefix) -and $_ -notmatch '^(LastSessions|LastActiveSession)<'
        }
    }
    $port = Get-MySqlPort
    $session = @(
        "${prefix}Host${D}1${D}127.0.0.1",
        "${prefix}User${D}1${D}root",
        "${prefix}Password${D}1${D}",
        "${prefix}Port${D}1${D}$port",
        "${prefix}NetType${D}3${D}0",
        "${prefix}Library${D}1${D}libmysql-8.4.0.dll",
        "${prefix}Databases${D}1${D}$DbName",
        "${prefix}WindowsAuth${D}3${D}0",
        "${prefix}LoginPrompt${D}3${D}0",
        "${prefix}Compressed${D}3${D}0",
        "${prefix}Comment${D}1${D}Facebook clone local DB",
        "${prefix}SessionCreated${D}1${D}$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')",
        "LastSessions${D}1${D}$HeidiSession",
        "LastActiveSession${D}1${D}$HeidiSession"
    )
    [IO.File]::WriteAllLines($HeidiSettings, [string[]]($session + $keep), (New-Object Text.UTF8Encoding $false))
    Write-Ok "HeidiSQL 세션 '$HeidiSession' 설정 (127.0.0.1:$port / root / $DbName)"
}

function Import-Database([switch]$WithSeed) {
    Write-Step 'DB 스키마 생성 (database/schema.sql)'
    Invoke-MySqlFile (Join-Path $Root 'database\schema.sql')
    if ($WithSeed) {
        Write-Step '데모 데이터 입력 (database/seed.sql)'
        Invoke-MySqlFile (Join-Path $Root 'database\seed.sql')
    }
    Write-Ok "DB '$DbName' 준비 완료"
}
