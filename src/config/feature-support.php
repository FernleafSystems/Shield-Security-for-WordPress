<?php
return
	sprintf(
	"---
properties:
  slug: 'support'
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'support' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'
  auto_enabled: true
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_support'
    primary: true
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_support'
    section: 'section_enable_plugin_feature_support'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
",
		_wpsf__( 'Premium Support' ),
		_wpsf__( 'Premium Plugin Support Centre' ) //tagline
	);