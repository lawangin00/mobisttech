@echo off
setlocal
set "ROOT=C:\mobisttech"
set "CSC=C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
set "OUT=%~dp0bin"
if not exist "%CSC%" (
  echo C# compiler not found: %CSC%
  exit /b 2
)
if not exist "%OUT%" mkdir "%OUT%"
"%CSC%" /nologo /target:winexe /platform:anycpu /optimize+ /out:"%OUT%\mobiST Control.exe" /win32icon:"%ROOT%\backend\public\favicon.ico" /reference:System.Windows.Forms.dll /reference:System.Drawing.dll /reference:System.Management.dll /reference:System.Core.dll "%~dp0Program.cs" "%~dp0ControlOps.cs" "%~dp0MainForm.cs"
if errorlevel 1 exit /b %errorlevel%
echo Built "%OUT%\mobiST Control.exe"
