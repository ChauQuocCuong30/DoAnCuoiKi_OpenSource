param(
    [string]$BaseUrl = 'http://localhost/doulingo/public',
    [string]$Php = 'C:\xampp\php\php.exe',
    [switch]$Dictionary,
    [switch]$Learning
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Push-Location $projectRoot
try {
    if (-not (Test-Path $Php)) { throw "Khong tim thay PHP: $Php" }
    $lintFailed = $false
    Get-ChildItem app\Modules\Admin,app\Modules\Vocabulary,resources\views\admin,database\samples -Recurse -File -Filter *.php | ForEach-Object {
        & $Php -l $_.FullName
        if ($LASTEXITCODE -ne 0) { $lintFailed = $true }
    }
    if ($lintFailed) { throw 'PHP lint failed.' }
    foreach ($test in @('check_task2.php','check_task3.php','check_task4.php')) {
        & $Php (Join-Path 'database\samples' $test)
        if ($LASTEXITCODE -ne 0) { throw "Failed: $test" }
    }
    foreach ($test in @('tests\unit\Sm2SchedulerTest.php','tests\unit\learning.php')) {
        if (Test-Path $test) {
            & $Php $test
            if ($LASTEXITCODE -ne 0) { throw "Failed: $test" }
        } else { Write-Host "SKIP not merged yet: $test" -ForegroundColor Yellow }
    }
    $BaseUrl = $BaseUrl.TrimEnd('/')
    $script:passed = 0
    function Check([bool]$Ok, [string]$Label) {
        if (-not $Ok) { throw "FAIL: $Label" }
        $script:passed++
        Write-Host "PASS $Label" -ForegroundColor Green
    }
    function Request([string]$Path, $Session, $Body = $null, [hashtable]$Headers = @{}, [string]$ContentType = 'application/json; charset=utf-8') {
        $requestArgs = @{ Uri = "$BaseUrl/api/v1$Path"; Method = 'Get'; UseBasicParsing = $true; WebSession = $Session; Headers = $Headers; TimeoutSec = 30 }
        if ($null -ne $Body) {
            $requestArgs.Method = 'Post'
            $requestArgs.ContentType = $ContentType
            if ($Body -is [byte[]]) { $requestArgs.Body = $Body }
            else { $requestArgs.Body = [System.Text.Encoding]::UTF8.GetBytes(($Body | ConvertTo-Json -Depth 30 -Compress)) }
        }
        $raw = ''; $status = 0
        try { $response = Invoke-WebRequest @requestArgs; $status = [int]$response.StatusCode; $raw = $response.Content }
        catch {
            if ($null -eq $_.Exception.Response) { throw }
            $status = [int]$_.Exception.Response.StatusCode
            $raw = $_.ErrorDetails.Message
            if ([string]::IsNullOrEmpty($raw) -and $_.Exception.Response.PSObject.Methods.Name -contains 'GetResponseStream') {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                try { $raw = $reader.ReadToEnd() } finally { $reader.Dispose() }
            }
        }
        $json = $null
        try { $json = $raw | ConvertFrom-Json } catch { }
        return [pscustomobject]@{ Status = $status; Json = $json; Raw = $raw }
    }
    $guest = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Check ((Request '/admin/content/bootstrap' $guest).Status -eq 401) 'anonymous cannot access Admin API'
    $credential = Get-Credential -Message 'Nhap tai khoan Admin local de test (khong luu mat khau vao file)'
    if ($null -eq $credential) { throw 'Can tai khoan Admin de test HTTP.' }
    $admin = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $password = $credential.GetNetworkCredential().Password
    try { $login = Request '/auth/login' $admin @{ email = $credential.UserName; password = $password } }
    finally { $password = $null }
    Check ($login.Status -eq 200 -and $login.Json.data.role -eq 'admin') 'Admin login'
    $boot = Request '/admin/content/bootstrap' $admin
    Check ($boot.Status -eq 200 -and $boot.Json.data.csrf_token -match '^[a-f0-9]{64}$') 'CSRF bootstrap route'
    $csrf = @{ 'X-CSRF-Token' = $boot.Json.data.csrf_token }
    foreach ($kind in @('words','lessons','exercises')) {
        $result = Request "/admin/content/${kind}?page=1" $admin
        Check ($result.Status -eq 200 -and $null -ne $result.Json.data.total) "Admin list $kind"
    }
    Check ((Request '/admin/content/words' $admin @{english='blocked'}).Status -eq 403) 'write requires CSRF'
    Check ((Request '/admin/content/words' $admin @{} $csrf).Status -eq 422) 'validate write payload'
    Check ((Request '/admin/content/csv/commit' $admin @{preview_token=('0' * 64)} $csrf).Status -eq 422) 'reject forged CSV preview'
    Check ((Request '/admin/dictionary' $admin).Status -eq 422) 'dictionary lookup route exists'
    Check ((Request '/admin/dictionary/words' $admin @{lookup_token=('0' * 64);candidate_key='0:0:0'}).Status -eq 422) 'dictionary save rejects forged token'
    $boundary = 'Part2-' + [Guid]::NewGuid().ToString('N')
    $multipart = "--$boundary`r`nContent-Disposition: form-data; name=`"csv`"; filename=`"invalid.csv`"`r`nContent-Type: text/csv`r`n`r`nwrong,header`r`n--$boundary--`r`n"
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($multipart)
    Check ((Request '/admin/content/csv/preview' $admin $bytes $csrf "multipart/form-data; boundary=$boundary").Status -eq 422) 'CSV upload route and invalid header'
    $topicPage = Invoke-WebRequest -Uri "$BaseUrl/admin/topics" -WebSession $admin -UseBasicParsing -TimeoutSec 30
    $match = [regex]::Match($topicPage.Content, 'name="csrf_token" value="([a-f0-9]{64})"')
    Check ($match.Success -and $match.Groups[1].Value -eq $boot.Json.data.csrf_token) 'Topic and Admin API use same CSRF token'
    if ($Learning) {
        $page = Invoke-WebRequest -Uri "$BaseUrl/learning" -WebSession $admin -UseBasicParsing -TimeoutSec 30
        Check ([int]$page.StatusCode -eq 200) 'PR20 learning page and merged route'
    }
    foreach ($path in @('dictionary','topics','words','lessons','exercises','import')) {
        $response = Invoke-WebRequest -Uri "$BaseUrl/admin/$path" -WebSession $admin -UseBasicParsing -TimeoutSec 30
        Check ([int]$response.StatusCode -eq 200) "Admin page $path"
    }
    if ($Dictionary) {
        $lookup = Request '/admin/dictionary?word=milk' $admin
        Check ($lookup.Status -eq 200 -and $lookup.Json.data.lookup_token -match '^[a-f0-9]{64}$') 'live dictionary API'
    } else { Write-Host 'SKIP external dictionary; add -Dictionary to test internet provider.' -ForegroundColor Yellow }
    Write-Host "RESULT: $script:passed HTTP checks passed. Task 2-4 fixtures were rolled back." -ForegroundColor Green
} finally { Pop-Location }
