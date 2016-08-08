<?php
return
	sprintf(
	"---
properties:
  slug: 'statistics'
  name: '%s'
  show_feature_menu_item: false
  storage_key: 'statistics' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_statistics'
    primary: true
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_statistics'
    section: 'section_enable_plugin_feature_statistics'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'

# Definitions for constant data that doesn't need stored in the options
definitions:
  statistics_table_name: 'statistics'
  statistics_table_columns:
    - 'id'
    - 'stat_key'
    - 'parent_stat_key'
    - 'tally'
    - 'created_at'
    - 'modified_at'
    - 'deleted_at'
",
		_wpsf__( 'Statistics' ),
		_wpsf__( 'Summary of the main security actions taken by this plugin' ), //tagline
        _wpsf__( 'Stats Viewer' )
	);