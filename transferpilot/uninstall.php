<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
// Preserve enquiries on deletion to prevent accidental loss. Operators erase
// personal data via WP privacy tools or delete requests before uninstalling.
wp_clear_scheduled_hook('tp_retention');
delete_option('tp_google_key');
delete_option('tp_places_key');
delete_option('tp_places_enabled');

delete_option('tp_maps_browser_key');
