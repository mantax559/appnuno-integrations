# AppNuno for PrestaShop 8.x

## Installation

1. In PrestaShop admin, go to **Modules → Module Manager → Upload a module** and upload `appnuno-prestashop-8.x.zip`.
2. Install the module. It adds an **AppNuno** item to the admin menu.
3. Open **AppNuno** and configure the navigation. **Configure** on the module row opens the same screen.

The module stores its settings in `module_appnuno_status` and `module_appnuno_config`. Deleting the module removes both settings and the admin menu item.

## Requirements

- PrestaShop 8.x
- PHP 7.2.5 or newer

## Update

1. Back up your store files and database.
2. Upload the new `appnuno-prestashop-8.x.zip` from **Modules → Module Manager → Upload a module** and replace the current version when PrestaShop asks.
3. Open **AppNuno** and confirm the saved navigation.

Do not delete the module before updating. Deleting it removes the saved settings.

Documentation: https://appnuno.com/prestashop
