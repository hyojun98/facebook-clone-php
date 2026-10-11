<#
  DB 초기화: 모든 테이블을 다시 만들고 데모 데이터를 넣습니다. (업로드 파일도 삭제)
  사용법: reset-db.bat [-NoSeed] [-Yes]
#>
param([switch]$NoSeed, [switch]$Yes)

. "$PSScriptRoot\common.ps1"

if (-not (Test-Installed)) {
    Write-Host '로컬 환경이 설치되어 있지 않습니다. 먼저 setup.bat 을 실행하세요.' -ForegroundColor Red
    exit 1
}
if (-not $Yes) {
    $answer = Read-Host "DB '$DbName' 의 모든 데이터와 업로드 파일이 삭제됩니다. 계속할까요? (y/N)"
    if ($answer -ne 'y') { Write-Host '취소했습니다.'; exit 0 }
}

$wasRunning = Test-Port (Get-MySqlPort)
Start-MySql
try {
    Import-Database -WithSeed:(-not $NoSeed)
} finally {
    if (-not $wasRunning) { Stop-MySql }
}

Write-Step '업로드 파일 삭제'
Get-ChildItem (Join-Path $Root 'uploads') -Directory -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force
Write-Ok '완료'
