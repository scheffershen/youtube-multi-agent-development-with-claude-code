@echo off
rem Wrapper so every agent and human runs the same PHP with the same extensions.
rem Change PHP_EXE if your PHP lives elsewhere.
set "PHP_EXE=C:\php\php-8.4.5-nts-Win32-vs17-x64\php.exe"
"%PHP_EXE%" -c "%~dp0php.ini" %*
