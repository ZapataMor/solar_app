' Runs the Laravel scheduler once, hidden (no console window).
' Registered in Windows Task Scheduler to fire every minute.
Set fso = CreateObject("Scripting.FileSystemObject")
projectDir = fso.GetParentFolderName(fso.GetParentFolderName(fso.GetParentFolderName(WScript.ScriptFullName)))

Set shell = CreateObject("WScript.Shell")
shell.CurrentDirectory = projectDir
shell.Run "cmd /c ""C:\tools\php84\php.exe artisan schedule:run >> storage\logs\scheduler.log 2>&1""", 0, True
