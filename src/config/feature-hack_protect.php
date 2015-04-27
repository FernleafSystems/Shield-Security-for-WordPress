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
",
		_wpsf__( 'Hack Protection' )
	);