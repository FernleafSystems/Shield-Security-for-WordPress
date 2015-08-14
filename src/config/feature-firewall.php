<?php
return
	sprintf(
	"---
slug: 'firewall'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'firewall' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_wordpress_firewall'
    primary: true
  -
    slug: 'section_firewall_blocking_options'
  -
    slug: 'section_choose_firewall_block_response'
  -
    slug: 'section_whitelist'
  -
    slug: 'section_blacklist'
  -
    slug: 'section_firewall_logging'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_firewall'
    section: 'section_enable_plugin_feature_wordpress_firewall'
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/43'
    link_blog: 'http://icwp.io/wpsf01'
  -
    key: 'include_cookie_checks'
    section: 'section_firewall_blocking_options'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_dir_traversal'
    section: 'section_firewall_blocking_options'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_sql_queries'
    section: 'section_firewall_blocking_options'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_wordpress_terms'
    section: 'section_firewall_blocking_options'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_field_truncation'
    section: 'section_firewall_blocking_options'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_php_code'
    section: 'section_firewall_blocking_options'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_exe_file_uploads'
    section: 'section_firewall_blocking_options'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_leading_schema'
    section: 'section_firewall_blocking_options'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'block_response'
    section: 'section_choose_firewall_block_response'
    default: 'redirect_die_message'
    type: 'select'
    value_options:
      -
        value_key: 'redirect_die_message'
        text: 'Die With Message'
      -
        value_key: 'redirect_die'
        text: 'Die'
      -
        value_key: 'redirect_home'
        text: 'Redirect To Home Page'
      -
        value_key: 'redirect_404'
        text: 'Return 404'
    link_info: ''
    link_blog: ''
  -
    key: 'block_send_email'
    section: 'section_choose_firewall_block_response'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'page_params_whitelist'
    section: 'section_whitelist'
    default: ''
    type: 'comma_separated_lists'
    link_info: 'http://icwp.io/2a'
    link_blog: ''
  -
    key: 'whitelist_admins'
    section: 'section_whitelist'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'ignore_search_engines'
    section: 'section_whitelist'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'ips_blacklist'
    section: 'section_blacklist'
    default: ''
    type: 'ip_addresses'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
",
		_wpsf__( 'Firewall' ),
		_wpsf__( 'Automatically block malicious URLs and data sent to your site' )
	);