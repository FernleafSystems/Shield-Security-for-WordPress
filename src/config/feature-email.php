<?php
return
	sprintf(
	"---
slug: 'email'
properties:
  name: '%s'
  show_feature_menu_item: false
  storage_key: 'email' # should correspond exactly to that in the plugin.yaml
# Options Sections
sections:
  -
    slug: 'section_email_options'
    primary: true
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'send_email_throttle_limit'
    section: 'section_email_options'
    default: 10
    type: 'integer'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
",
		_wpsf__( 'Email' )
	);