<#
  MySQL + PHP 내장 서버 실행
  사용법: start.bat [-Port 8000] [-NoBrowser] [-DebugChrome] [-KeepDb]
  -DebugChrome : VS Code 디버거가 연결할 수 있게 원격 디버깅 포트(9222)를 연 Chrome 실행
  Ctrl+C 로 종료하면 MySQL 도 함께 종료됩니다. (-KeepDb: MySQL 유지)
#>
param(
    [int]$Port = 8000,
    [switch]$NoBrowser,
    [switch]$DebugChrome,
    [int]$DebugPort = 9222,
    [switch]$KeepDb
)

. "$PSScriptRoot\common.ps1"

if (-not (Test-Installed)) {
    Write-Host '로컬 환경이 설치되어 있지 않습니다. 먼저 setup.bat 을 실행하세요.' -ForegroundColor Red
    exit 1
}
if (Test-Port $Port) {
    Write-Host "포트 $Port 가 이미 사용 중입니다. -Port 옵션으로 다른 포트를 지정하세요." -ForegroundColor Red
    exit 1
}

Write-Step 'MySQL 시작'
Start-MySql

$url = "http://localhost:$Port"
if ($DebugChrome) {
    Open-DebugChrome $url $DebugPort
} elseif (-not $NoBrowser) {
    Open-Browser $url
}

Write-Step "PHP 서버 시작: $url"
Write-Host '    종료하려면 Ctrl+C' -ForegroundColor DarkGray

Push-Location $Root
try {
    $ErrorActionPreference = 'Continue'   # 내장 서버 접속 로그(stderr)를 그대로 출력
    & $PhpExe -S "127.0.0.1:$Port" -t $Root (Join-Path $Root 'router.php')
} finally {
    Pop-Location
    if (-not $KeepDb) {
        Write-Step 'MySQL 종료'
        Stop-MySql
    }
}
