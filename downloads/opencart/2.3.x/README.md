# AppNuno for OpenCart 2.3.x

The current package is verified against OpenCart 2.3.0.2. The series label is used for the download location; other OpenCart 2.3.x releases have not yet been independently verified.

## Install

Upload `appnuno-opencart-2.3.x.ocmod.zip` from **Extensions → Extension Installer**. For an existing earlier installation, upload this updated archive over the old module files without uninstalling, then refresh modifications and reload the module settings page. This release fixes saving when the browser omits the hidden configuration field and adds translated labels and destination selectors. For a fresh installation, open **Extensions → Extensions → Modules**, install **AppNuno Native Navigation**, and configure it.

The OCMOD modification adds a same-origin bootstrap script to the shared catalog header. That endpoint emits page-specific state without cache and loads the hosted SDK from `https://appnuno.com/sdk/v1/appnuno.js`. Uninstalling removes the module event and settings. To upgrade, upload the newer archive over the existing files and keep the module installed; do not uninstall first because uninstalling deletes module settings. Refresh modifications after upload. No database schema changes are used.

## Navigation settings

The module provides admin controls for the fallback tab set, exact OpenCart route overrides, the cart badge tab, back-button placement, glass appearance and light/dark navigation colors. A route such as `checkout/cart` can publish a different full set from `common/home`. Keep tab IDs stable when the same destination occurs in multiple sets; the selected cart tab receives the confirmed cart quantity. The module stores these choices as validated JSON internally; administrators do not need to edit JSON manually.

Each tab has a label input for every enabled OpenCart language, prefixed by its language flag. The storefront sends the translation matching its current language, falling back to the first configured translation when a label is missing. Destinations can be selected from built-in store routes (home, special offers, account, login, register, cart, checkout), available information pages and categories, or entered as a custom local path. Custom paths can be set independently for every installed language, including disabled languages, with the current language’s path published on the storefront. Route overrides offer common OpenCart routes plus a custom route field.

Navigation and glass settings start with the native-contract defaults. Navigation colors and glass tint use color pickers; the tint opacity control preserves the native 0.02 alpha default.

```json
{
  "cartTabId": "cart",
  "defaultTabs": [
    { "id": "home", "label": "Home", "url": "/", "icon": "home-outline", "activeIcon": "home" },
    { "id": "cart", "label": "Cart", "url": "/index.php?route=checkout/cart", "icon": "cart-outline", "activeIcon": "cart" }
  ],
  "routes": {
    "checkout/cart": [
      { "id": "home", "label": "Home", "url": "/", "icon": "home-outline", "activeIcon": "home" },
      { "id": "cart", "label": "Bag", "url": "/index.php?route=checkout/cart", "icon": "cart-outline", "activeIcon": "cart" },
      { "id": "account", "label": "Account", "url": "/index.php?route=account/account", "icon": "account-outline", "activeIcon": "account" }
    ]
  },
  "back": { "enabled": true, "placement": "separate" }
}
```

Tab URLs must be local paths beginning with `/`; external origins and protocol-relative URLs are rejected. Up to 8 tabs per set and 30 route overrides are supported. Icons use Material Design Icons glyph names available in the AppNuno native font. Optional `back`, `glass`, and `colors` values are forwarded as shared navigation settings and validated against the native contract.

## Store state and behavior

- Initial state is collected server-side from OpenCart's cart and customer objects.
- Following stock cart add/edit/remove and account login/logout/register/edit AJAX requests, the SDK reads authoritative state from the same-origin, private no-store module endpoint. Failed requests do not publish attempted quantities.
- Customer identity is the OpenCart customer ID as a string under guard `customer`. Logout sends the same ID with a signed-out state. Email, names, addresses, session IDs, credentials, order data, device identifiers and push tokens are not included.
- A custom theme or cart extension can trigger `appnuno:refresh` on `document` after a confirmed change to refresh state.
- Cart and tab data are not sent to an AppNuno API. Native device enrollment and push identity delivery remain part of the existing native app flow.

This integration targets OpenCart 2.3.0.2 MVC routes. The repository has no OpenCart core fixture, so installation/event/theme behavior must be tested against the store's actual OpenCart and PHP versions. Themes that replace the shared header controller or bypass standard AJAX routes need additional integration testing.

## SDK upgrades

The SDK source and public file are maintained in `appnuno-web/public/sdk/v1/appnuno.js`. Compatible fixes can be deployed at `/sdk/v1/`; incompatible publisher changes require a new versioned URL and an updated module release.
