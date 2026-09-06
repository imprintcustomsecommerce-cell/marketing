@echo off
setlocal
title Imprint Hub - Local Public App

cd /d "%~dp0temp_app"

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0temp_app\scripts\start-imprint-hub.ps1"
set "RESULT=%ERRORLEVEL%"

if not "%RESULT%"=="0" (
    echo.
    echo Imprint Hub could not start. Review the error above.
    echo.
    pause
)

exit /b %RESULT%
