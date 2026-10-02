' Runs the Laravel scheduler once, hidden (no console window).
' Registered in Windows Task Scheduler to fire every minute.
' PHP: the first that exists of C:\tools\php84 (team machine), Laravel Herd, or "php" on the PATH.
' It needs CA certificates for the HTTPS APIs (Herd has them; otherwise see storage/app/certs).
Set fso = CreateObject("Scripting.FileSystemObject")
projectDir = fso.GetParentFolderName(fso.GetParentFolderName(fso.GetParentFolderName(WScript.ScriptFullName)))

Set shell = CreateObject("WScript.Shell")
userProfile = shell.ExpandEnvironmentStrings("%USERPROFILE%")

php = "php"
candidates = Array("C:\tools\php84\php.exe", userProfile & "\.config\herd\bin\php84\php.exe")
For Each candidate In candidates
    If fso.FileExists(candidate) Then
        php = candidate
        Exit For
    End If
Next

shell.CurrentDirectory = projectDir
shell.Run "cmd /c """"" & php & """ artisan schedule:run >> storage\logs\scheduler.log 2>&1""", 0, True
