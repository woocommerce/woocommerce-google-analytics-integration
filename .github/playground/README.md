# WordPress Playground previews of pull request builds

The `test-the-build.yml` workflow adds a "Test the build" comment to each pull request. Its WordPress Playground links open the build of that pull request in the browser, with no development environment. The files in this folder set up that store. They are not part of the plugin zip (`.github` is `export-ignore`).

## How a link works

1. `checks.yml` builds the plugin zip, then calls `test-the-build.yml`.
2. The workflow uploads the zip and the PHP files of this folder as one artifact, `playground-<run ID>`, kept for 14 days.
3. Each link is `https://playground.wordpress.net/#` followed by a Blueprint. The Blueprint installs WooCommerce, downloads the artifact through Playground's artifact proxy, installs the zip and runs `setup.php`.

The run ID in the artifact name ties a link to one run: a link never mixes the zip of one build with the setup files of another.

## What the files do

- `setup.php` skips the WooCommerce setup wizard, opens the store to visitors, saves the placeholder tracking ID `G-PREVIEW`, turns on cash on delivery and a flat rate, adds three products, and copies `event-log.php` to `mu-plugins`.
- `event-log.php` adds a panel to the storefront that lists the gtag commands of the visit (`config`, `consent`, `event` and their parameters) as the plugin pushes them to `window.dataLayer`. The list lasts for the browser session.

## The two links

- **Store, as a visitor.** Not logged in, opens `/shop/`. This is the one that shows events.
- **Settings, as an administrator.** Opens WooCommerce > Settings > Integrations > Google Analytics. The plugin prints no tracking code for administrators, so the storefront shows no events until you log out. Log in again at `/wp-admin` with `admin` / `password`.

## Limits

- `G-PREVIEW` is not a Google Analytics property. Google's gtag.js loads, and no data reaches a report. To see data in Google Analytics, save the ID of a property you own in the settings.
- Every link starts a new temporary store in the browser tab. Nothing is kept after the tab is closed.
- Only WooCommerce and this plugin are installed. Extensions the plugin integrates with, such as the WP Consent API, Product Bundles and Composite Products, are not.
- Pull requests from forks and from Dependabot get no comment: their workflow runs have a read-only token and no secrets.
- Playground's artifact proxy serves the `wordpress`, `automattic` and `woocommerce` organizations, and lists the first 30 workflows of a repository to find `Checks` by name.

## Jetpack Beta and Jurassic Ninja

The comment also offers these two when the repository secret `JETPACK_BETA_BUILDER_SECRET` is set and the Jetpack Beta Builder accepts the build. Both install from the Jetpack Beta Builder, which takes builds only from a plugin registered with it. Without the secret, the workflow skips those steps and the comment has the Playground links only.

## Run a preview locally

Build the zip, then run the same steps against it with the Playground CLI (Node 20.18 or newer):

```sh
npm run build
mkdir -p /tmp/ga4w-preview/playground
cp woocommerce-google-analytics-integration.zip /tmp/ga4w-preview/
cp .github/playground/*.php /tmp/ga4w-preview/playground/
cat > /tmp/ga4w-preview/blueprint.json <<'EOF'
{
  "preferredVersions": { "php": "8.3", "wp": "latest" },
  "features": { "networking": true },
  "steps": [
    { "step": "installPlugin", "pluginData": { "resource": "wordpress.org/plugins", "slug": "woocommerce" } },
    { "step": "installPlugin", "pluginData": { "resource": "vfs", "path": "/tmp/pr/woocommerce-google-analytics-integration.zip" } },
    { "step": "runPHP", "code": "<?php require '/wordpress/wp-load.php'; require '/tmp/pr/playground/setup.php';" }
  ]
}
EOF
npx @wp-playground/cli@3.1.64 server --blueprint=/tmp/ga4w-preview/blueprint.json --mount=/tmp/ga4w-preview:/tmp/pr
```

Open `http://127.0.0.1:9400/shop/`.
