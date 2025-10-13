# Mini FAIR Repo
The Mini FAIR plugin transforms your site into a [FAIR Repository](https://github.com/fairpm/fair-protocol), allowing you to serve packages directly from your own infrastructure into the FAIR system.
## Design Goals
Mini FAIR is designed to allow plugin and theme vendors to host their own FAIR repository easily, integrating with tools they’re already using. Mini FAIR is built for small scale hosting for a few packages, not for general use as a mass-hosting repository.

We aim to make it possible for everyone to run Mini FAIR, with a focus on ease of use and integration with existing tooling.
## Requirements
- PHP 8.3 or higher
- WordPress 6.4 or higher
- [Persistant Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/#persistent-caching)
- [Composer](https://getcomposer.org/) for dependency management
- [Git Updater](https://git-updater.com/) plugin (for integration)
## Local Installation & Development Setup
### 1. Clone the Repository
```bash
git clone https://github.com/fairpm/mini-fair-repo.git
cd mini-fair-repo
```
### 2. Install Dependencies
Install PHP dependencies using Composer:
```bash
composer install
```
This will install the required packages:
- `yocto/yoclib-multibase` - For multibase encoding
- `simplito/elliptic-php` - For elliptic curve cryptography
- `spomky-labs/cbor-php` - For CBOR data format handling
### 3. WordPress Setup
#### Option A: Local WordPress Installation
1. Set up a local WordPress environment using your preferred method:
   - [Local by Flywheel](https://localwp.com/)
   - [XAMPP](https://www.apachefriends.org/)
   - [MAMP](https://www.mamp.info/)
   - [Docker](https://github.com/docker/awesome-compose/tree/master/wordpress-mysql)
2. Copy the Mini FAIR plugin to your WordPress plugins directory:
   ```bash
   cp -r /path/to/mini-fair-repo /path/to/wordpress/wp-content/plugins/mini-fair
   ```
3. Activate the plugin through the WordPress admin interface or via WP-CLI:
   ```bash
   wp plugin activate mini-fair
   ```
#### Option B: Using WP-CLI for Development
If you have WP-CLI installed, you can quickly set up a development environment:
```bash
# Create a new WordPress installation
wp core download
wp config create --dbname=minifair_dev --dbuser=root --dbpass=password
wp core install --url=http://localhost --title=“Mini FAIR Dev” --admin_user=admin --admin_password=password --admin_email=admin@example.com
# Create a symlink to the plugin
ln -s /path/to/mini-fair-repo wp-content/plugins/mini-fair
# Activate the plugin
wp plugin activate mini-fair
```
### 4. Install Git Updater
Mini FAIR requires Git Updater for package management:
```bash
# Download and install Git Updater
wp plugin install https://github.com/afragen/git-updater/archive/refs/heads/master.zip --activate
```
### 5. Development Environment Configuration
#### Enable WordPress Debug Mode
Add these lines to your `wp-config.php` file for development:
```php
define(‘WP_DEBUG’, true);
define(‘WP_DEBUG_LOG’, true);
define(‘WP_DEBUG_DISPLAY’, false);
define(‘SCRIPT_DEBUG’, true);
```
#### Enable WP-CLI Commands
Mini FAIR includes WP-CLI commands for PLC management. Ensure WP-CLI is installed and available in your development environment.
### 6. Verify Installation
1. Check that the plugin is active:
   ```bash
   wp plugin list
   ```
2. Verify the REST API endpoint is working:
   ```bash
   curl http://your-site.local/wp-json/minifair/v1/packages
   ```
3. Access the Mini FAIR admin page at:
   `http://your-site.local/wp-admin/admin.php?page=minifair`
## Development Workflow
### Project Structure
```
mini-fair-repo/
├── plugin.php              # Main plugin file
├── composer.json           # PHP dependencies
├── inc/                    # Core functionality
│   ├── namespace.php       # Main bootstrap
│   ├── admin/              # Admin interface
│   ├── api/                # REST API endpoints
│   ├── git-updater/        # Git Updater integration
│   ├── keys/               # Cryptographic key management
│   └── plc/                # PLC (Public Ledger of Credentials) functionality
└── vendor/                 # Composer dependencies
```
### Key Components
- **PLC Integration**: Handles Decentralized Identifiers (DIDs) for packages
- **Git Updater Provider**: Integrates with Git Updater for package management
- **REST API**: Provides endpoints for package metadata (`/wp-json/minifair/v1/packages/{did}`)
- **Admin Interface**: WordPress dashboard integration for managing DIDs and packages
### Making Changes
1. **PHP Code**: Edit files in the `inc/` directory
2. **Dependencies**: Update `composer.json` and run `composer install`
3. **Testing**: Use WP-CLI commands for testing PLC functionality:
   ```bash
   wp plc --help
   ```
### Debugging
- Check WordPress debug logs in `wp-content/debug.log`
- Use `error_log()` for debugging output
- Monitor the REST API responses for package metadata
## Using Mini FAIR
### Installation for End Users
Mini FAIR currently supports integration with [Git Updater](https://git-updater.com/), with planned support for other tools such as EDD coming soon.
To use Mini FAIR, install the latest version of plugin as well as a supported tool - that is, Git Updater.
### Creating a DID for your package
Once you’ve got Mini FAIR installed, you’ll need to create a DID for your package if you don’t already have one. Head to the Mini FAIR page in your WordPress dashboard, and click “Create New PLC DID…”
This will begin the process of creating a new PLC DID for your package - a globally-unique ID identifying your package, which you can take with you even if you change repositories in the future.
It will also create two cryptographic keys: a “rotation” key, used to manage the DID’s details, as well as a “verification key”, which is used to sign releases. These keys will be stored in your WordPress database - **don’t lose them, as you can’t recover your DID if you lose your rotation key!**
Once your DID has been created, it’ll be published [in the global PLC Directory](https://web.plc.directory/resolve) if you want to double check it. You can also sync changes to the directory via the Dashboard if your site’s URL changes, or (coming soon!) to rotate your keys.
### Distributing your package with your DID
To start distributing your package, you’ll need to add your DID to your plugin or theme.
For plugins, add a `Plugin ID:` header to your plugin’s PHP file:
```php
<?php
/**
 * Plugin Name: My Example Plugin
 * Plugin ID: did:plc:abcd1234dcba
 * ...
```
For themes, add a `Theme ID:` header to your theme’s `style.css` file:
```css
/*
Theme Name: My Example Theme
Theme ID: did:plc:abcd1234dcba
Theme URI: Https://...
```
Ensure your plugin or theme is set up correctly with [Git Updater](https://git-updater.com/knowledge-base/), and that you’re using the same site as you registered your DID with.
Once that’s done, you’re ready to go - your package should integrate automatically with the FAIR system! You can use the [FAIR Plugin](https://github.com/fairpm/fair-plugin) to install your package directly by ID, and once it’s been installed once, discovery aggregators will start to list it.
You can double-check your packages by checking the REST API endpoint at `/wp-json/minifair/v1/packages/{did}` (replace `{did}` with your package’s DID).
## Contributing
We welcome contributions! Please see [the TSC repository](https://github.com/fairpm/tsc) for contribution guidelines, including the code of conduct.
### Development Setup for Contributors
1. Fork the repository
2. Follow the local installation steps above
3. Create a feature branch: `git checkout -b feature/your-feature-name`
4. Make your changes and test thoroughly
5. Submit a pull request
## Troubleshooting
### Common Issues
- **Composer dependencies not loading**: Ensure you’ve run `composer install` after cloning
- **Plugin not appearing**: Check that the plugin directory is named correctly and placed in `wp-content/plugins/`
- **REST API errors**: Verify WordPress permalinks are enabled and working
- **PLC command not found**: Ensure WP-CLI is properly installed and the plugin is activated
### Getting Help
- Check the [FAIR Protocol documentation](https://github.com/fairpm/fair-protocol)
- Review the [Git Updater documentation](https://git-updater.com/)
- Open an issue on this repository for bug reports or feature requests
## License
Copyright 2025 contributors. Licensed under the GNU General Public License, v2 or later.
Contributions are welcomed from all! See [the TSC repository](https://github.com/fairpm/tsc) for contribution guidelines, including the code of conduct.
