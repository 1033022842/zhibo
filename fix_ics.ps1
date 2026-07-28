$logPath = "d:\phpstudy_pro\WWW\douyin\fix_ics_output.txt"
"Script started at $(Get-Date)" | Out-File $logPath

$m = New-Object -ComObject HNetCfg.HNetShare
$connections = $m.EnumEveryConnection

# 在 本地连接* 10 上启用 ICS_PRIVATE（接收端）
foreach ($c in $connections) {
    $props = $m.NetConnectionProps.Invoke($c)
    
    if ($props.Name -eq "本地连接* 10") {
        "Enabling ICS PRIVATE on: $($props.Name)" | Out-File $logPath -Append
        $sharingCfg = $m.INetSharingConfigurationForINetConnection.Invoke($c)
        try {
            $sharingCfg.EnableSharing(1)
            "  Success." | Out-File $logPath -Append
        } catch {
            "  ERROR: $_" | Out-File $logPath -Append
        }
    }
}

"" | Out-File $logPath -Append

# 在 vEthernet (外部) 上启用 ICS_PUBLIC
foreach ($c in $connections) {
    $props = $m.NetConnectionProps.Invoke($c)
    
    if ($props.Name -eq "vEthernet (外部)") {
        "Enabling ICS PUBLIC on: $($props.Name)" | Out-File $logPath -Append
        $sharingCfg = $m.INetSharingConfigurationForINetConnection.Invoke($c)
        try {
            $sharingCfg.EnableSharing(0)
            "  Success." | Out-File $logPath -Append
        } catch {
            "  ERROR: $_" | Out-File $logPath -Append
        }
    }
}

"" | Out-File $logPath -Append
"=== Result ===" | Out-File $logPath -Append

$sc = $m.EnumEveryConnection
foreach ($c in $sc) {
    $props = $m.NetConnectionProps.Invoke($c)
    $sharingCfg = $m.INetSharingConfigurationForINetConnection.Invoke($c)
    if ($sharingCfg.SharingEnabled) {
        $type = if ($sharingCfg.SharingConnectionType -eq 0) { "PUBLIC" } else { "PRIVATE" }
        "$($props.Name): $type" | Out-File $logPath -Append
    }
}

"Script finished at $(Get-Date)" | Out-File $logPath -Append
