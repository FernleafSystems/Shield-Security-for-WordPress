<?php
return
	sprintf(
	"---
slug: 'hack_protect'
properties:
  name: '%s'
  show_feature_menu_item: false
  storage_key: 'hack_protect' # should correspond exactly to that in the plugin.yaml
  auto_enabled: true
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_hack_protection_tools'
    primary: true
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'

# Definitions for constant data that doesn't need store in the options
definitions:
  plugin_vulnerabilities_data_source: 'https://raw.githubusercontent.com/FernleafSystems/wp-plugin-vulnerabilities/master/vulnerabilities.yaml'
  notifications_cron_name: 'plugin-vulnerabilities-notification'
  corechecksum_cron_name: 'core-checksum-notification'
  url_checksum_api: 'https://api.wordpress.org/core/checksums/1.0/'
  url_wordress_core_svn: 'https://core.svn.wordpress.org/'
",
		_wpsf__( 'Hack Protection' )
	);