param(
    [ValidateSet('Preparar','Iniciar','Detener','Asistencia','Respaldar')][string]$Accion = 'Iniciar',
    [string]$Base = 'rsu_v3_demo',
    [string]$Escucha = '127.0.0.1',
    [int]$Puerto = 8104,
    [string]$Php = 'php',
    [string]$MysqlDump = 'mysqldump'
)
$ErrorActionPreference = 'Stop'
if ($Base -notmatch '^rsu_v3_demo(?:_[a-z0-9_]+)?$') { throw 'Usa una base de demostración dedicada rsu_v3_demo.' }
if ($Escucha -notmatch '^(127\.0\.0\.1|10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+)$') {
    throw 'Usa loopback o la dirección privada del adaptador Wi-Fi.'
}
$Repo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$Estado = Join-Path $Repo 'storage/logs/rsu-v3-demo.pid'
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:RSU_DEMO_DATA = 'true'
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = $Base
$env:APP_URL = "http://${Escucha}:$Puerto"

Push-Location $Repo
try {
    switch ($Accion) {
        Preparar {
            if ($env:RSU_DEMO_PASSWORD.Length -lt 12) { throw 'Define RSU_DEMO_PASSWORD con una clave ficticia propia.' }
            if (!$env:ADMIN_PASSWORD -or !$env:ADMIN_EMAIL) { throw 'Define ADMIN_EMAIL y ADMIN_PASSWORD exclusivos para el ensayo.' }
            & $Php artisan migrate --seed --force
            if ($LASTEXITCODE -ne 0) { throw 'Migración o carga inicial fallida.' }
            & $Php artisan db:seed --class=DemoV3Seeder --force
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear el escenario.' }
        }
        Asistencia {
            $env:RSU_DEMO_ALL_PRESENT = '1'
            & $Php artisan db:seed --class=DemoV3Seeder --force
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo completar la asistencia.' }
        }
        Iniciar {
            if (Test-Path $Estado) { throw 'Detén primero la instancia registrada o comprueba su estado.' }
            $ejecutable = (Get-Command $Php -CommandType Application -ErrorAction Stop).Source
            if ($ejecutable -notlike '*.exe') { throw 'Indica el ejecutable php.exe mediante -Php.' }
            $proceso = Start-Process -FilePath $ejecutable -WorkingDirectory (Join-Path $Repo 'public') -WindowStyle Hidden -PassThru -ArgumentList '-S',"${Escucha}:$Puerto",'../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
            $proceso.Id | Set-Content $Estado
            for ($intento = 0; $intento -lt 10; $intento++) {
                try {
                    $salud = Invoke-RestMethod "${env:APP_URL}/api/v1/salud" -TimeoutSec 2
                    if ($salud.version -eq 3) { Write-Output $env:APP_URL; return }
                } catch { Start-Sleep -Milliseconds 250 }
            }
            throw 'El proceso se inició, pero la comprobación HTTP no respondió.'
        }
        Detener {
            if (!(Test-Path $Estado)) { return }
            $pidDemo = [int](Get-Content $Estado)
            $proceso = Get-CimInstance Win32_Process -Filter "ProcessId=$pidDemo"
            if ($proceso) {
                if ($proceso.Name -ne 'php.exe' -or $proceso.CommandLine -notlike "*-S ${Escucha}:$Puerto*" -or $proceso.CommandLine -notlike '*Foundation/resources/server.php*') {
                    throw 'El PID guardado no corresponde al servidor PHP y puerto del ensayo.'
                }
                Stop-Process -Id $pidDemo
            }
            Remove-Item -LiteralPath $Estado
        }
        Respaldar {
            $env:MYSQL_PWD = $env:DB_PASSWORD
            try {
                $archivo = Join-Path $Repo ("storage/logs/$Base-" + (Get-Date -Format 'yyyyMMddHHmmss') + '.sql')
                & $MysqlDump "--host=$env:DB_HOST" "--port=$env:DB_PORT" "--user=$env:DB_USERNAME" --single-transaction --no-tablespaces "--result-file=$archivo" $Base
                if ($LASTEXITCODE -ne 0) { throw 'Falló el respaldo SQL.' }
                Write-Output $archivo
            } finally { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue }
        }
    }
} finally { Pop-Location }
