param(
    [string]$BaseUrl = "http://localhost/2demayo-ocupacional/",
    [Parameter(Mandatory = $true)][string]$Usuario,
    [Parameter(Mandatory = $true)][string]$Password,
    [switch]$SkipCreate,
    [switch]$KeepActive
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
$createdCompany = $null

try {
    $loginUrl = Join-ApiUrl -Base $BaseUrl -Path 'api_login.php'
    $authUrl = Join-ApiUrl -Base $BaseUrl -Path 'api_auth_status.php'
    $empresasUrl = Join-ApiUrl -Base $BaseUrl -Path 'api_ocupacional_empresas.php'

    Write-Step "Login API"
    $loginResp = Invoke-Json -Method POST -Url $loginUrl -Session $session -Body @{ usuario = $Usuario; password = $Password }
    if (-not $loginResp.success) {
        throw "Login sin success=true"
    }
    Write-Pass "Login correcto para usuario $Usuario"
    $results.Add([pscustomobject]@{ Caso = 'AUTH-01'; Estado = 'PASS'; Detalle = 'Login correcto' })

    Write-Step "Verificar sesion autenticada"
    $authResp = Invoke-Json -Method GET -Url $authUrl -Session $session
    if (-not $authResp.authenticated) {
        throw "Sesion no autenticada tras login"
    }
    Write-Pass "Sesion autenticada, rol: $($authResp.rol)"
    $results.Add([pscustomobject]@{ Caso = 'AUTH-02'; Estado = 'PASS'; Detalle = 'Sesion autenticada' })

    Write-Step "Listar empresas ocupacionales (estado=todos)"
    $listResp = Invoke-Json -Method GET -Url ($empresasUrl + '?estado=todos&page=1&per_page=20&sort_by=razon_social&sort_dir=asc') -Session $session
    if (-not $listResp.success) {
        throw "Listado de empresas sin success=true"
    }
    $total = [int]($listResp.meta.total)
    Write-Pass "Listado ok. Total actual: $total"
    $results.Add([pscustomobject]@{ Caso = 'EMP-06'; Estado = 'PASS'; Detalle = "Listado correcto, total=$total" })

    if (-not $SkipCreate) {
        $ruc = New-Ruc
        $razon = "SMOKE QA EMPRESA $((Get-Date).ToString('yyyyMMddHHmmss'))"

        Write-Step "Crear empresa de prueba ($ruc)"
        $createResp = Invoke-Json -Method POST -Url $empresasUrl -Session $session -Body @{
            ruc = $ruc
            razon_social = $razon
            nombre_comercial = "$razon COMERCIAL"
            actividad = 'LABORATORIO'
            direccion = 'Av. QA 123'
            departamento = 'LIMA'
            provincia = 'LIMA'
            distrito = 'MIRAFLORES'
            telefono_1 = '999999999'
            telefono_2 = '988888888'
            contacto_1 = 'CONTACTO QA'
            contacto_2 = 'CONTACTO QA 2'
            correo_1 = 'qa.smoke@empresa.test'
            correo_2 = 'qa2.smoke@empresa.test'
            rrhh_usuario = 'rrhh.qa'
            rrhh_password = 'rrhh.qa.123'
            doctor_usuario = 'doctor.qa'
            doctor_password = 'doctor.qa.123'
            formato_principal = 'Anexo 7-C'
            formato_certificado = 'Tipo A'
            observacion = 'Smoke QA empresa'
        }

        if (-not $createResp.success -or -not $createResp.data.id) {
            throw "Creacion de empresa no devolvio id"
        }

        $createdCompany = [pscustomobject]@{
            id = [int]$createResp.data.id
            ruc = [string]$createResp.data.ruc
            razon_social = [string]$createResp.data.razon_social
        }

        Write-Pass "Empresa creada id=$($createdCompany.id), ruc=$($createdCompany.ruc)"
        $results.Add([pscustomobject]@{ Caso = 'EMP-01'; Estado = 'PASS'; Detalle = "Empresa creada id=$($createdCompany.id)" })

        $razonEditada = "$($createdCompany.razon_social) EDITADA"
        Write-Step "Actualizar empresa creada"
        $updateResp = Invoke-Json -Method POST -Url $empresasUrl -Session $session -Body @{
            accion = 'actualizar'
            id = $createdCompany.id
            ruc = $createdCompany.ruc
            razon_social = $razonEditada
            nombre_comercial = "$razonEditada COMERCIAL"
            actividad = 'LABORATORIO'
            direccion = 'Av. QA Editada 456'
            departamento = 'LIMA'
            provincia = 'LIMA'
            distrito = 'SURCO'
            telefono_1 = '988888888'
            telefono_2 = '977777777'
            contacto_1 = 'CONTACTO QA EDIT'
            contacto_2 = 'CONTACTO QA EDIT 2'
            correo_1 = 'qa.editada@empresa.test'
            correo_2 = 'qa2.editada@empresa.test'
            rrhh_usuario = 'rrhh.qa.edit'
            rrhh_password = 'rrhh.qa.edit.123'
            doctor_usuario = 'doctor.qa.edit'
            doctor_password = 'doctor.qa.edit.123'
            formato_principal = 'Anexo 7-C'
            formato_certificado = 'Tipo B'
            observacion = 'Smoke QA empresa editada'
        }

        if (-not $updateResp.success) {
            throw "Actualizacion de empresa no exitosa"
        }

        $createdCompany.razon_social = [string]$updateResp.data.razon_social
        Write-Pass "Actualizacion aplicada para id=$($createdCompany.id)"
        $results.Add([pscustomobject]@{ Caso = 'EMP-19'; Estado = 'PASS'; Detalle = 'Actualizacion de empresa correcta' })

        Write-Step "Prevalidar inactivacion segura"
        $preResp = Invoke-Json -Method POST -Url $empresasUrl -Session $session -Body @{
            accion = 'inactivar_seguro'
            id = $createdCompany.id
            modo = 'prevalidar'
        }

        if (-not $preResp.success -or -not $preResp.data.diagnostico) {
            throw "Prevalidacion no devolvio diagnostico"
        }

        $diag = $preResp.data.diagnostico
        Write-Pass "Prevalidacion ok: puede_inactivar=$($diag.puede_inactivar)"
        $results.Add([pscustomobject]@{ Caso = 'EMP-16'; Estado = 'PASS'; Detalle = "Prevalidacion ok, puede_inactivar=$($diag.puede_inactivar)" })

        if (-not $KeepActive) {
            Write-Step "Aplicar inactivacion segura"
            $applyResp = Invoke-Json -Method POST -Url $empresasUrl -Session $session -Body @{
                accion = 'inactivar_seguro'
                id = $createdCompany.id
                modo = 'aplicar'
                force = $false
            }

            if (-not $applyResp.success) {
                throw "Aplicacion de inactivacion segura no exitosa"
            }

            Write-Pass "Inactivacion segura aplicada"
            $results.Add([pscustomobject]@{ Caso = 'EMP-17'; Estado = 'PASS'; Detalle = 'Inactivacion segura aplicada' })

            Write-Step "Revalidar estado tras inactivacion"
            $q = [System.Web.HttpUtility]::UrlEncode($createdCompany.ruc)
            $verifyResp = Invoke-Json -Method GET -Url ($empresasUrl + "?estado=todos&q=$q&page=1&per_page=20") -Session $session
            $row = $null
            if ($verifyResp.data -and $verifyResp.data.Count -gt 0) {
                $row = $verifyResp.data | Where-Object { [int]$_.id -eq $createdCompany.id } | Select-Object -First 1
            }
            if ($null -eq $row) {
                throw "No se encontro la empresa recien creada en verificacion"
            }
            if ([string]$row.estado -ne 'inactivo') {
                throw "Estado esperado inactivo, obtenido: $($row.estado)"
            }
            Write-Pass "Empresa verificada en estado inactivo"
            $results.Add([pscustomobject]@{ Caso = 'EMP-18'; Estado = 'PASS'; Detalle = 'Estado inactivo verificado' })

            Write-Step "Reactivar empresa inactiva"
            $reactResp = Invoke-Json -Method POST -Url $empresasUrl -Session $session -Body @{
                accion = 'reactivar_seguro'
                id = $createdCompany.id
                modo = 'aplicar'
            }

            if (-not $reactResp.success) {
                throw "Reactivacion segura no exitosa"
            }

            Write-Pass "Reactivacion segura aplicada"
            $results.Add([pscustomobject]@{ Caso = 'EMP-20'; Estado = 'PASS'; Detalle = 'Reactivacion segura aplicada' })

            Write-Step "Revalidar estado tras reactivacion"
            $verifyReactResp = Invoke-Json -Method GET -Url ($empresasUrl + "?estado=todos&q=$q&page=1&per_page=20") -Session $session
            $rowReact = $null
            if ($verifyReactResp.data -and $verifyReactResp.data.Count -gt 0) {
                $rowReact = $verifyReactResp.data | Where-Object { [int]$_.id -eq $createdCompany.id } | Select-Object -First 1
            }
            if ($null -eq $rowReact) {
                throw "No se encontro la empresa recien reactivada en verificacion"
            }
            if ([string]$rowReact.estado -ne 'activo') {
                throw "Estado esperado activo tras reactivacion, obtenido: $($rowReact.estado)"
            }
            Write-Pass "Empresa verificada en estado activo"
            $results.Add([pscustomobject]@{ Caso = 'EMP-21'; Estado = 'PASS'; Detalle = 'Estado activo verificado tras reactivacion' })
        } else {
            Write-Step "KeepActive habilitado: no se inactiva la empresa creada"
            $results.Add([pscustomobject]@{ Caso = 'EMP-17'; Estado = 'SKIP'; Detalle = 'KeepActive habilitado' })
            $results.Add([pscustomobject]@{ Caso = 'EMP-20'; Estado = 'SKIP'; Detalle = 'KeepActive habilitado' })
        }
    } else {
        Write-Step "SkipCreate habilitado: se omite creacion/inactivacion de empresa"
        $results.Add([pscustomobject]@{ Caso = 'EMP-01'; Estado = 'SKIP'; Detalle = 'SkipCreate habilitado' })
    }

    Write-Host ""
    Write-Host "==== RESUMEN SMOKE EMPRESAS OCUPACIONAL ====" -ForegroundColor Yellow
    $results | Format-Table -AutoSize | Out-String | Write-Host

    Write-Host "Script completado." -ForegroundColor Green
    exit 0
}
catch {
    Write-Fail $_.Exception.Message
    if ($createdCompany -ne $null) {
        Write-Host "Empresa de prueba creada: id=$($createdCompany.id), ruc=$($createdCompany.ruc)" -ForegroundColor Yellow
        Write-Host "Revise si necesita limpieza manual." -ForegroundColor Yellow
    }

    Write-Host ""
    Write-Host "==== RESUMEN PARCIAL ====" -ForegroundColor Yellow
    if ($results.Count -gt 0) {
        $results | Format-Table -AutoSize | Out-String | Write-Host
    } else {
        Write-Host "Sin casos completados." -ForegroundColor Yellow
    }

    exit 1
}
