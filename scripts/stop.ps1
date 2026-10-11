<#
  실행 중인 PHP 내장 서버 / MySQL 종료
#>
. "$PSScriptRoot\common.ps1"

Write-Step 'PHP 서버 종료'
$php = Get-Process php -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $PhpExe }
if ($php) { $php | Stop-Process -Force; Write-Ok 'PHP 서버 종료됨' } else { Write-Ok '실행 중인 PHP 서버 없음' }

Write-Step '디버그용 Chrome 종료'
$debugChrome = Get-CimInstance Win32_Process -Filter "Name='chrome.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -match 'chrome-debug-profile' }
if ($debugChrome) {
    $debugChrome | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Write-Ok '디버그용 Chrome 종료됨'
} else { Write-Ok '실행 중인 디버그용 Chrome 없음' }

Write-Step 'MySQL 종료'
if (Test-Port (Get-MySqlPort)) { Stop-MySql } else { Write-Ok '실행 중인 MySQL 없음' }
