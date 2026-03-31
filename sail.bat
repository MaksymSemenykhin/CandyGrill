@echo off
setlocal
cd /d "%~dp0"

if not exist .env (
    if exist .env.example (
        echo candygrill: copy .env.example to .env
        exit /b 1
    )
)

set COMPOSE_PROJECT_NAME=candygrill

if /i "%~1"=="compose" (
    echo candygrill: use sail.bat up, sail.bat exec — do not prefix with 'compose'
    exit /b 1
)

if /i "%~1"=="composer" goto exec_app
if /i "%~1"=="php" goto exec_app
if /i "%~1"=="bash" goto exec_app
if /i "%~1"=="sh" goto exec_app
if /i "%~1"=="vendor\bin\phpunit" goto exec_app

docker compose %*
exit /b %ERRORLEVEL%

:exec_app
docker compose exec app %*
exit /b %ERRORLEVEL%
