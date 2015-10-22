<?php
return
	sprintf(
	"---
slug: 'login_protect'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'loginprotect' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'

admin_notices:
  'email-verification-sent':
    once: false
    valid_admin: true
    type: 'warning'

# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_login_protection'
    primary: true
  -
    slug: 'section_bypass_login_protection'
  -
    slug: 'section_rename_wplogin'
  -
    slug: 'section_two_factor_authentication'
  -
    slug: 'section_brute_force_login_protection'
  -
    slug: 'section_yubikey_authentication'
  -
    slug: 'section_login_logging'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_login_protect'
    section: 'section_enable_plugin_feature_login_protection'
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/51'
    link_blog: 'http://icwp.io/wpsf03'
  -
    key: 'enable_xmlrpc_compatibility'
    section: 'section_bypass_login_protection'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'rename_wplogin_path'
    section: 'section_rename_wplogin'
    default: ''
    type: 'text'
    link_info: 'http://icwp.io/5q'
    link_blog: 'http://icwp.io/5r'
  -
    key: 'enable_two_factor_auth_by_ip'
    section: 'section_two_factor_authentication'
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/3s'
    link_blog: ''
  -
    key: 'enable_two_factor_auth_by_cookie'
    section: 'section_two_factor_authentication'
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/3t'
    link_blog: ''
  -
    key: 'two_factor_auth_user_roles'
    section: 'section_two_factor_authentication'
    type: 'multiple_select'
    default:
      - 1
      - 2
      - 3
      - 8
    value_options:
      -
        value_key: 0
        text: 'Subscribers'
      -
        value_key: 1
        text: 'Contributors'
      -
        value_key: 2
        text: 'Authors'
      -
        value_key: 3
        text: 'Editors'
      -
        value_key: 8
        text: 'Administrators'
    link_info: 'http://icwp.io/4v'
    link_blog: ''
  -
    key: 'enable_user_register_checking'
    section: 'section_brute_force_login_protection'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'login_limit_interval'
    section: 'section_brute_force_login_protection'
    default: '10'
    type: 'integer'
    link_info: 'http://icwp.io/3q'
    link_blog: ''
  -
    key: 'enable_login_gasp_check'
    section: 'section_brute_force_login_protection'
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/3r'
    link_blog: ''
  -
    key: 'enable_prevent_remote_post'
    section: 'section_brute_force_login_protection'
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/4n'
    link_blog: ''
  -
    key: 'enable_yubikey'
    section: 'section_yubikey_authentication'
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/4f'
    link_blog: ''
  -
    key: 'yubikey_app_id'
    section: 'section_yubikey_authentication'
    default: ''
    type: 'text'
    link_info: 'http://icwp.io/4g'
    link_blog: ''
  -
    key: 'yubikey_api_key'
    section: 'section_yubikey_authentication'
    default: ''
    type: 'text'
    link_info: 'http://icwp.io/4g'
    link_blog: ''
  -
    key: 'yubikey_unique_keys'
    section: 'section_yubikey_authentication'
    default: ''
    type: 'yubikey_unique_keys'
    link_info: 'http://icwp.io/4h'
    link_blog: ''
  -
    key: 'enable_login_protect_log'
    section: 'section_login_logging'
    hidden: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/4h'
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'email_can_send_verified_at'
    section: 'section_non_ui'
    default: -1
  -
    key: 'gasp_key'
    section: 'section_non_ui'
  -
    key: 'two_factor_secret_key'
    section: 'section_non_ui'
  -
    key: 'last_login_time'
    section: 'section_non_ui'
  -
    key: 'last_login_time_file_path'
    section: 'section_non_ui'
  -
    key: 'log_category'
    section: 'section_non_ui'
  -
    key: 'two_factor_auth_table_name'
    section: 'section_non_ui'
    value: 'login_auth'
  -
    key: 'two_factor_auth_table_columns'
    immutable: true
    section: 'section_non_ui'
    value:
      - 'id'
      - 'session_id'
      - 'wp_username'
      - 'ip'
      - 'pending'
      - 'expired_at'
      - 'created_at'
      - 'deleted_at'
  -
    key: 'two_factor_auth_table_created'
    section: 'section_non_ui'
  -
    key: 'recreate_database_table'
    section: 'section_non_ui'
    default: false
",
		_wpsf__( 'Login Protection' ),
		_wpsf__( 'Block brute force attacks and secure user identities with Two-Factor Authentication' ) //tagline
	);