<?php
return
	sprintf(
	"---
slug: 'headers'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'headers' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_headers'
    primary: true
  -
    slug: 'section_security_headers'
  -
    slug: 'section_content_security_policy'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_headers'
    section: 'section_enable_plugin_feature_headers'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'x_frame'
    section: 'section_security_headers'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/78'
    link_blog: ''
  -
    key: 'x_xss_protect'
    section: 'section_security_headers'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/79'
    link_blog: ''
  -
    key: 'x_content_type'
    section: 'section_security_headers'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/7a'
    link_blog: ''
  -
    key: 'x_content_security_policy'
    section: 'section_content_security_policy'
    transferable: true
    default: 'N'
    type: 'array'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
",
		_wpsf__( 'HTTP Headers' ),
		_wpsf__( 'Control HTTP Security Headers' ) //tagline
	);