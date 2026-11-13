# AppNuno for OpenCart 4.x

## Package name

OpenCart 4 derives the extension code from the uploaded archive filename: `appnuno.ocmod.zip` installs into `extension/appnuno/`. That matches the AppNuno namespaces and routes. Older OpenCart lines keep versioned names such as `appnuno-opencart-3.x.ocmod.zip` because their OCMOD installer does not use the ZIP basename as the extension directory.

## Installation

1. In OpenCart admin, go to **Extensions → Extension Installer** and upload `appnuno.ocmod.zip`. Do not rename the file before upload; OpenCart 4 maps `extension/{filename}` from the ZIP basename.
2. Click **Install** beside the uploaded AppNuno package.
3. Go to **Extensions → Extensions** and choose **Modules**.
4. Find **AppNuno Native Navigation**, click **Install**, then **Edit** to configure the module.

## Permissions

Installing the module grants **Access** and **Modify** permissions to the user group performing the installation. To allow another admin group to manage it, go to **System → Users → User Groups**, edit that group, and enable `extension/appnuno/module/appnuno` under both **Access Permission** and **Modify Permission**.

## Update

1. Back up your store files and database.
2. Upload the new `appnuno.ocmod.zip` in **Extensions → Extension Installer** and install the package again.
3. Open **Extensions → Extensions → Modules**, find AppNuno, and confirm its settings and status.

Do not uninstall the existing module before updating; uninstalling deletes its saved settings.
