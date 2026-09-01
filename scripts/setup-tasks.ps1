<#
.SYNOPSIS
    Sets up automatic ZKTeco attendance sync on this machine.

.DESCRIPTION
    Registers the two background services the automatic sync needs:

      ZKTeco Scheduler    - ticks Laravel's scheduler, which runs
                            attendance:sync and attendance:push every 5 minutes.
      ZKTeco Queue Worker - drains the queue so the dashboard push actually sends.

    Both start at logon and restart automatically if they stop.
    Re-running this script is safe; it replaces any previous registration.

.PARAMETER Php
    Full path to php.exe. Auto-detected from PATH if omitted.

.PARAMETER Root
    Full path to the project root. Defaults to this script's parent folder.

.PARAMETER User
    Account the tasks run as. Defaults to the current user.

.EXAMPLE
    # From an ADMIN PowerShell window, in the project root:
    powershell -ExecutionPolicy Bypass -File scripts\setup-tasks.ps1
#>

[CmdletBinding()]
param(
    [string]$Php,
    [string]$Root,
    [string]$User = "$env:USERDOMAIN\$env:USERNAME"
)

$ErrorActionPreference = 'Stop'

# --- Resolve paths -----------------------------------------------------------

if (-not $Root) {
    $Root = Split-Path -Parent $PSScriptRoot
}

if (-not $Php) {
    $found = Get-Command php.exe -ErrorAction SilentlyContinue
    if (-not $found) {
        throw "php.exe not found on PATH. Re-run with -Php 'C:\path\to\php.exe'."
    }
    $Php = $found.Source
}

if (-not (Test-Path $Php))                          { throw "php.exe not found at: $Php" }
if (-not (Test-Path (Join-Path $Root 'artisan')))   { throw "No artisan file in: $Root" }

Write-Host "PHP     : $Php"
Write-Host "Project : $Root"
Write-Host "Run as  : $User"
Write-Host ""

# --- Verify we are elevated --------------------------------------------------

$isAdmin = ([Security.Principal.WindowsPrincipal] `
    [Security.Principal.WindowsIdentity]::GetCurrent()
).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    throw "This script must be run from an Administrator PowerShell window."
}

# --- Register the tasks ------------------------------------------------------

function Register-ZkTask {
    param([string]$Name, [string]$Arguments, [string]$Description)

    $action = New-ScheduledTaskAction -Execute $Php `
                                      -Argument $Arguments `
                                      -WorkingDirectory $Root

    $trigger = New-ScheduledTaskTrigger -AtLogOn -User $User

    # Never time out, restart if it dies, and don't start a second copy.
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries `
                                             -DontStopIfGoingOnBatteries `
                                             -RestartCount 999 `
                                             -RestartInterval (New-TimeSpan -Minutes 1) `
                                             -ExecutionTimeLimit ([TimeSpan]::Zero) `
                                             -MultipleInstances IgnoreNew

    $principal = New-ScheduledTaskPrincipal -UserId $User -RunLevel Highest

    Unregister-ScheduledTask -TaskName $Name -Confirm:$false -ErrorAction SilentlyContinue

    Register-ScheduledTask -TaskName $Name `
                           -Action $action `
                           -Trigger $trigger `
                           -Settings $settings `
                           -Principal $principal `
                           -Description $Description | Out-Null

    Start-ScheduledTask -TaskName $Name

    Write-Host "  [ok] $Name"
}

Write-Host "Registering scheduled tasks..."

Register-ZkTask -Name 'ZKTeco Scheduler' `
                -Arguments 'artisan schedule:work' `
                -Description 'Runs the Laravel scheduler for ZKTeco attendance sync and dashboard push.'

Register-ZkTask -Name 'ZKTeco Queue Worker' `
                -Arguments 'artisan queue:work --tries=5 --sleep=3 --max-time=3600' `
                -Description 'Processes queued jobs, including the dashboard attendance push.'

Write-Host ""
Write-Host "Done. Verify with:  Get-ScheduledTask -TaskName 'ZKTeco*'"
