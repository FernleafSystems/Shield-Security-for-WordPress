<?php
return
	sprintf(
	"---
properties:
  slug: 'plugin'
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'plugin' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'

admin_notices:
  'override-forceoff':
    id: 'override-forceoff'
    schedule: 'conditions'
    valid_admin: true
    type: 'error'
  'php53-version-warning':
    id: 'php53-version-warning'
    schedule: 'once'
    valid_admin: true
    type: 'warning'
  'plugin-update-available':
    id: 'plugin-update-available'
    schedule: 'version'
    valid_admin: true
    type: 'warning'
  'post-plugin-update':
    id: 'post-plugin-update'
    schedule: 'version'
    valid_admin: true
    type: 'warning'
  'plugin-mailing-list-signup':
    id: 'plugin-mailing-list-signup'
    schedule: 'once'
    valid_admin: true
    delay_days: 15
    type: 'promo'
  'rate-plugin':
    id: 'rate-plugin'
    schedule: 'once'
    valid_admin: true
    delay_days: 30
    type: 'promo'
  'translate-plugin':
    id: 'translate-plugin'
    schedule: 'once'
    valid_admin: true
    delay_days: 45
    type: 'promo'
  'allow-tracking':
    id: 'allow-tracking'
    schedule: 'once'
    valid_admin: true
    delay_days: 0
    type: 'promo'

# Options Sections
sections:
  -
    slug: 'section_global_security_options'
    primary: true
  -
    slug: 'section_general_plugin_options'
  -
    slug: 'section_third_party_google'
  -
    slug: 'section_third_party_duo'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'global_enable_plugin_features'
    section: 'section_global_security_options'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_tracking'
    section: 'section_general_plugin_options'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_send_email_address'
    section: 'section_general_plugin_options'
    transferable: true
    sensitive: true
    default: ''
    type: 'email'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_upgrade_admin_notice'
    section: 'section_general_plugin_options'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'display_plugin_badge'
    section: 'section_general_plugin_options'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/5v'
    link_blog: 'http://icwp.io/wpsf20'
  -
    key: 'delete_on_deactivate'
    section: 'section_general_plugin_options'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'unique_installation_id'
    section: 'section_general_plugin_options'
    default: ''
    type: 'noneditable_text'
    link_info: ''
    link_blog: ''
  -
    key: 'google_recaptcha_site_key'
    section: 'section_third_party_google'
    transferable: true
    sensitive: true
    default: ''
    type: 'text'
    link_info: 'http://icwp.io/shld5'
    link_blog: ''
  -
    key: 'google_recaptcha_secret_key'
    section: 'section_third_party_google'
    transferable: true
    sensitive: true
    default: ''
    type: 'text'
    link_info: 'http://icwp.io/shld5'
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'last_tracking_sent_at'
    section: 'section_non_ui'
  -
    key: 'tracking_permission_set_at'
    default: 0
    section: 'section_non_ui'
  -
    key: 'installation_time'
    section: 'section_non_ui'

# Definitions for constant data that doesn't need stored in the options
definitions:
  tracking_cron_handle: 'plugin_tracking_cron'
  tracking_post_url: 'https://tracking.icontrolwp.com/track/plugin/shield'
  active_plugin_features:
    -
      slug: 'admin_access_restriction'
      storage_key: 'admin_access_restriction'
      load_priority: 20
    -
      slug: 'firewall'
      storage_key: 'firewall'
      load_priority: 13
    -
      slug: 'login_protect'
      storage_key: 'loginprotect'
    -
      slug: 'user_management'
      storage_key: 'user_management'
    -
      slug: 'comments_filter'
      storage_key: 'commentsfilter'
    -
      slug: 'autoupdates'
      storage_key: 'autoupdates'
    -
      slug: 'hack_protect'
      storage_key: 'hack_protect'
    -
      slug: 'headers'
      storage_key: 'headers'
    -
      slug: 'lockdown'
      storage_key: 'lockdown'
    -
      slug: 'ips'
      storage_key: 'ips'
      load_priority: 12
    -
      slug: 'statistics'
      storage_key: 'statistics'
      load_priority: 11
      hidden: false
    -
      slug: 'audit_trail'
      storage_key: 'audit_trail'
      load_priority: 11
      hidden: false
    -
      slug: 'support'
      storage_key: 'support'
      load_priority: 20
      hidden: false
    -
      slug: 'email'
      storage_key: 'email'
      
",
		_wpsf__( 'Dashboard' ),
		_wpsf__( 'Overview of the plugin settings' ) //tagline
	);