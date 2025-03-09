Printify Shipping Classes Generator
===================================

Contributors: Elise Teddington the UX Slayer
Donate Link: https://UXSlayer.com
Tags: Printify, Shipping Classes
Requires at least: 6.2
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Overview
--------

The Printify Shipping Classes Generator is a WooCommerce plugin that automatically creates shipping classes based on your Printify products and print providers.

Key Features
------------

1.  **Automatic Integration**: Connects directly to the Printify API to retrieve your shops, products, and print providers.
2.  **Intelligent Shipping Class Creation**: Creates WooCommerce shipping classes that combine print provider information with product names.
3.  **Manual & Scheduled Sync**: Offers both on-demand synchronization and daily automatic updates.
4.  **Robust Logging**: Comprehensive logging for troubleshooting and monitoring.
5.  **Performance Optimized**: Uses caching to minimize API calls and reduce server load.

Installation Instructions
-------------------------

1.  Upload the `printify-shipping-class-helper` folder to the `/wp-content/plugins/` directory
2.  Activate the plugin through the 'Plugins' menu in WordPress
3.  Navigate to WooCommerce > Printify Shipping to configure the plugin

Configuration
-------------

### API Settings

1.  Generate a Printify API token in your Printify account under Profile > Connections
2.  Enter your API token in the plugin settings
3.  Select your Printify shop from the dropdown

### Sync Settings

-   **Auto Sync**: Enable to automatically synchronize shipping classes daily
-   **Cache Expiration**: Set how long API data should be cached (default: 1 hour)

### Logging Settings

-   **Enable Logging**: Toggle logging for debugging purposes

Usage
-----

After configuration, the plugin will:

1.  Retrieve all products from your Printify shop
2.  Fetch information about print providers
3.  Create shipping classes following this naming pattern: "[Provider Name] - [First 6 words of Product Title]"
4.  Make these shipping classes available in the WooCommerce product editor

### Manual Sync

You can trigger a manual sync at any time by:

1.  Go to WooCommerce > Printify Shipping
2.  Click "Sync Shipping Classes" on the Dashboard tab

How It Works
------------

1.  **API Connection**: The plugin communicates directly with the Printify API using your personal access token.
2.  **Data Retrieval**: On sync, it fetches:
    -   Your Printify shop information
    -   All published products
    -   Print provider details
3.  **Shipping Class Generation**: For each product, the plugin:
    -   Identifies the associated print provider
    -   Creates a shipping class named "[Provider] - [First 6 words of product title]"
    -   Adds a description with product and provider details
4.  **WooCommerce Integration**: The shipping classes are created as standard WooCommerce shipping classes, making them available in:
    -   Shipping zone rate settings
    -   Individual product settings
5.  **Incremental Updates**: The plugin uses an "upsert" approach:
    -   Creates new shipping classes for new products
    -   Updates existing shipping classes if information has changed
    -   Preserves existing shipping classes that correspond to Printify products

Best Practices
--------------

1.  **Initial Setup**: After installation, perform a manual sync to create all required shipping classes.
2.  **Shipping Rates**: Configure specific shipping rates for each shipping class in WooCommerce > Settings > Shipping > Shipping Zones.
3.  **Regular Updates**: If you frequently add new products, enable Auto Sync to ensure shipping classes stay current.
4.  **Performance Considerations**: Set an appropriate cache expiration based on how frequently your Printify catalog changes.

Debugging
---------

If you encounter issues:

1.  Enable logging in the plugin settings
2.  Check the Logs tab for detailed information
3.  Review WooCommerce logs at WooCommerce > Status > Logs (filtered by "printify-shipping-class-helper")

Security Considerations
-----------------------

-   Your Printify API token grants access to your Printify account, so keep it secure
-   The plugin stores the token in your WordPress database encrypted
-   All API requests are made using HTTPS for secure communication

Limitations
-----------

-   The plugin only creates shipping classes; you still need to configure shipping rates in WooCommerce
-   It doesn't update shipping costs automatically based on Printify's rates
-   Only works with published Printify products

Future Enhancements
-------------------

Future versions may include:

-   Automatic import of actual shipping costs from Printify
-   Support for product variants with different shipping requirements
-   Bulk management of shipping classes
-   Integration with other Printify-related plugins
-   Filter options to selectively sync specific products or providers

Frequently Asked Questions
--------------------------

### Will this plugin automatically set shipping rates?

No, this plugin only creates the shipping classes based on your Printify products. You still need to configure specific shipping rates for each class in WooCommerce > Settings > Shipping > Shipping Zones.

### How often should I sync shipping classes?

If you add new products frequently, enable Auto Sync for daily updates. Otherwise, manual sync when you add new products is sufficient.

### Does this work with other print-on-demand services?

No, this plugin is specifically designed for Printify. Each print-on-demand service has its own API structure.

### What happens to shipping classes if I delete a product from Printify?

The shipping classes will remain in WooCommerce unless you manually delete them. The plugin doesn't remove shipping classes during sync.

### Can I customize the naming format for shipping classes?

Currently, the naming format is fixed as "[Provider] - [First 6 words of Product Title]". Custom naming may be added in future updates.

Technical Details
-----------------

### API Usage

The plugin uses these Printify API endpoints:

-   `GET /shops.json` - To retrieve available shops
-   `GET /shops/{shop_id}/products.json` - To get products from a specific shop
-   `GET /catalog/print_providers.json` - To get print provider information

### Database Impact

The plugin stores:

-   Settings in the WordPress options table (`wp_options`)
-   Shipping classes as taxonomy terms in `wp_terms`, `wp_term_taxonomy`, and `wp_term_relationships`
-   Logs in the `/wp-content/uploads/psc-logs/` directory

### WordPress Hooks

Key WordPress actions and filters used:

-   `admin_menu` - Adds the plugin menu item
-   `admin_init` - Registers plugin settings
-   `plugins_loaded` - Initializes the plugin
-   `wp_ajax_psc_run_sync` - Handles AJAX sync requests

### Caching Strategy

To minimize API calls and improve performance:

-   API responses are cached using WordPress transients
-   Default cache duration is 1 hour (configurable)
-   Manual sync clears the cache before fetching fresh data

Integration with WooCommerce
----------------------------

This plugin extends WooCommerce's shipping functionality by:

1.  Creating shipping classes in the `product_shipping_class` taxonomy
2.  Making these classes available in the product editor under the "Shipping" tab
3.  Enabling class-specific shipping rates in WooCommerce shipping zones

Compatibility
-------------

-   WordPress: 5.0+
-   WooCommerce: 3.0+
-   PHP: 7.0+

Support
-------

For support questions or feature requests, please contact:

-   Email: creativewun@gmail.com
-   Website: <https://uxslayer.com>

Changelog
---------

### 1.0.0

-   Initial release
-   API integration with Printify
-   Shipping class generation
-   Admin interface with manual sync
-   Scheduled automatic sync option
-   Logging functionality