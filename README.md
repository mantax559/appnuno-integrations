# AppNuno Integrations

| Platform | Version | Instruction | Download |
| --- | --- | --- | --- |
| WooCommerce | 11.x | [Instruction](downloads/woocommerce/11.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/11.x/appnuno-woocommerce-11.x.zip) |
| WooCommerce | 10.x | [Instruction](downloads/woocommerce/10.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/10.x/appnuno-woocommerce-10.x.zip) |
| WooCommerce | 9.x | [Instruction](downloads/woocommerce/9.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/9.x/appnuno-woocommerce-9.x.zip) |
| WooCommerce | 8.x | [Instruction](downloads/woocommerce/8.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/8.x/appnuno-woocommerce-8.x.zip) |
| WooCommerce | 7.x | [Instruction](downloads/woocommerce/7.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/7.x/appnuno-woocommerce-7.x.zip) |
| WooCommerce | 6.x | [Instruction](downloads/woocommerce/6.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/6.x/appnuno-woocommerce-6.x.zip) |
| WooCommerce | 5.x | [Instruction](downloads/woocommerce/5.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/5.x/appnuno-woocommerce-5.x.zip) |
| WooCommerce | 4.x | [Instruction](downloads/woocommerce/4.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/4.x/appnuno-woocommerce-4.x.zip) |
| WooCommerce | 3.x | [Instruction](downloads/woocommerce/3.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/3.x/appnuno-woocommerce-3.x.zip) |
| WooCommerce | 2.x | [Instruction](downloads/woocommerce/2.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/2.x/appnuno-woocommerce-2.x.zip) |
| WooCommerce | 1.x | [Instruction](downloads/woocommerce/1.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/woocommerce/1.x/appnuno-woocommerce-1.x.zip) |
| PrestaShop | 8.x | [Instruction](downloads/prestashop/8.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/prestashop/8.x/appnuno-prestashop-8.x.zip) |
| PrestaShop | 9.x | [Instruction](downloads/prestashop/9.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/prestashop/9.x/appnuno-prestashop-9.x.zip) |
| PrestaShop | 1.7.x | Instruction in preparation | — |
| PrestaShop | 1.6.x | Instruction in preparation | — |
| PrestaShop | 1.5.x and older | Not supported | — |
| OpenCart | 4.x | [Instruction](downloads/opencart/4.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/4.x/appnuno.ocmod.zip) |
| OpenCart | 3.x | [Instruction](downloads/opencart/3.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/3.x/appnuno-opencart-3.x.ocmod.zip) |
| OpenCart | 2.3.x | [Instruction](downloads/opencart/2.3.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/2.3.x/appnuno-opencart-2.3.x.ocmod.zip) |
| OpenCart | 2.2.x | [Instruction](downloads/opencart/2.2.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/2.2.x/appnuno-opencart-2.2.x.ocmod.zip) |
| OpenCart | 2.1.x | [Instruction](downloads/opencart/2.1.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/2.1.x/appnuno-opencart-2.1.x.ocmod.zip) |
| OpenCart | 2.0.x | [Instruction](downloads/opencart/2.0.x/README.md) | [Download](https://github.com/mantax559/appnuno-integrations/raw/development/downloads/opencart/2.0.x/appnuno-opencart-2.0.x.ocmod.zip) |
| OpenCart | 1.5.x and older | Not supported | — |

## Generate OpenCart ZIP packages

Run this command from the repository root. PHP must have the `zip` extension enabled. It builds or replaces ZIP packages for all supported OpenCart versions under `downloads/opencart/`.

```powershell
php tools/opencart/build-package.php
```

## Generate the PrestaShop packages

Run these commands from the repository root. PHP must have the `zip` extension enabled. They build the PrestaShop 8.x and 9.x packages.

```powershell
php tools/prestashop/build-package.php 8
php tools/prestashop/build-package.php 9
```

## Run PrestaShop installation tests

PrestaShop 8.2 and 9.0 are installed against MySQL and tested with the AppNuno module, including browser checks. Docker must be installed.

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\prestashop\tests\run-live.ps1 -PrestaShopTag 8.2-apache
powershell -ExecutionPolicy Bypass -File .\tools\prestashop\tests\run-live.ps1 -PrestaShopTag 9.0-apache
```

## Generate the WooCommerce packages

Run these commands from the repository root. PHP must have the `zip` extension enabled. They build the WooCommerce 1.x through 11.x packages.

```powershell
php tools/wordpress/build-package.php 1
php tools/wordpress/build-package.php 2
php tools/wordpress/build-package.php 3
php tools/wordpress/build-package.php 4
php tools/wordpress/build-package.php 5
php tools/wordpress/build-package.php 6
php tools/wordpress/build-package.php 7
php tools/wordpress/build-package.php 8
php tools/wordpress/build-package.php 9
php tools/wordpress/build-package.php 10
php tools/wordpress/build-package.php
```

## Generate WordPress and OpenCart workflows

Run this script from the repository root to regenerate the WordPress/WooCommerce and OpenCart GitHub Actions workflow files. The checked-in PrestaShop workflow is not generated by this script and is preserved:

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\update-workflows.ps1
```

## OpenCart workflow secret

Open [Settings → Secrets and variables → Actions → Secrets](https://github.com/mantax559/appnuno-integrations/settings/secrets/actions). Under **Repository secrets**, add:

- `APPNUNO_PLATFORM_REPOSITORY_TOKEN`: create a fine-grained token named `RO for appnuno-platform` at [GitHub token settings](https://github.com/settings/personal-access-tokens), select only `mantax559/appnuno-platform`, and grant **Contents: Read-only** permissions.
