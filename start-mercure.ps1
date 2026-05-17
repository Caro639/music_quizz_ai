# start-mercure.ps1
# Démarre le hub Mercure local pour music_quizz_ai

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$mercureDir = Join-Path $scriptDir "bin\mercure"

# Charger le JWT secret depuis .env
$envFile = Join-Path $scriptDir ".env"
$jwtSecret = "!ChangeThisMercureHubJWTSecretKey!"
if (Test-Path $envFile) {
    $match = Select-String -Path $envFile -Pattern 'MERCURE_JWT_SECRET="([^"]+)"'
    if ($match) {
        $jwtSecret = $match.Matches[0].Groups[1].Value
    }
}

$env:MERCURE_PUBLISHER_JWT_KEY = $jwtSecret
$env:MERCURE_SUBSCRIBER_JWT_KEY = $jwtSecret
$env:MERCURE_CERT_FILE = Join-Path $mercureDir "mercure.crt"
$env:MERCURE_CERT_KEY_FILE = Join-Path $mercureDir "mercure.key"

Write-Host "Démarrage du hub Mercure sur https://localhost:3000"
Write-Host "Hub URL : https://localhost:3000/.well-known/mercure"
Write-Host "Appuyez sur Ctrl+C pour arrêter."

Set-Location $mercureDir
.\mercure.exe run --config project.Caddyfile
