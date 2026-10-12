param([Parameter(Mandatory = $true)][string]$ApiUrl)
$ErrorActionPreference = 'Stop'
$base = $ApiUrl.TrimEnd('/') -replace '/api$', ''
if (-not $base.StartsWith('https://')) { throw 'Use the public HTTPS backend URL.' }
foreach ($path in @('/up', '/api/movies', '/api/cinemas')) {
    $response = Invoke-WebRequest -Uri ($base + $path) -Headers @{ Accept = 'application/json' } -UseBasicParsing
    if ($response.StatusCode -ne 200) { throw "Failed: $path" }
    Write-Output "OK $path"
}
$origin = 'https://cineon-cinema.vercel.app'
$preflight = Invoke-WebRequest -Uri ($base + '/api/auth/login') -Method Options -Headers @{
    Origin = $origin
    'Access-Control-Request-Method' = 'POST'
    'Access-Control-Request-Headers' = 'content-type,authorization,x-session-id,x-booking-access-token'
} -UseBasicParsing
if ([string]$preflight.Headers['Access-Control-Allow-Origin'] -ne $origin) {
    throw 'CORS does not allow the Vercel origin.'
}
if ([string]$preflight.Headers['Access-Control-Allow-Credentials'] -ne 'true') {
    throw 'Credentialed API requests are not enabled.'
}
Write-Output 'OK CORS for Vercel (login/guest booking headers)'
