$m = New-Object -ComObject HNetCfg.HNetShare

$connections = $m.EnumEveryConnection
foreach ($c in $connections) {
    $props = $m.NetConnectionProps.Invoke($c)
    $sharingCfg = $m.INetSharingConfigurationForINetConnection.Invoke($c)
    
    Write-Host "---"
    Write-Host "Name: $($props.Name)"
    Write-Host "Guid: $($props.Guid)"
    Write-Host "SharingEnabled: $($sharingCfg.SharingEnabled)"
    Write-Host "SharingConnectionType: $($sharingCfg.SharingConnectionType)"
}
