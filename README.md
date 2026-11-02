# AppNuno Integrations

| Platform | Version | Instruction | Download | Tested |
| --- | --- | --- | --- | --- |
| OpenCart | 4.x | [Instruction](downloads/opencart/4.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/4.x/appnuno-opencart-4.x.ocmod.zip) | 4.0.0.0, 4.0.1.0, 4.0.1.1, 4.0.2.0, 4.0.2.1, 4.0.2.2, 4.0.2.3, 4.1.0.0, 4.1.0.1, 4.1.0.2, 4.1.0.3 and 4.1.0.4 |
| OpenCart | 3.x | [Instruction](downloads/opencart/3.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/3.x/appnuno-opencart-3.x.ocmod.zip) | 3.0.0.0, 3.0.0.2, 3.0.1.1, 3.0.1.2, 3.0.2.0, 3.0.3.0, 3.0.3.1, 3.0.3.2, 3.0.3.3, 3.0.3.4, 3.0.3.5, 3.0.3.6, 3.0.3.7, 3.0.3.8, 3.0.3.9, 3.0.4.0, 3.0.4.1, 3.0.5.0 and 3.0.5.1 |
| OpenCart | 2.3.x | [Instruction](downloads/opencart/2.3.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/2.3.x/appnuno-opencart-2.3.x.ocmod.zip) | 2.3.0.0, 2.3.0.1 and 2.3.0.2 |
| OpenCart | 2.2.x | [Instruction](downloads/opencart/2.2.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/2.2.x/appnuno-opencart-2.2.x.ocmod.zip) | 2.2.0.0 |
| OpenCart | 2.1.x | [Instruction](downloads/opencart/2.1.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/2.1.x/appnuno-opencart-2.1.x.ocmod.zip) | 2.1.0.0, 2.1.0.1 and 2.1.0.2 |
| OpenCart | 2.0.x | [Instruction](downloads/opencart/2.0.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/2.0.x/appnuno-opencart-2.0.x.ocmod.zip) | 2.0.0.0, 2.0.1.0, 2.0.1.1, 2.0.2.0, 2.0.3.0 and 2.0.3.1 |

## Local OpenCart integration tests with Docker

Prerequisites: Docker Desktop with Docker Compose, and sibling `appnuno-platform` checkout containing `public/sdk/v1/appnuno.js`.

From the repository root, run the full one-shot OpenCart 2.3.x build, validation, installation and browser integration test:

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\opencart\tests\run-docker.ps1
```

The default version is `2.3.0.2`. To test another supported 2.3.x version, pass it explicitly:

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\opencart\tests\run-docker.ps1 -OpenCartVersion "2.3.0.1"
```

The one-shot runner builds the package, runs PHP validation checks, installs OpenCart with MySQL, uploads the extension through FTP, and runs the Playwright integration scenarios. It removes its containers, network and temporary database when finished. Screenshots, video, trace and the PHP server log remain under `artifacts/opencart-docker/<version>/`.

To keep a disposable store running for manual browser inspection, run these commands in PowerShell from the repository root:

```powershell
$compose = @("-f", ".\tools\opencart\tests\docker-compose.yml", "-f", ".\tools\opencart\tests\docker-compose.live.yml", "-p", "appnuno-opencart-live")
docker compose @compose build runner opencart-php
docker compose @compose run --rm live-init
docker compose @compose up -d opencart-php
```

Open `http://127.0.0.1:8080`; the installer starts with a fresh database. To remove the live store and its temporary database after inspection:

```powershell
docker compose @compose down --volumes --remove-orphans
```

The current local PHP image is 7.3 with the extensions required by OpenCart 2.3. The historical PHP 5.4 Docker image uses an obsolete Docker manifest format, so this local environment does not reproduce the exact PHP 5.4 runtime used by GitHub Actions.
