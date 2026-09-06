@echo off
setlocal

REM ---------------------------------------------------------------------------
REM Registers the Windows task that keeps Imprint Hub's backups running.
REM
REM The task runs Laravel's scheduler once a minute. What that actually does is
REM decided in temp_app\routes\console.php rather than buried in Task Scheduler:
REM today, an hourly backup that stops after the first success of the day, plus
REM the tunnel check.
REM
REM Runs as the logged-in user, needs no administrator rights, stores no
REM password. Remove it again with:
REM
REM     schtasks /delete /tn "Imprint Hub Scheduler" /f
REM
REM Registration goes through PowerShell rather than schtasks: schtasks cannot
REM set a working directory, and folding one in with "cd /d ... &&" makes it
REM reject the argument outright.
REM ---------------------------------------------------------------------------

set "TASK=Imprint Hub Scheduler"
set "APPDIR=%~dp0temp_app"

where php >nul 2>nul
if errorlevel 1 (
    echo.
    echo ERROR: PHP was not found in PATH.
    echo Start XAMPP or add the XAMPP PHP folder to PATH, then try again.
    echo.
    pause
    exit /b 1
)

for /f "delims=" %%P in ('where php') do (
    set "PHP=%%P"
    goto :found
)
:found

if not exist "%APPDIR%\artisan" (
    echo ERROR: Laravel was not found in "%APPDIR%".
    pause
    exit /b 1
)

echo Registering "%TASK%"
echo   PHP:     %PHP%
echo   Project: %APPDIR%
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$a = New-ScheduledTaskAction -Execute '%PHP%' -Argument 'artisan schedule:run' -WorkingDirectory '%APPDIR%';" ^
  "$t = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650);" ^
  "$s = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopIfGoingOnBatteries -AllowStartIfOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30);" ^
  "Register-ScheduledTask -TaskName '%TASK%' -Action $a -Trigger $t -Settings $s -Description 'Runs Laravel''s scheduler for Imprint Hub once a minute.' -Force | Out-Null"

if errorlevel 1 (
    echo.
    echo Could not register the task. Try running this file as administrator.
    echo.
    pause
    exit /b 1
)

echo Registered. Current state:
schtasks /query /tn "%TASK%"

echo.
echo Taking one backup now, so there is something on disk today...
pushd "%APPDIR%"
"%PHP%" artisan imprint:backup --force
popd

echo.
echo Backups are written to:
echo   %APPDIR%\storage\app\backups
echo.
echo These sit on the same disk as the database. Set NAS_BACKUP_PATH in
echo temp_app\.env to a second location and each archive is copied there too -
echo a backup on the failing disk is not a backup.
echo.
pause
