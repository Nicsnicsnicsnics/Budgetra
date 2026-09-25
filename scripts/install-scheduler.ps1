<#
.SYNOPSIS
    Registers the Windows task that drives Laravel's scheduler.

.DESCRIPTION
    routes/console.php schedules the trip and itinerary reminders, but
    Schedule::command() does nothing on its own — something has to run
    `php artisan schedule:run` every minute. On a server that is a cron entry.
    On Windows it is this task.

    Without it the two reminder toggles in Settings save correctly and are read
    correctly by their commands, and then no notification ever arrives, because
    the commands are never invoked.

    The nightly photo backfill is NOT part of this: it is gated behind
    SERPAPI_IMAGE_BACKFILL and stays off, so nothing here spends SerpAPI quota.

    Safe to run more than once — an existing task is removed and re-registered,
    so this doubles as "apply my changes".

.PARAMETER Remove
    Unregister the task and exit.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\install-scheduler.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\install-scheduler.ps1 -Remove
#>
[CmdletBinding()]
param(
    [switch] $Remove
)

$ErrorActionPreference = 'Stop'

$TaskName = 'Budgetra Scheduler'

# Resolved from this file rather than hardcoded, so moving or re-cloning the
# repo does not silently leave the task pointing at the old copy.
$RepoRoot    = Split-Path -Parent $PSScriptRoot
$LauncherVbs = Join-Path $PSScriptRoot 'schedule-run.vbs'

function Remove-BudgetraTask {
    if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
        return $true
    }
    return $false
}

if ($Remove) {
    if (Remove-BudgetraTask) {
        Write-Host "Removed '$TaskName'. Reminders will stop firing." -ForegroundColor Yellow
    } else {
        Write-Host "'$TaskName' was not registered; nothing to remove."
    }
    return
}

if (-not (Test-Path $LauncherVbs)) {
    throw "Launcher not found at $LauncherVbs - scripts\schedule-run.vbs is required."
}

# Fail here, readably, rather than letting every pass fail silently in the
# background for days.
$php = if (Test-Path 'C:\php\php.exe') {
    'C:\php\php.exe'
} else {
    (Get-Command php -ErrorAction SilentlyContinue).Source
}
if (-not $php) {
    throw 'PHP not found at C:\php\php.exe or on PATH. Install PHP, or edit scripts\schedule-run.vbs.'
}

Write-Host "Repo    : $RepoRoot"
Write-Host "PHP     : $php"
Write-Host "Launcher: $LauncherVbs"
Write-Host ''

# wscript.exe, not php.exe: the .vbs is what keeps a console window from
# appearing every minute. See the comment at the top of schedule-run.vbs.
$action = New-ScheduledTaskAction -Execute 'wscript.exe' `
    -Argument "`"$LauncherVbs`"" -WorkingDirectory $RepoRoot

# A Once trigger dated in the past, repeating forever — deliberately NOT
# -AtLogOn. AtLogOn looks like the natural fit, but its repetition only begins
# the next time someone logs on: the task registers cleanly, LastTaskResult
# reads 0, NextRunTime comes back empty, and nothing repeats until a
# logoff/logon cycle. That is a poor thing to discover a week later when no
# reminder has arrived. Starting in the past makes the first repeat due
# immediately, and StartWhenAvailable resumes the cycle after the machine has
# been off.
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(-1) `
    -RepetitionInterval (New-TimeSpan -Minutes 1)

# Blanked on purpose: Task Scheduler reads an empty <Duration> as "repeat
# indefinitely", and rejects the [TimeSpan]::MaxValue that looks like the way to
# say that ("The task XML contains a value which is incorrectly formatted or out
# of range. Duration:P99999999DT23H59M59S"). StopAtDurationEnd has to come with
# it — left at its default of true it contradicts the open-ended duration.
$trigger.Repetition.Duration          = ''
$trigger.Repetition.StopAtDurationEnd = $false

# IgnoreNew is not optional: the database is Supabase over the network, so a
# pass can outlast the minute until the next one. Without it slow passes stack.
$settings = New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

# Limited, as the current interactive user: no admin prompt, no stored
# credential, and the task sees the same PHP and environment you do.
$principal = New-ScheduledTaskPrincipal `
    -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited

if (Remove-BudgetraTask) {
    Write-Host "Replacing the existing '$TaskName' task."
}

Register-ScheduledTask -TaskName $TaskName `
    -Action $action -Trigger $trigger -Settings $settings -Principal $principal `
    -Description 'Runs php artisan schedule:run every minute for Budgetra (trip + itinerary reminders).' | Out-Null

Write-Host "Registered '$TaskName' - runs every minute." -ForegroundColor Green
Write-Host ''
Write-Host 'Check on it with:'
Write-Host "    Get-ScheduledTask -TaskName '$TaskName' | Get-ScheduledTaskInfo"
Write-Host ''
Write-Host 'LastTaskResult 0 and a NextRunTime about a minute out means it is working.'
Write-Host 'An EMPTY NextRunTime means the repetition never started - that is a failure.'
