<?php
return
	sprintf(
	"---
slug: 'lockdown'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'lockdown' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_wordpress_lockdown'
    primary: true
  -
    slug: 'section_system_lockdown'
  -
    slug: 'section_security_headers'
  -
    slug: 'section_permission_access_options'
  -
    slug: 'section_wordpress_obscurity_options'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_lockdown'
    section: 'section_enable_plugin_feature_wordpress_lockdown'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/4r'
    link_blog: ''
  -
    key: 'disable_xmlrpc'
    section: 'section_system_lockdown'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'disable_file_editing'
    section: 'section_permission_access_options'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/4q'
    link_blog: ''
  -
    key: 'force_ssl_login'
    section: 'section_permission_access_options'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/4s'
    link_blog: ''
  -
    key: 'force_ssl_admin'
    section: 'section_permission_access_options'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/4t'
    link_blog: ''
  -
    key: 'mask_wordpress_version'
    section: 'section_wordpress_obscurity_options'
    transferable: true
    default: ''
    type: 'text'
    link_info: 'http://icwp.io/43'
    link_blog: ''
  -
    key: 'hide_wordpress_generator_tag'
    section: 'section_wordpress_obscurity_options'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_author_discovery'
    section: 'section_wordpress_obscurity_options'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/wpsf23'
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
",
		_wpsf__( 'Lockdown' ),
		_wpsf__( 'Harden the more loosely controlled settings of your site' ) //tagline
	);