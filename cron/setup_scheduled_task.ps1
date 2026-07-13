# Register Windows Task Scheduler job: daily sync at 12:00 AM (midnight)
# Run PowerShell as Administrator:
#   Set-ExecutionPolicy -Scope Process Bypass
#   .\cron\setup_scheduled_task.ps1

$ErrorActionPreference = 'Stop'

$ProjectDir = Split-Path -Parent $PSScriptRoot
$PhpExe = 'D:\www\php\php.exe'
$ScriptPath = Join-Path $ProjectDir 'cron\run_sync.php'
$TaskName = 'UPNM_SSO_DailySync'

if (-not (Test-Path $PhpExe)) {
    Write-Host "ERROR: PHP not found at $PhpExe" -ForegroundColor Red
    Write-Host "Edit cron\setup_scheduled_task.ps1 and set `$PhpExe to your php.exe path."
    exit 1
}

if (-not (Test-Path $ScriptPath)) {
    Write-Host "ERROR: Script not found at $ScriptPath" -ForegroundColor Red
    exit 1
}

$Action = New-ScheduledTaskAction `
    -Execute $PhpExe `
    -Argument "`"$ScriptPath`"" `
    -WorkingDirectory $ProjectDir

# Daily at 12:00 AM (local time, Asia/Kuala_Lumpur if server TZ is set)
$Trigger = New-ScheduledTaskTrigger -Daily -At '12:00AM'

$Settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Hours 4)

# Run whether user is logged in or not (requires password for non-SYSTEM account)
# Using SYSTEM — ensure XAMPP/MySQL/ODBC accessible to SYSTEM or switch -UserId below
$Principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $Action `
    -Trigger $Trigger `
    -Settings $Settings `
    -Principal $Principal `
    -Force | Out-Null

Write-Host "Scheduled task registered: $TaskName" -ForegroundColor Green
Write-Host "  Runs daily at 12:00 AM"
Write-Host "  PHP:    $PhpExe"
Write-Host "  Script: $ScriptPath"
Write-Host "  Logs:   $(Join-Path $ProjectDir 'cron\logs\sync_cron.log')"
Write-Host ""
Write-Host "Test now:  D:\www\php\php.exe `"$ScriptPath`""
Write-Host "Or run:    schtasks /Run /TN `"$TaskName`""
