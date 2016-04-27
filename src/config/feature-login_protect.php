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
    slug: 'section_brute_force_login_protection'
  -
    slug: 'section_multifactor_authentication'
  -
    slug: 'section_rename_wplogin'
  -
    slug: 'section_yubikey_authentication'
  -
    slug: 'section_bypass_login_protection'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_login_protect'
    section: 'section_enable_plugin_feature_login_protection'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/51'
    link_blog: 'http://icwp.io/wpsf03'
  -
    key: 'enable_xmlrpc_compatibility'
    section: 'section_bypass_login_protection'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'rename_wplogin_path'
    section: 'section_rename_wplogin'
    transferable: true
    default: ''
    type: 'text'
    link_info: 'http://icwp.io/5q'
    link_blog: 'http://icwp.io/5r'
  -
    key: 'enable_google_authenticator'
    section: 'section_multifactor_authentication'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/shld7'
    link_blog: 'http://icwp.io/shld6'
  -
    key: 'enable_email_authentication'
    section: 'section_multifactor_authentication'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/3s'
    link_blog: ''
  -
    key: 'enable_two_factor_auth_by_ip'
    section: 'section_non_ui'
  -
    key: 'enable_two_factor_auth_by_cookie'
    section: 'section_non_ui'
  -
    key: 'two_factor_auth_user_roles'
    section: 'section_multifactor_authentication'
    transferable: true
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
    key: 'enable_google_recaptcha'
    section: 'section_brute_force_login_protection'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/shld5'
    link_blog: ''
  -
    key: 'enable_login_gasp_check'
    section: 'section_brute_force_login_protection'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/3r'
    link_blog: ''
  -
    key: 'login_limit_interval'
    section: 'section_brute_force_login_protection'
    transferable: true
    default: '10'
    type: 'integer'
    link_info: 'http://icwp.io/3q'
    link_blog: ''
  -
    key: 'enable_user_register_checking'
    section: 'section_brute_force_login_protection'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_prevent_remote_post'
    section: 'section_brute_force_login_protection'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/4n'
    link_blog: ''
  -
    key: 'enable_yubikey'
    section: 'section_yubikey_authentication'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/4f'
    link_blog: ''
  -
    key: 'yubikey_app_id'
    section: 'section_yubikey_authentication'
    transferable: true
    default: ''
    type: 'text'
    link_info: 'http://icwp.io/4g'
    link_blog: ''
  -
    key: 'yubikey_api_key'
    section: 'section_yubikey_authentication'
    transferable: true
    default: ''
    type: 'text'
    link_info: 'http://icwp.io/4g'
    link_blog: ''
  -
    key: 'yubikey_unique_keys'
    section: 'section_yubikey_authentication'
    transferable: true
    default: ''
    type: 'yubikey_unique_keys'
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
    key: 'two_factor_auth_table_created'
    section: 'section_non_ui'
  -
    key: 'recreate_database_table'
    section: 'section_non_ui'
    default: false

# Definitions for constant data that doesn't need stored in the options
definitions:
  two_factor_auth_table_name: 'login_auth'
  two_factor_auth_table_columns:
    - 'id'
    - 'session_id'
    - 'wp_username'
    - 'ip'
    - 'pending'
    - 'expired_at'
    - 'created_at'
    - 'deleted_at'
",
		_wpsf__( 'Login Protection' ),
		_wpsf__( 'Block brute force attacks and secure user identities with Two-Factor Authentication' ) //tagline
	);