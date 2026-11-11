@echo off
REM Sobe o Cyberly Diet acessivel pela rede local (celular no mesmo Wi-Fi).
cd /d "%~dp0"

if exist public\hot del public\hot
echo Compilando assets...
call npm run build || goto :error

echo.
echo Enderecos para abrir no celular:
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do for /f "tokens=*" %%b in ("%%a") do echo    http://%%b:8000
echo.
php artisan serve --host=0.0.0.0 --port=8000
goto :eof

:error
echo Falha ao compilar os assets.
pause
