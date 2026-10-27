# AppNuno Integrations

OpenCart and future platform integrations for the AppNuno native WebView contract. The hosted browser SDK is maintained in `appnuno-web` at `public/sdk/v1/appnuno.js` and served from `https://appnuno.com/sdk/v1/appnuno.js`.

## OpenCart 2.3.0.2

- Installable release archive: `opencart/2.3.0.2/appnuno-2.3.0.2.ocmod.zip`
- Module source: `src/opencart/2.3.0.2`
- Maintainer tools and checks: `tools/opencart/2.3.0.2`
- Installation, module settings and compatibility: `docs/opencart-2.3.0.2.md`

The release folder contains only the archive intended for store owners. Source files, package creation scripts and tests live elsewhere in the repository and are not included in that ZIP. Store administrators configure tab sets and native-bar appearance through module controls rather than editing raw JSON.

Build and check the release from the repository root:

```text
php tools/opencart/2.3.0.2/tests/config-validation.php
php tools/opencart/2.3.0.2/build-package.php
php tools/opencart/2.3.0.2/tests/package-validation.php
```

The package tools require PHP's `ZipArchive` extension. The module itself does not require Python. The repository does not include OpenCart core, so test against a real OpenCart 2.3.0.2 fixture before production release.
