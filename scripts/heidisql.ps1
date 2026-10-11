<#
  HeidiSQL 로 로컬 DB(fb_clone) 열기
  - HeidiSQL 이 없으면 포터블 버전을 .runtime/heidisql 에 설치
  - MySQL 이 꺼져 있으면 시작 (종료는 stop.bat)
  사용법: heidisql.bat [-Reinstall]
#>
param([switch]$Reinstall)

. "$PSScriptRoot\common.ps1"

if (-not (Test-Installed)) {
    Write-Host '로컬 환경이 설치되어 있지 않습니다. 먼저 setup.bat 을 실행하세요.' -ForegroundColor Red
    exit 1
}

if ($Reinstall -or -not (Test-Path $HeidiExe)) {
    Install-HeidiSql -Force:$Reinstall
}

$running = Get-Process heidisql -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $HeidiExe }
if (-not $running) {
    Set-HeidiSession   # HeidiSQL 은 종료 시 설정을 다시 쓰므로 실행 중이 아닐 때만 갱신
}

Write-Step 'MySQL 확인'
Start-MySql

Write-Step "HeidiSQL 실행 (세션: $HeidiSession)"
Start-Process -FilePath $HeidiExe -ArgumentList "--description=`"$HeidiSession`""
Write-Ok 'MySQL 은 계속 실행됩니다. 다 쓰고 나면 stop.bat 으로 종료하세요.'
