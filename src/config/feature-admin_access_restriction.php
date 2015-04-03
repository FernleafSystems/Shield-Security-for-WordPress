<?php
return
	sprintf(
	"---
slug: 'admin_access_restriction'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'admin_access_restriction' # should correspond exactly to that in the plugin.yaml
  menu_title: '%s'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_admin_access_restriction'
    primary: true
  -
    slug: 'section_admin_access_restriction_settings'
    primary: false
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_admin_access_restriction'
    section: 'section_enable_plugin_feature_admin_access_restriction'
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/40'
    link_blog: 'http://icwp.io/wpsf02'
  -
    key: 'admin_access_key'
    section: 'section_admin_access_restriction_settings'
    default: ''
    type: 'password'
    link_info: 'http://icwp.io/42'
    link_blog: ''
  -
    key: 'admin_access_timeout'
    section: 'section_admin_access_restriction_settings'
    default: 30
    type: 'integer'
    link_info: 'http://icwp.io/41'
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'admin_access_key_cookie_name'
    section: 'section_non_ui'
    value: 'icwp_wpsf_aakcook'
",
		_wpsf__( 'Admin Access Restriction' ),
		_wpsf__( 'Admin Access' )
	);