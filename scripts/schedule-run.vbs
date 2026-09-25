' Runs one pass of Laravel's scheduler, with no window.
'
' The Windows task points at this file rather than at php.exe directly, and the
' only reason is the 0 near the bottom: it is WScript.Shell.Run's window style,
' and it is the difference between a quiet machine and a console window
' appearing every sixty seconds, all day, over whatever you are working on.
' Neither `start /min` nor `powershell -WindowStyle Hidden` avoids that flash;
' this does.
'
' The False is "do not wait" — the task returns immediately and the scheduler
' pass runs on its own. Overlapping passes are prevented by the task's
' MultipleInstances=IgnoreNew setting, not from here.
'
' Paths resolve from this file's own location, so the repo can be moved or
' cloned elsewhere without editing anything.

Option Explicit

Dim shell, fso, scriptDir, repoRoot, php

Set shell = CreateObject("WScript.Shell")
Set fso   = CreateObject("Scripting.FileSystemObject")

scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)
repoRoot  = fso.GetParentFolderName(scriptDir)

' The usual install, falling back to PATH so this keeps working if PHP moves.
php = "C:\php\php.exe"
If Not fso.FileExists(php) Then php = "php.exe"

shell.CurrentDirectory = repoRoot
shell.Run """" & php & """ artisan schedule:run", 0, False
