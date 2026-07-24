param(
    [string]$BaseUrl = "http://localhost/2demayo-ocupacional/",
    [Parameter(Mandatory = $true)][string]$Usuario,
    [Parameter(Mandatory = $true)][string]$Password,
    [int]$MaxCompaniesToScan = 200
)

$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)
    Write-Host "[STEP] $Message" -ForegroundColor Cyan
}

function Write-Pass {
    param([string]$Message)
    Write-Host "[PASS] $Message" -ForegroundColor Green
}

function Write-Fail {
    param([string]$Message)
    Write-Host "[FAIL] $Message" -ForegroundColor Red
}

function Join-ApiUrl {
    param([string]$Base, [string]$Path)
    $normalized = $Base.TrimEnd('/') + '/'
    return $normalized + $Path
}

function Invoke-Json {
    param(
        [Parameter(Mandatory = $true)][ValidateSet('GET', 'POST')][string]$Method,
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Session,
        [object]$Body = $null
    )

    if ($Method -eq 'GET') {
        return Invoke-RestMethod -Method Get -Uri $Url -WebSession $Session -ContentType 'application/json'
    }

    $json = if ($null -eq $Body) { '{}' } else { ($Body | ConvertTo-Json -Depth 10 -Compress) }
    return Invoke-RestMethod -Method Post -Uri $Url -WebSession $Session -ContentType 'application/json' -Body $json
}

function Invoke-JsonAllowHttpError {
    param(
        [Parameter(Mandatory = $true)][ValidateSet('GET', 'POST')][string]$Method,
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Session,
        [object]$Body = $null
    )

    $json = if ($null -eq $Body) { '{}' } else { ($Body | ConvertTo-Json -Depth 10 -Compress) }

    try {
        if ($Method -eq 'GET') {
            $resp = Invoke-WebRequest -Method Get -Uri $Url -WebSession $Session -ContentType 'application/json'
        } else {
            $resp = Invoke-WebRequest -Method Post -Uri $Url -WebSession $Session -ContentType 'application/json' -Body $json
        }

        $payload = $null
        if ($resp.Content) {
            try { $payload = $resp.Content | ConvertFrom-Json } catch { $payload = $null }
        }

        return [pscustomobject]@{
            StatusCode = [int]$resp.StatusCode
            Payload = $payload
            Raw = $resp.Content
        }
    }
    catch {
        $status = 0
        $content = ''
        $payload = $null

        if ($_.Exception.Response) {
            try { $status = [int]$_.Exception.Response.StatusCode.value__ } catch { $status = 0 }
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $content = $reader.ReadToEnd()
                $reader.Close()
            } catch {
                $content = ''
            }
            if ($content) {
                try { $payload = $content | ConvertFrom-Json } catch { $payload = $null }
            }
        }

        return [pscustomobject]@{
            StatusCode = $status
            Payload = $payload
            Raw = $content
        }
    }
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$results = New-Object System.Collections.Generic.List[object]

try {
    $loginUrl = Join-ApiUrl -Base $BaseUrl -Path 'api_login.php'
    $empresasUrl = Join-ApiUrl -Base $BaseUrl -Path 'api_ocupacional_empresas.php'

    Write-Step "Login"
    $loginResp = Invoke-Json -Method POST -Url $loginUrl -Session $session -Body @{ usuario = $Usuario; password = $Password }
    if (-not $loginResp.success) {
        throw "Login sin success=true"
    }
    Write-Pass "Login correcto"
    $results.Add([pscustomobject]@{ Caso = 'AUTH-01'; Estado = 'PASS'; Detalle = 'Login correcto' })

    Write-Step "Listar empresas activas"
    $listResp = Invoke-Json -Method GET -Url ($empresasUrl + "?estado=activo&page=1&per_page=$MaxCompaniesToScan&sort_by=created_at&sort_dir=desc") -Session $session
    if (-not $listResp.success) {
        throw "No se pudo listar empresas activas"
    }
    $rows = @($listResp.data)
    if ($rows.Count -eq 0) {
        throw "No hay empresas activas para evaluar"
    }
    Write-Pass "Empresas activas leidas: $($rows.Count)"
    $results.Add([pscustomobject]@{ Caso = 'EMP-BLK-01'; Estado = 'PASS'; Detalle = "Activas=$($rows.Count)" })

    Write-Step "Buscar una empresa con bloqueos por dependencias"
    $candidate = $null
    foreach ($row in $rows) {
        $pre = Invoke-JsonAllowHttpError -Method POST -Url $empresasUrl -Session $session -Body @{
            accion = 'inactivar_seguro'
            id = [int]$row.id
            modo = 'prevalidar'
        }

        if ($pre.StatusCode -ne 200 -or -not $pre.Payload.success) {
            continue
        }

        $diag = $pre.Payload.data.diagnostico
        if ($null -eq $diag) {
            continue
        }

        $bloqueos = $diag.bloqueos
        $totalBloqueos = [int]($bloqueos.trabajadores_activos) + [int]($bloqueos.protocolos_activos) + [int]($bloqueos.ordenes_emitidas_o_en_proceso)
        if ($totalBloqueos -gt 0) {
            $candidate = [pscustomobject]@{
                id = [int]$row.id
                razon_social = [string]$row.razon_social
                bloqueos = $bloqueos
                total = $totalBloqueos
            }
            break
        }
    }

    if ($null -eq $candidate) {
        Write-Host "No se encontro empresa con bloqueos activos. Caso queda en SKIP controlado." -ForegroundColor Yellow
        $results.Add([pscustomobject]@{ Caso = 'EMP-BLK-02'; Estado = 'SKIP'; Detalle = 'Sin empresas bloqueadas en muestra' })
        Write-Host ""
        Write-Host "==== RESUMEN SMOKE INACTIVACION BLOQUEADA ====" -ForegroundColor Yellow
        $results | Format-Table -AutoSize | Out-String | Write-Host
        exit 0
    }

    Write-Pass "Empresa candidata id=$($candidate.id) bloqueos=$($candidate.total)"
    $results.Add([pscustomobject]@{ Caso = 'EMP-BLK-02'; Estado = 'PASS'; Detalle = "Candidata id=$($candidate.id), bloqueos=$($candidate.total)" })

    Write-Step "Intentar aplicar inactivacion sin force (esperado: 409)"
    $apply = Invoke-JsonAllowHttpError -Method POST -Url $empresasUrl -Session $session -Body @{
        accion = 'inactivar_seguro'
        id = $candidate.id
        modo = 'aplicar'
        force = $false
    }

    if ($apply.StatusCode -ne 409) {
        throw "Se esperaba HTTP 409 y se obtuvo $($apply.StatusCode)"
    }

    $errMsg = [string]($apply.Payload.error)
    if (-not $errMsg) {
        throw "Respuesta 409 sin mensaje de error"
    }

    Write-Pass "Inactivacion sin force correctamente bloqueada (409)"
    $results.Add([pscustomobject]@{ Caso = 'EMP-BLK-03'; Estado = 'PASS'; Detalle = 'Bloqueo 409 confirmado' })

    Write-Host ""
    Write-Host "==== RESUMEN SMOKE INACTIVACION BLOQUEADA ====" -ForegroundColor Yellow
    $results | Format-Table -AutoSize | Out-String | Write-Host
    exit 0
}
catch {
    Write-Fail $_.Exception.Message
    Write-Host ""
    Write-Host "==== RESUMEN PARCIAL ====" -ForegroundColor Yellow
    if ($results.Count -gt 0) {
        $results | Format-Table -AutoSize | Out-String | Write-Host
    } else {
        Write-Host "Sin casos completados." -ForegroundColor Yellow
    }
    exit 1
}
