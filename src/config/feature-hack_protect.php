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
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'plugin_vulnerabilities_data_source'
    value: 'https://raw.githubusercontent.com/FernleafSystems/wp-plugin-vulnerabilities/master/vulnerabilities.yaml'
    immutable: true
    section: 'section_non_ui'
  -
    key: 'notifications_cron_name'
    default: 'plugin-vulnerabilities-notification'
    section: 'section_non_ui'

# Definitions for constant data that doesn't need store in the options
definitions:
  corechecksum_cron_name: 'core-checksum-notification'
  url_checksum_api: 'https://api.wordpress.org/core/checksums/1.0/'
  url_wordress_core_svn: 'https://core.svn.wordpress.org/'
",
		_wpsf__( 'Hack Protection' )
	);