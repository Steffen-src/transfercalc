BAUVERIS TRANSFERCALC 0.9.3 — WORDPRESS TRANSFER FARES & ENQUIRIES

Local release checks documented; platform checkout/publication are separate.
TransferPilot is a working name; brand clearance is still pending.

WHAT IS INCLUDED
Fixed-price routes, vehicle capacities, optional address-based road routing,
base/per-kilometre/minimum fares, per-journey night surcharge, optional return
journey, once-per-enquiry extras, contact form, saved enquiries, admin statuses,
CSV export, configurable internal notifications and data retention.
German and English interface, selected in TransferPilot > Setup > Language.
Custom location names, descriptions, extras and fare notices are authored by the
operator and are intentionally not translated automatically.

INSTALL
Upload transferpilot-0.9.3.zip through Plugins > Add Plugin > Upload Plugin.
Activate and open TransferPilot > Setup. Add business details and notification
email, privacy URL, currency, locations and vehicle classes. Save first, then add
fixed-price routes with their vehicle prices. Add [transferpilot] to a page.
No Elementor, WooCommerce or special theme is required. WordPress 6.5+, PHP 8.1+.
Single-site only. Multisite/network activation is not supported in this release.

CONFIGURATION
The downloadable plugin has no example routes or customer data, no recipient
address and no Google key. Populate these for your own business.
Empty route/vehicle price means that vehicle class is not offered on that route.
All entered prices are final operator amounts; the plugin does not add tax.
Set your fare notice to explain included/excluded fees and applicable taxes.
Use a named WordPress timezone, e.g. Europe/London or Europe/Berlin. All journey
dates and times use that one operator timezone, shown on the form.

GOOGLE ROUTING (OPTIONAL)
Fixed-price routes work without Google. Address-based routing needs your own
Google Cloud billing account, Routes API key and a public terms URL. Configure
these, then enable both fare modes. The provider may charge for usage.
The API key is kept server-side in a non-autoloaded WP option, not encrypted at
rest. Protect your database/backups. Restrict the key to Routes API and server
IPs where supported. Set provider quotas and monitor billing.
Default: DRIVE/TRAFFIC_UNAWARE. Optional traffic forecasts use TRAFFIC_AWARE
with pickup time (Google Pro). Places API (New) suggestions and click-loaded
Maps JavaScript maps are optional. Map keys are separate and referrer-restricted.
See GOOGLE-FUNKTIONEN-DE-EN.txt for setup/costs. A return journey makes
a separate reverse-route request. Missing/invalid provider responses never
produce an invented fare. Routing cannot be used until configured.
Raw Google distance/duration results are not stored in enquiry records. The
commercial price breakdown and customer-provided addresses are retained.
Review current Google terms, attribution and EEA-specific requirements before
public use. Displayed Google results have a Google Maps attribution label.

FARE RULES
Fixed route: configured one-way fare, doubled for a return. Address routing:
max(minimum fare, base fare + distance x per-km fare), calculated separately for
each direction. Integer minor units avoid floating-point money accumulation.
Night surcharge applies per qualifying journey, checked against its pickup hour.
Extras are charged ONCE for the entire enquiry, including a return. No quantities.
No automatic toll, parking, airport, waiting-time or tax calculation is included.
An enquiry is NOT a confirmed booking and does not reserve vehicle availability.

OPERATIONS & PRIVACY
Internal email contains a reference and a link to protected WordPress admin.
Email failure does not lose the saved enquiry. 'Accepted' mail status means
wp_mail accepted it, not confirmed delivery. No passenger email is sent here.
Configure your hosting mail/SMTP provider and verify real delivery.
Exclude the form page from caching. Public nonces expire. Configure reliable
server cron for daily retention deletion (default 90 days, adjustable 1–730).
WordPress personal-data export and erasure are supported. CSV files, sent email
and backups remain the operator's responsibility. Admin access requires
manage_options. Tokens protect calculated fares and prevent duplicate saves.
Rate limits are per direct peer IP: 30 quotes / 8 enquiry attempts per 15 minutes.
Check proxy handling on target hosting. Forwarded headers are not blindly trusted.

UNINSTALL & UPDATES
Deactivation stops retention cron. Deletion removes the Google key but preserves
enquiries and settings to prevent accidental data loss. Erase enquiries before
permanent removal and arrange removal of retained tables/options as needed.
Manual ZIP updates only. No licence server or automatic update service is included.
Support terms: SUPPORT-DE-EN.txt. GPL-2.0-or-later.

VERIFICATION
See RELEASE-TESTS-DE-EN.txt. Fresh WordPress 6.5 installation, anonymous HTTP,
PHP 8.1/8.4 core suites, real Places/traffic/maps and enquiry tests documented.
Broader hosting/browser/physical touch-device coverage is not guaranteed.
Fixtures and operator credentials are excluded from the ZIP.

NOT INCLUDED
Passenger payments, invoicing, fleet availability, driver dispatch, flight
tracking, automatic booking confirmation, automatic content translation,
automatic per-address timezone selection, navigation or coloured congestion maps.
Support: SUPPORT-DE-EN.txt. Setup: START-HERE-DE-EN.txt.
