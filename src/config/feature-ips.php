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
requirements:
  php:
    version: '5.3.6'
    functions:
      - 'filter_var'
    constants:
      - 'FILTER_VALIDATE_IP'
      - 'FILTER_FLAG_IPV4'
      - 'FILTER_FLAG_IPV6'
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
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'transgression_limit'
    section: 'section_auto_black_list'
    default: 5
    type: 'integer'
    link_info: ''
    link_blog: ''
  -
    key: 'auto_expire'
    section: 'section_auto_black_list'
    default: 'hour'
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
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'ip_lists_table_name'
    section: 'section_non_ui'
    value: 'ip_lists'
  -
    key: 'ip_list_table_columns'
    section: 'section_non_ui'
    value:
      - 'id'
      - 'ip'
      - 'label'
      - 'list'
      - 'ip6'
      - 'range'
      - 'transgressions'
      - 'last_access_at'
      - 'created_at'
      - 'deleted_at'
",
		_wpsf__( 'IP Manager' ),
		_wpsf__( 'Manage Visitor IP Address' )
	);