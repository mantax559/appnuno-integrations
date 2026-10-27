# AppNuno for OpenCart 2.3.0.2

## Install

Upload `opencart/2.3.0.2/appnuno-2.3.0.2.ocmod.zip` from **Extensions → Extension Installer**. Refresh modifications, then open **Extensions → Extensions → Modules**, install **AppNuno Native Navigation**, and configure it.

The extension uses OpenCart's pre-view event to add a same-origin bootstrap script to the shared catalog header. That endpoint emits page-specific state without cache and loads the hosted SDK from `https://appnuno.com/sdk/v1/appnuno.js`. Uninstalling removes the module event and settings. To upgrade, upload the newer archive over the existing files and keep the module installed; do not uninstall first because uninstalling deletes module settings. Refresh modifications after upload. No database schema changes are used.

## Navigation settings

The module provides admin controls for the fallback tab set, exact OpenCart route overrides, the cart badge tab, back-button placement, glass appearance and light/dark navigation colors. A route such as `checkout/cart` can publish a different full set from `common/home`. Keep tab IDs stable when the same destination occurs in multiple sets; the selected cart tab receives the confirmed cart quantity. The module stores these choices as validated JSON internally; administrators do not need to edit JSON manually.

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
