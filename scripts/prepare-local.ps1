$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $projectRoot '.env'
if (-not (Test-Path -LiteralPath $envPath)) {
    Copy-Item -LiteralPath (Join-Path $projectRoot '.env.example') -Destination $envPath
}
$content = (Get-Content -LiteralPath $envPath -Raw).Replace("`r`n", "`n")
$values = @{
    DB_PASSWORD = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
    APP_KEY = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    H2_OPERATOR_PASSWORD = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
}
foreach ($entry in $values.GetEnumerator()) {
    if ($content -notmatch ('(?m)^' + $entry.Key + '=.+$')) {
        $content = [regex]::Replace($content, '(?m)^' + $entry.Key + '=$', '')
        $content += [Environment]::NewLine + $entry.Key + '=' + $entry.Value
    }
}
Set-Content -LiteralPath $envPath -Value $content -Encoding utf8
Write-Output 'Entorno ficticio local preparado. No se imprimen ni reemplazan secretos existentes.'
