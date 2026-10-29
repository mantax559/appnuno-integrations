# AppNuno for OpenCart 4.x

## Installation

1. In OpenCart admin, go to **Extensions → Extension Installer** and upload `appnuno.ocmod.zip`. The file name is the extension code, so keep this name.
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
