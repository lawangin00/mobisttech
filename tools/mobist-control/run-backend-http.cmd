@echo off
title MOBIST_CONTROL_backend_http
cd /d C:\mobisttech\backend
C:\php\php.exe artisan serve --host=127.0.0.1 --port=18080 --tries=1
