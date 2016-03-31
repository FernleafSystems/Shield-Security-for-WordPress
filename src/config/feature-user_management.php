<?php
return
	sprintf(
	"---
slug: 'user_management'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'user_management' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'
  storag
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_user_accounts_management'
    primary: true
  -
    slug: 'section_user_session_management'
  -
    slug: 'section_multifactor_authentication'
  -
    slug: 'section_bypass_user_accounts_management'
  -
    slug: 'section_admin_login_notification'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_user_management'
    section: 'section_enable_plugin_feature_user_accounts_management'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_xmlrpc_compatibility'
    section: 'section_bypass_user_accounts_management'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_admin_login_email_notification'
    section: 'section_admin_login_notification'
    transferable: true
    default: ''
    type: 'email'
    link_info: ''
    link_blog: ''
  -
    key: 'session_timeout_interval'
    section: 'section_user_session_management'
    transferable: true
    default: 2
    type: 'integer'
    link_info: ''
    link_blog: ''
  -
    key: 'session_idle_timeout_interval'
    section: 'section_user_session_management'
    transferable: true
    default: 0
    type: 'integer'
    link_info: ''
    link_blog: ''
  -
    key: 'session_lock_location'
    section: 'section_user_session_management'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'session_username_concurrent_limit'
    section: 'section_user_session_management'
    transferable: true
    default: 0
    type: 'integer'
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
  user_sessions_table_name: 'user_management'
  user_sessions_table_columns:
    - 'id'
    - 'session_id'
    - 'wp_username'
    - 'ip'
    - 'logged_in_at'
    - 'last_activity_at'
    - 'last_activity_uri'
    - 'used_mfa'
    - 'pending'
    - 'login_attempts'
    - 'created_at'
    - 'deleted_at'
",
		_wpsf__( 'User Management' ),
		_wpsf__( 'Get true user sessions and control account sharing, session duration and timeouts' ) //tagline
	);