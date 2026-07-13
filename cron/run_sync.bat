@echo off
REM Daily SSO user sync — run manually or via Windows Task Scheduler
setlocal

set "PROJECT_DIR=%~dp0.."
set "PHP_EXE=D:\www\php\php.exe"

if not exist "%PHP_EXE%" (
    echo ERROR: PHP not found at %PHP_EXE%
    echo Edit cron\run_sync.bat and set PHP_EXE to your php.exe path.
    exit /b 1
)

cd /d "%PROJECT_DIR%"
"%PHP_EXE%" "%PROJECT_DIR%\cron\run_sync.php"
exit /b %ERRORLEVEL%
