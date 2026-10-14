@echo off
set "PHP_EXE=C:\php\php-8.4.5-nts-Win32-vs17-x64\php.exe"
set "COMPOSER_HOME=%~dp0.composer-home"
"%PHP_EXE%" -c "%~dp0php.ini" "%~dp0composer.phar" %*
