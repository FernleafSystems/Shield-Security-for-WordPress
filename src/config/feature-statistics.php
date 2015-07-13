<?php
return
	sprintf(
	"---
properties:
  slug: 'statistics'
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'statistics' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_statistics'
    primary: true
  -
    slug: 'section_stats_sharing'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_statistics'
    section: 'section_enable_plugin_feature_statistics'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_stats_sharing'
    section: 'section_stats_sharing'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
",
		_wpsf__( 'Statistics' ),
		_wpsf__( 'Summary of the main security actions taken by this plugin' ) //tagline
	);