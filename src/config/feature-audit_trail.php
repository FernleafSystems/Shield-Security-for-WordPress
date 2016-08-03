<?php
return
	sprintf(
	"---
slug: 'audit_trail'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'audit_trail' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_audit_trail'
    primary: true
  -
    slug: 'section_audit_trail_options'
  -
    slug: 'section_enable_audit_contexts'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options and assign to section slug
options:
  -
    key: 'enable_audit_trail'
    section: 'section_enable_plugin_feature_audit_trail'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/5p'
    link_blog: ''
  -
    key: 'audit_trail_auto_clean'
    section: 'section_audit_trail_options'
    transferable: true
    default: 14
    type: 'integer'
    link_info: 'http://icwp.io/5p'
    link_blog: ''
  -
    key: 'enable_audit_context_users'
    section: 'section_enable_audit_contexts'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_audit_context_plugins'
    section: 'section_enable_audit_contexts'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_audit_context_themes'
    section: 'section_enable_audit_contexts'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_audit_context_posts'
    section: 'section_enable_audit_contexts'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_audit_context_wordpress'
    section: 'section_enable_audit_contexts'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_audit_context_emails'
    section: 'section_enable_audit_contexts'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_audit_context_wpsf'
    section: 'section_enable_audit_contexts'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'recreate_database_table'
    section: 'section_non_ui'
    default: false

# Definitions for constant data that doesn't need stored in the options
definitions:
  audit_trail_table_name: 'audit_trail'
  audit_trail_table_columns:
    - 'id'
    - 'wp_username'
    - 'ip'
    - 'context'
    - 'event'
    - 'category'
    - 'message'
    - 'immutable'
    - 'created_at'
    - 'deleted_at'

menu_items:
  -
    slug: 'audit_trail_viewer'
    title: '%s'
    callback: 'displayAuditTrailViewer'
",
		_wpsf__( 'Audit Trail' ),
		_wpsf__( 'Get a view on what happens on your site, when it happens' ), //tagline
		_wpsf__( 'Audit Trail Viewer' )
	);