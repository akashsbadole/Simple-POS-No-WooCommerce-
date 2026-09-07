# Simple POS add-ons

All add-ons ship **inside** the Simple POS plugin and are **free**. There is
nothing to install — each folder here (`<slug>/<slug>.php`) is a module the
core loads automatically when enabled.

- Open **POS → Add-ons** in wp-admin.
- Click **Enable / Disable** — the change applies on the next page load.
- Disabled add-ons stay listed so you can re-enable them any time.

The enabled flags are stored in the `simple_pos_addons_enabled` option.

## Building distributable zips (optional)

Only needed if you want to run one of these add-ons on a **different**
WordPress site where the Simple POS core is installed separately:

    php scripts/build-addon-zips.php

The zips land in `addons/dist/<slug>.zip` with the layout WordPress expects
(``<slug>/<slug>.php`` at the zip root), ready for
Plugins → Add New → Upload Plugin.
