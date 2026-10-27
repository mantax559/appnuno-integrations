# AppNuno Integrations

Installable integrations for adding AppNuno's native navigation to commerce platforms.

## Available downloads

- [OpenCart 2.3.x — download OCMOD](downloads/opencart/2.3.x/appnuno-opencart-2.3.x.ocmod.zip) · [installation and compatibility](downloads/opencart/2.3.x/README.md)
- PrestaShop — coming soon.

The OpenCart package is currently verified against OpenCart 2.3.0.2; the `2.3.x` label groups the release by its compatible series and does not claim testing against every 2.3.x build. The module release version is maintained separately from the OpenCart version.

## Maintainers

The platform source and build tools are kept outside `downloads/`:

- OpenCart source: `src/opencart/2.3.0.2`
- OpenCart build and validation tools: `tools/opencart/2.3.0.2`
- Hosted browser SDK: maintained separately in `appnuno-web/public/sdk/v1/appnuno.js` and served from `https://appnuno.com/sdk/v1/appnuno.js`

Build and validate the OpenCart release from the repository root:

```text
php tools/opencart/2.3.0.2/tests/config-validation.php
php tools/opencart/2.3.0.2/build-package.php
php tools/opencart/2.3.0.2/tests/package-validation.php
```

The package tools require PHP's `ZipArchive` extension. The repository does not include OpenCart core, so installation, theme, and event behavior must be tested against a real OpenCart 2.3.0.2 store before production use.
