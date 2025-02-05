=== Aramex Shipping AUNZ ===
Contributors: TBP
Tags: shipping, woocommerce, aramex, australia, new zealand
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Plugin URI: https://github.com/madeinoz67/aramex-shipping-aunz

Integrate Aramex shipping services with WooCommerce for Australia and New Zealand, featuring real-time rates, label generation, and tracking.

## Description

The Aramex Shipping AUNZ plugin provides a complete shipping solution for WooCommerce stores in Australia and New Zealand. It integrates directly with Aramex's API to provide real-time shipping rates, automated label generation, and comprehensive tracking capabilities.

### Key Features

- Real-time shipping rate calculations
- Automated shipping label generation
- Package tracking integration
- Customer email notifications with tracking information
- Multiple packaging options (boxes and satchels)
- Support for both Australia and New Zealand shipping
- Address validation and autocomplete
- Bulk label printing
- Custom box size definitions
- Rural delivery support

## Installation

1. Upload the plugin files to the `/wp-content/plugins/aramex-shipping-aunz` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to WooCommerce > Settings > Shipping > Aramex Shipping Settings to configure the plugin
4. Enter your Aramex API credentials and configure your preferred settings

## Configuration

### API Credentials
1. Log in to your Aramex account
2. Navigate to API Settings to generate your API Key and Secret
3. Enter these credentials in the plugin settings

### Package Types
- Configure available package types (satchels and boxes)
- Set up custom box sizes if needed
- Enable/disable specific package types

### Email Notifications
- Customize tracking email templates
- Configure automated email triggers
- Set up customer notification preferences

## Usage

### Creating Shipments
1. Open a WooCommerce order
2. Click "Create Consignment" to generate a shipping label
3. Print the label and attach it to your package

### Tracking Shipments
1. Use the tracking shortcode `[aramex_tracking]` on any page
2. View tracking information directly in the order details
3. Automated tracking emails sent to customers

## Frequently Asked Questions

### What countries are supported?
Currently, the plugin supports shipping within and between Australia and New Zealand.

### Can I use custom box sizes?
Yes, you can define custom box sizes in the plugin settings using the JSON format provided.

### How do I enable real-time rates?
Real-time rates are enabled by default when you enter valid API credentials.

## Screenshots

1. Shipping Settings Page
2. Label Generation Interface
3. Tracking Information Display
4. Email Notification Template

## Changelog

### 1.0.0
* Initial release

## License

This plugin is licensed under the GNU General Public License v2 or later.

License URI: http://www.gnu.org/licenses/gpl-2.0.html

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

## Credits

Developed by TBP

## Technical Details

* Requires at least: WordPress 5.8
* Tested up to: WordPress 6.7
* Requires PHP: 7.4
* Stable tag: 1.0.0
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
