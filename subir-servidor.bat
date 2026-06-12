@echo off
REM ============================================================
REM  Degrade - sobe o servidor local
REM  Basta dar duplo-clique neste arquivo.
REM ============================================================
cd /d "%~dp0"
echo.
echo  Subindo o Degrade...
echo  Acesse no navegador: http://127.0.0.1:8000/login
echo  Login: owner@test.local  /  senha: password
echo.
echo  (Para PARAR o servidor: feche esta janela ou aperte Ctrl+C)
echo.
php artisan serve
echo.
echo  O servidor parou. Pressione uma tecla para fechar.
pause >nul
