param(
    [string]$BaseUrl = "http://localhost/2demayo-ocupacional/",
    [Parameter(Mandatory = $true)][string]$Usuario,
    [Parameter(Mandatory = $true)][string]$Password,
    [switch]$KeepArtifacts
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

    try {
        $result = Invoke-Json -Method $Method -Url $Url -Session $Session -Body $Body
        return [pscustomobject]@{
            ok = $true
            status = 200
            payload = $result
            raw = $null
        }
    }
    catch {
        $resp = $_.Exception.Response
        if ($resp -eq $null) {
            throw
        }

        $status = [int]$resp.StatusCode
        $raw = ''
        if ($resp.Content) {
            $raw = [string]$resp.Content
        }

        $payload = $null
        if ($raw -ne '') {
            try {
                $payload = $raw | ConvertFrom-Json -Depth 20
            }
            catch {
                $payload = $null
            }
        }

        return [pscustomobject]@{
            ok = $false
            status = $status
            payload = $payload
            raw = $raw
        }
    }
}

function New-Ruc {
    # 11-digit synthetic RUC for smoke testing, prefixed with 20
    $stamp = Get-Date -Format 'MMddHHmm'
    $rand = Get-Random -Minimum 10 -Maximum 99
    $ruc = "20$stamp$rand"
    if ($ruc.Length -gt 11) {
        $ruc = $ruc.Substring(0, 11)
    }
    if ($ruc.Length -lt 11) {
        $ruc = $ruc.PadRight(11, '0')
    }
    return $ruc
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$results = New-Object System.Collections.Generic.List[object]
$artifacts = @()

try {
    $loginUrl = Join-ApiUrl -Base $BaseUrl -Path 'api_login.php'
    $empresasUrl = Join-ApiUrl -Base $BaseUrl -Path 'api_ocupacional_empresas.php'

    Write-Step "Login API"
    $loginResp = Invoke-Json -Method POST -Url $loginUrl -Session $session -Body @{ usuario = $Usuario; password = $Password }
    if (-not $loginResp.success) {
        throw "Login sin success=true"
    }
    Write-Pass "Login correcto para usuario $Usuario"
    $results.Add([pscustomobject]@{ Caso = 'AUTH-01'; Estado = 'PASS'; Detalle = 'Login correcto' })

    $rucA = New-Ruc
    $rucB = New-Ruc
    if ($rucA -eq $rucB) {
        $rucB = ($rucB.Substring(0, 10) + '9')
    }

    Write-Step "Crear empresa A para caso duplicado"
    $createA = Invoke-Json -Method POST -Url $empresasUrl -Session $session -Body @{
        ruc = $rucA
        razon_social = "SMOKE NEG A $((Get-Date).ToString('yyyyMMddHHmmss'))"
        nombre_comercial = 'SMOKE NEG A COMERCIAL'
        actividad = 'LABORATORIO'
        direccion = 'Av. Negativo 100'
        departamento = 'LIMA'
        provincia = 'LIMA'
        distrito = 'MIRAFLORES'
        telefono_1 = '977777771'
        telefono_2 = '977777773'
        contacto_1 = 'CONTACTO NEG A'
        contacto_2 = 'CONTACTO NEG A2'
        correo_1 = 'neg.a@empresa.test'
        correo_2 = 'neg.a2@empresa.test'
        rrhh_usuario = 'rrhh.neg.a'
        rrhh_password = 'rrhh.neg.a.123'
        doctor_usuario = 'doctor.neg.a'
        doctor_password = 'doctor.neg.a.123'
        formato_principal = 'Anexo 7-C'
        formato_certificado = 'Tipo A'
        observacion = 'Negativo A'
    }
    if (-not $createA.success -or -not $createA.data.id) {
        throw "No se pudo crear empresa A"
    }
    $empresaA = [pscustomobject]@{
        id = [int]$createA.data.id
        ruc = [string]$createA.data.ruc
    }
    $artifacts += $empresaA
    Write-Pass "Empresa A creada id=$($empresaA.id)"

    Write-Step "Crear empresa B para caso duplicado"
    $createB = Invoke-Json -Method POST -Url $empresasUrl -Session $session -Body @{
        ruc = $rucB
        razon_social = "SMOKE NEG B $((Get-Date).ToString('yyyyMMddHHmmss'))"
        nombre_comercial = 'SMOKE NEG B COMERCIAL'
        actividad = 'LABORATORIO'
        direccion = 'Av. Negativo 200'
        departamento = 'LIMA'
        provincia = 'LIMA'
        distrito = 'SURCO'
        telefono_1 = '977777772'
        telefono_2 = '977777774'
        contacto_1 = 'CONTACTO NEG B'
        contacto_2 = 'CONTACTO NEG B2'
        correo_1 = 'neg.b@empresa.test'
        correo_2 = 'neg.b2@empresa.test'
        rrhh_usuario = 'rrhh.neg.b'
        rrhh_password = 'rrhh.neg.b.123'
        doctor_usuario = 'doctor.neg.b'
        doctor_password = 'doctor.neg.b.123'
        formato_principal = 'Anexo 7-C'
        formato_certificado = 'Tipo A'
        observacion = 'Negativo B'
    }
    if (-not $createB.success -or -not $createB.data.id) {
        throw "No se pudo crear empresa B"
    }
    $empresaB = [pscustomobject]@{
        id = [int]$createB.data.id
        ruc = [string]$createB.data.ruc
    }
    $artifacts += $empresaB
    Write-Pass "Empresa B creada id=$($empresaB.id)"

    Write-Step "Caso negativo 1: actualizar B con RUC duplicado de A (espera 409)"
    $dupResp = Invoke-JsonAllowHttpError -Method POST -Url $empresasUrl -Session $session -Body @{
        accion = 'actualizar'
        id = $empresaB.id
        ruc = $empresaA.ruc
        razon_social = 'SMOKE DUPLICADO'
        nombre_comercial = 'SMOKE DUPLICADO COMERCIAL'
        actividad = 'LABORATORIO'
        direccion = 'Av. Dup 1'
        departamento = 'LIMA'
        provincia = 'LIMA'
        distrito = 'BARRANCO'
        telefono_1 = '966666661'
        telefono_2 = '966666664'
        contacto_1 = 'CONTACTO DUPLICADO'
        contacto_2 = 'CONTACTO DUPLICADO 2'
        correo_1 = 'dup@empresa.test'
        correo_2 = 'dup2@empresa.test'
        rrhh_usuario = 'rrhh.dup'
        rrhh_password = 'rrhh.dup.123'
        doctor_usuario = 'doctor.dup'
        doctor_password = 'doctor.dup.123'
        formato_principal = 'Anexo 7-C'
        formato_certificado = 'Tipo A'
        observacion = 'Negativo duplicado'
    }

    if ($dupResp.ok -or $dupResp.status -ne 409) {
        throw "Se esperaba HTTP 409 en duplicado de RUC, obtenido: $($dupResp.status)"
    }
    Write-Pass "Duplicado RUC bloqueado con 409"
    $results.Add([pscustomobject]@{ Caso = 'EMP-NEG-01'; Estado = 'PASS'; Detalle = 'Duplicado de RUC retorna 409' })

    Write-Step "Caso negativo 2: actualizar A con correo invalido (espera 422)"
    $mailResp = Invoke-JsonAllowHttpError -Method POST -Url $empresasUrl -Session $session -Body @{
        accion = 'actualizar'
        id = $empresaA.id
        ruc = $empresaA.ruc
        razon_social = 'SMOKE CORREO INVALIDO'
        nombre_comercial = 'SMOKE CORREO INVALIDO COMERCIAL'
        actividad = 'LABORATORIO'
        direccion = 'Av. Mail 2'
        departamento = 'LIMA'
        provincia = 'LIMA'
        distrito = 'CHORRILLOS'
        telefono_1 = '966666662'
        telefono_2 = '966666665'
        contacto_1 = 'CONTACTO MAIL'
        contacto_2 = 'CONTACTO MAIL 2'
        correo_1 = 'correo-invalido'
        correo_2 = 'mail2@empresa.test'
        rrhh_usuario = 'rrhh.mail'
        rrhh_password = 'rrhh.mail.123'
        doctor_usuario = 'doctor.mail'
        doctor_password = 'doctor.mail.123'
        formato_principal = 'Anexo 7-C'
        formato_certificado = 'Tipo A'
        observacion = 'Negativo correo'
    }

    if ($mailResp.ok -or $mailResp.status -ne 422) {
        throw "Se esperaba HTTP 422 en correo invalido, obtenido: $($mailResp.status)"
    }
    Write-Pass "Correo invalido bloqueado con 422"
    $results.Add([pscustomobject]@{ Caso = 'EMP-NEG-02'; Estado = 'PASS'; Detalle = 'Correo invalido retorna 422' })

    Write-Step "Caso negativo 3: actualizar id inexistente (espera 404)"
    $fakeId = 999999999
    $idResp = Invoke-JsonAllowHttpError -Method POST -Url $empresasUrl -Session $session -Body @{
        accion = 'actualizar'
        id = $fakeId
        ruc = (New-Ruc)
        razon_social = 'SMOKE ID INEXISTENTE'
        nombre_comercial = 'SMOKE ID INEXISTENTE COMERCIAL'
        actividad = 'LABORATORIO'
        direccion = 'Av. Fake 3'
        departamento = 'LIMA'
        provincia = 'LIMA'
        distrito = 'SJL'
        telefono_1 = '966666663'
        telefono_2 = '966666666'
        contacto_1 = 'CONTACTO FAKE'
        contacto_2 = 'CONTACTO FAKE 2'
        correo_1 = 'fake@empresa.test'
        correo_2 = 'fake2@empresa.test'
        rrhh_usuario = 'rrhh.fake'
        rrhh_password = 'rrhh.fake.123'
        doctor_usuario = 'doctor.fake'
        doctor_password = 'doctor.fake.123'
        formato_principal = 'Anexo 7-C'
        formato_certificado = 'Tipo A'
        observacion = 'Negativo id'
    }

    if ($idResp.ok -or $idResp.status -ne 404) {
        throw "Se esperaba HTTP 404 en id inexistente, obtenido: $($idResp.status)"
    }
    Write-Pass "ID inexistente retorna 404"
    $results.Add([pscustomobject]@{ Caso = 'EMP-NEG-03'; Estado = 'PASS'; Detalle = 'ID inexistente retorna 404' })

    if (-not $KeepArtifacts) {
        Write-Step "Limpieza: inactivar empresas de prueba"
        foreach ($a in $artifacts) {
            $null = Invoke-Json -Method POST -Url $empresasUrl -Session $session -Body @{
                accion = 'inactivar_seguro'
                id = [int]$a.id
                modo = 'aplicar'
                force = $true
            }
        }
        Write-Pass "Limpieza aplicada"
        $results.Add([pscustomobject]@{ Caso = 'EMP-NEG-99'; Estado = 'PASS'; Detalle = 'Limpieza de artefactos aplicada' })
    }
    else {
        Write-Step "KeepArtifacts habilitado: no se realiza limpieza"
        $results.Add([pscustomobject]@{ Caso = 'EMP-NEG-99'; Estado = 'SKIP'; Detalle = 'KeepArtifacts habilitado' })
    }

    Write-Host ""
    Write-Host "==== RESUMEN SMOKE NEGATIVO EMPRESAS ====" -ForegroundColor Yellow
    $results | Format-Table -AutoSize | Out-String | Write-Host

    Write-Host "Script completado." -ForegroundColor Green
    exit 0
}
catch {
    Write-Fail $_.Exception.Message

    if ($artifacts.Count -gt 0) {
        Write-Host "Artefactos creados durante la prueba:" -ForegroundColor Yellow
        $artifacts | ForEach-Object { Write-Host "- id=$($_.id), ruc=$($_.ruc)" -ForegroundColor Yellow }
    }

    Write-Host ""
    Write-Host "==== RESUMEN PARCIAL ====" -ForegroundColor Yellow
    if ($results.Count -gt 0) {
        $results | Format-Table -AutoSize | Out-String | Write-Host
    }
    else {
        Write-Host "Sin casos completados." -ForegroundColor Yellow
    }

    exit 1
}
