<?php
/**
 * Plugin Name: TransferPilot — Transfer Fare & Enquiries
 * Description: Configurable transfer fares, optional road routing and saved ride enquiries. German and English interface. No automatic booking confirmation.
 * Version: 0.9.3
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: BAUVERIS
 * License: GPL-2.0-or-later
 * Text Domain: transferpilot
 */
if (!defined('ABSPATH')) exit;
define('TP_VERSION', '0.9.3');
define('TP_DIR', plugin_dir_path(__FILE__));
require_once TP_DIR . 'includes/class-tp-i18n.php';
require_once TP_DIR . 'includes/class-tp-fares.php';
require_once TP_DIR . 'includes/class-tp-plugin.php';
require_once TP_DIR . 'includes/class-tp-places.php';
require_once TP_DIR . 'includes/class-tp-admin.php';
require_once TP_DIR . 'includes/class-tp-diagnostics.php';
register_activation_hook(__FILE__, ['TP_Plugin','activate']);
register_deactivation_hook(__FILE__, function(){ wp_clear_scheduled_hook('tp_retention'); });
TP_Plugin::init();
TP_Diagnostics::boot();
