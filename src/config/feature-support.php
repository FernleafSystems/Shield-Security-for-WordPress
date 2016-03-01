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
  highlight_menu_item: true
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

# Definitions for constant data that doesn't need store in the options
definitions:
  default_helpdesk_url: 'http://icwp.io/shieldhelpdesk'
",
		_wpsf__( 'Premium Support' ),
		_wpsf__( 'Premium Plugin Support Centre' ) //tagline
	);