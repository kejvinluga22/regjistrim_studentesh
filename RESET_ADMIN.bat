@echo off
title Rivendos llogarine e administratorit - CIT
cd /d "%~dp0"
echo.
echo   ======================================================
echo     HARRUAT FJALEKALIMIN E ADMINISTRATORIT?
echo.
echo     Llogaria rivendoset ne:  perdoruesi admin, fjalekalimi admin
echo     Studentet, programet dhe fotot NUK preken.
echo     Pastaj ndryshojeni fjalekalimin te Admin - Llogaria.
echo   ======================================================
echo.
choice /c PJ /m "  Vazhdo? (P = Po, J = Jo)"
if errorlevel 2 exit /b
echo.
"C:\xampp\php\php.exe" "%~dp0reset_admin.php"
echo.
pause
