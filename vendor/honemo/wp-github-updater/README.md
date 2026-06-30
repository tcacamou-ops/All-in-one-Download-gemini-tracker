# WP GitHub Updater

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/wordpress-%3E%3D6.0-blue)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--3.0-green)](LICENSE)

A modern PHP library to automatically update WordPress plugins from public or private GitHub repositories.

## ✨ Features

- 🔒 **Secure**: Multiple token sources support (wp-config, environment variables, WP options)
- 🏷️ **Flexible**: Support for tags with or without `v` prefix (v1.0.0 or 1.0.0)
- 🎯 **Modern**: Strict PHP 8 typing, complete PHPDoc, fully tested
- ✅ **Tested**: Complete PHPUnit test suite with 15 tests
- 📦 **Zero config**: Works directly with public repositories, no token needed
- 🔐 **Private repos**: Full support with GitHub authentication
- 🌐 **URL parsing**: Accepts multiple GitHub URL formats (HTTPS, Git, SSH)
- 🔄 **Smart caching**: Respects WordPress transients to avoid API rate limits
- 🛡️ **Error handling**: Graceful degradation if GitHub API is unavailable
- 📝 **Standards compliant**: Follows WordPress Coding Standards and PSR-12

## 📋 Requirements

- PHP 7.4 or higher
- WordPress 6.0 or higher
- Composer

## 🚀 Installation

```bash
composer require honemo/wp-github-updater
```

## 📖 Usage

### Basic usage (public repository)

```php
<?php
use honemo\updater\Updater;

if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

    $updater = new Updater(
        __FILE__,                                      // Main plugin file
        'https://github.com/Honemo/wp-github-updater'  // Repository URL
    );
    
    $updater->init();
}
```

### Private repository (Recommended secure method)

#### Option 1: Constant in wp-config.php ⭐ (Recommended)

```php
// In wp-config.php (BEFORE require_once(ABSPATH . 'wp-settings.php'))
define( 'GITHUB_UPDATER_TOKEN', 'ghp_your_secret_token' );
```

```php
// In your plugin - NO TOKEN in clear text!
use honemo\updater\Updater;

if ( is_admin() ) {
    $updater = new Updater(
        __FILE__,
        'https://github.com/Honemo/my-private-plugin'
        // Token is automatically retrieved from wp-config.php
    );
    $updater->init();
}
```

### Supported URL formats

The updater accepts various GitHub URL formats:

```php
// Standard HTTPS URL
'https://github.com/username/repository'

// With .git extension
'https://github.com/username/repository.git'

// With trailing slash
'https://github.com/username/repository/'

// Without https://
'github.com/username/repository'

// Git SSH format (converted automatically)
'git@github.com:username/repository.git'
```

#### Option 2: Environment variable

```bash
# In .env (never versioned)
GITHUB_UPDATER_TOKEN=ghp_your_secret_token
```

The code remains the same - the updater automatically searches in environment variables.

#### Option 3: WordPress option

```php
// Via WordPress admin or in functions.php
update_option( 'github_updater_token', 'ghp_your_secret_token' );
```

### Token priority order

The updater searches for the token in this order:
1. Parameter passed to constructor
2. `GITHUB_UPDATER_TOKEN` constant (wp-config.php)
3. `GITHUB_UPDATER_TOKEN` environment variable
4. `github_updater_token` WordPress option

## � API Reference

### Constructor

```php
new Updater( string $file, string $repository_url, string $github_token = '' )
```

**Parameters:**

- `$file` (string, required): Absolute path to your main plugin file (typically `__FILE__`)
- `$repository_url` (string, required): GitHub repository URL. Accepts multiple formats:
  - `https://github.com/username/repository`
  - `https://github.com/username/repository.git`
  - `github.com/username/repository`
  - `git@github.com:username/repository.git`
- `$github_token` (string, optional): GitHub personal access token. If not provided, the updater will attempt to retrieve it from:
  - `GITHUB_UPDATER_TOKEN` constant
  - `GITHUB_UPDATER_TOKEN` environment variable
  - `github_updater_token` WordPress option

**Example:**

```php
$updater = new \honemo\updater\Updater(
    __FILE__,
    'https://github.com/tcacamou-ops/all-in-one-download'
);
$updater->init();
```

### Methods

#### `init(): void`

Initialize the updater by hooking into WordPress filters. Must be called after instantiation.

```php
$updater->init();
```

## �🔐 Generate a GitHub token

1. Go to GitHub → Settings → Developer Settings → Personal access tokens
2. Generate new token (classic)
3. Minimum required permissions:
   - ✅ `repo` (for private repositories)
   - ✅ `public_repo` (for public repositories only)
4. Copy the token and store it securely

## 📦 Creating a release

### Automated release (Recommended) 🤖

A GitHub Actions workflow is included to automatically create releases when you push a tag:

**Step 1:** Update the version in your plugin file
```php
/**
 * Version: 1.2.0
 */
```

**Step 2:** Commit your changes
```bash
git add .
git commit -m "Release version 1.2.0"
```

**Step 3:** Create and push a tag
```bash
git tag v1.2.0  # or simply 1.2.0
git push origin main
git push origin v1.2.0
```

**That's it!** The workflow will automatically:
- ✅ Build a distribution package (without dev dependencies)
- ✅ Generate a changelog from git commits
- ✅ Create a GitHub release with the package attached
- ✅ Upload the distributable ZIP file

The workflow supports both formats:
- Tags with prefix: `v1.0.0`, `v2.3.4`
- Tags without prefix: `1.0.0`, `2.3.4`

### Manual release

If you prefer to create releases manually:

1. Create a version tag:
   ```bash
   git tag v1.0.0  # or simply 1.0.0
   git push origin v1.0.0
   ```

2. Create a release on GitHub:
   - Go to "Releases" → "Create a new release"
   - Select the tag
   - Add release notes
   - Publish the release

3. The version in your main file must match:
   ```php
   /**
    * Version: 1.0.0
    */
   ```

### What gets included in the distribution?

The `.distignore` file controls what's excluded from releases:
- ❌ Development dependencies (`/vendor/` dev packages, `/tests/`)
- ❌ Configuration files (`phpcs.xml`, `phpstan.neon`)
- ❌ Git files (`.git`, `.github`, `.gitignore`)
- ✅ Production code (`/src/`)
- ✅ Composer autoloader
- ✅ README.md and LICENSE

## 📝 Complete example

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Version: 1.0.0
 * Plugin URI: https://github.com/Honemo/my-plugin
 * Description: An awesome plugin with auto-update from GitHub
 * Author: Honemo
 * Author URI: https://github.com/Honemo
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Composer autoloader
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

// Initialize updater (admin only)
if ( is_admin() ) {
    $updater = new \honemo\updater\Updater(
        __FILE__,
        'https://github.com/Honemo/my-plugin'
    );
    $updater->init();
}

// Rest of your code...
```

## 🐛 Troubleshooting

### Updates not showing up

**Check these points:**

1. **Version mismatch**: Ensure the version in your plugin header matches exactly with the GitHub release tag (without `v` prefix)
   ```php
   // Plugin header
   Version: 1.0.0
   
   // GitHub tag should be: v1.0.0 or 1.0.0
   ```

2. **Release published**: Make sure you've created and published a release on GitHub, not just a tag

3. **Token authentication**: For private repositories, verify your token has the correct permissions:
   - `repo` scope for private repositories
   - `public_repo` scope for public repositories

4. **Clear transients**: WordPress caches update information. Clear it with:
   ```php
   delete_site_transient( 'update_plugins' );
   ```

### Invalid repository URL error

If you get an `InvalidArgumentException`, ensure your URL:
- Points to `github.com` (not GitLab, Bitbucket, etc.)
- Contains both username and repository name
- Follows one of the supported formats

**Valid formats:**
```php
'https://github.com/username/repository'
'github.com/username/repository'
'git@github.com:username/repository.git'
```

**Invalid formats:**
```php
'https://gitlab.com/username/repository'  // ❌ Not GitHub
'https://github.com/username'              // ❌ Missing repository
'username/repository'                      // ❌ Missing domain
```

### Token not being recognized

Verify the token is accessible:

```php
// Add this temporarily to debug
if ( defined( 'GITHUB_UPDATER_TOKEN' ) ) {
    error_log( 'Token is defined in wp-config.php' );
}
```

Make sure the constant is defined **before** `wp-settings.php` is loaded in `wp-config.php`.


## 🛠️ Development

```bash
# Clone repository
git clone https://github.com/Honemo/wp-github-updater.git
cd wp-github-updater

# Install dependencies
composer install

# Run tests
composer test

# Check code quality
composer check:all
```

## 📄 License

GPL-3.0 License. See [LICENSE](LICENSE) for more details.

## 🤝 Contributing

Contributions are welcome! Feel free to:
- Open an issue to report a bug
- Submit a pull request to improve the code
- Suggest new features
