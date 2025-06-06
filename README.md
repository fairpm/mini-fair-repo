# Mini FAIR Repo

The Mini FAIR plugin transforms your site into a [FAIR Repository](https://github.com/fairpm/fair-protocol), allowing you to serve packages directly from your own infrastructure into the FAIR system.


## Design Goals

Mini FAIR is designed to allow plugin and theme vendors to host their own FAIR repository easily, integrating with tools they're already using. Mini FAIR is built for small scale hosting for a few packages, not for general use as a mass-hosting repository.

We aim to make it possible for everyone to run Mini FAIR, with a focus on ease of use and integration with existing tooling.


## Using Mini FAIR

### Installation

Mini FAIR currently supports integration with [Git Updater](https://git-updater.com/), with planned support for other tools such as EDD coming soon.

To use Mini FAIR, install the latest version of plugin as well as a supported tool - that is, Git Updater.


### Creating a DID for your package

Once you've got Mini FAIR installed, you'll need to create a DID for your package if you don't already have one. Head to the Mini FAIR page in your WordPress dashboard, and click "Create New PLC DID…"

This will begin the process of creating a new PLC DID for your package - a globally-unique ID identifying your package, which you can take with you even if you change repositories in the future.

It will also create two cryptographic keys: a "rotation" key, used to manage the DID's details, as well as a "verification key", which is used to sign releases. These keys will be stored in your WordPress database - **don't lose them, as you can't recover your DID if you lose your rotation key!**

Once your DID has been created, it'll be published [in the global PLC Directory](https://web.plc.directory/resolve) if you want to double check it. You can also sync changes to the directory via the Dashboard if your site's URL changes, or (coming soon!) to rotate your keys.


### Distributing your package with your DID

To start distributing your package, you'll need to add your DID to your plugin or theme.

For plugins, add a `Plugin ID:` header to your plugin's PHP file:

```php
<?php
/**
 * Plugin Name: My Example Plugin
 * Plugin ID: did:plc:abcd1234dcba
 * ...
```

For themes, add a `Theme ID:` header to your theme's `style.css` file:

```css
/*
Theme Name: My Example Theme
Theme ID: did:plc:abcd1234dcba
Theme URI: Https://...
```

Ensure your plugin or theme is set up correctly with Git Updater, and that you're using the same site as you registered your DID with.

Once that's done, you're ready to go - your package should integrate automatically with the FAIR system! You can use the [FAIR Plugin](https://github.com/fairpm/fair-plugin) to install your package directly by ID, and once it's been installed once, discovery aggregators will start to list it.

You can double-check your packages by checking the REST API endpoint at `/wp-json/minifair/v1/packages/{did}` (replace `{did}` di your package's DID).


## License

Copyright 2025 contributors. Licensed under the GNU General Public License, v2 or later.

Contributions are welcomed from all! See [the TSC repository](https://github.com/fairpm/tsc) for contribution guidelines, including the code of conduct.
