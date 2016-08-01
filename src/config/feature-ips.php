<?php
return
	sprintf(
	"---
slug: 'ips'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'ips' # should correspond exactly to that in the plugin-spec.yaml
  tagline: '%s'
admin_notices:
  'visitor-whitelisted':
    id: 'visitor-whitelisted'
    schedule: 'conditions'
    valid_admin: true
    type: 'info'
requirements:
  php:
    functions:
      - 'filter_var'
    constants:
      - 'FILTER_VALIDATE_IP'
      - 'FILTER_FLAG_IPV4'
      - 'FILTER_FLAG_IPV6'
      - 'FILTER_FLAG_NO_PRIV_RANGE'
      - 'FILTER_FLAG_NO_RES_RANGE'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_ips'
    primary: true
  -
    slug: 'section_auto_black_list'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_ips'
    section: 'section_enable_plugin_feature_ips'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/wpsf26'
    link_blog: ''
  -
    key: 'transgression_limit'
    section: 'section_auto_black_list'
    transferable: true
    default: 10
    type: 'integer'
    link_info: 'http://icwp.io/wpsf24'
    link_blog: 'http://icwp.io/wpsf26'
  -
    key: 'auto_expire'
    section: 'section_auto_black_list'
    transferable: true
    default: 'minute'
    type: 'select'
    value_options:
      -
        value_key: 'minute'
        text: 'Minute'
      -
        value_key: 'hour'
        text: 'Hour'
      -
        value_key: 'day'
        text: 'Day'
      -
        value_key: 'week'
        text: 'Week'
    link_info: 'http://icwp.io/wpsf25'
    link_blog: 'http://icwp.io/wpsf26'
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'this_server_ip'
    sensitive: true
    section: 'section_non_ui'
    value: ''
  -
    key: 'this_server_ip_last_check_at'
    section: 'section_non_ui'
    value: 0
  -
    key: 'recreate_database_table'
    section: 'section_non_ui'
    default: false

# Definitions for constant data that doesn't need stored in the options
definitions:
  ip_lists_table_name: 'ip_lists'
  ip_list_table_columns:
    - 'id'
    - 'ip'
    - 'label'
    - 'list'
    - 'ip6'
    - 'is_range'
    - 'transgressions'
    - 'last_access_at'
    - 'created_at'
    - 'deleted_at'
",
		_wpsf__( 'IP Manager' ),
		_wpsf__( 'Manage Visitor IP Address' )
	);