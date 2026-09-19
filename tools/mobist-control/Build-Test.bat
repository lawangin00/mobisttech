@echo off
setlocal
set "ROOT=C:\mobisttech"
set "CSC=C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
set "OUT=%ROOT%\.local\mt62"
if not exist "%OUT%" mkdir "%OUT%"
"%CSC%" /nologo /target:exe /platform:anycpu /optimize+ /out:"%OUT%\mobist-control-test.exe" /reference:System.Windows.Forms.dll /reference:System.Drawing.dll /reference:System.Management.dll /reference:System.Core.dll "%~dp0Program.cs" "%~dp0ControlOps.cs" "%~dp0MainForm.cs"
if errorlevel 1 exit /b %errorlevel%
echo Built "%OUT%\mobist-control-test.exe"
