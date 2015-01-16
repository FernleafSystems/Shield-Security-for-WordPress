<?php
return "---
properties:
  slug: 'plugin'
  name: 'Dashboard'
  show_feature_menu_item: true
  storage_key: 'plugin' # should correspond exactly to that in the plugin.yaml
# Options Sections
sections:
  -
    slug: 'section_global_security_options'
    primary: true
  -
    slug: 'section_general_plugin_options'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'ip_whitelist'
    section: 'section_global_security_options'
    default: ''
    type: 'array'
    link_info: ''
    link_blog: ''
  -
    key: 'block_send_email_address'
    section: 'section_general_plugin_options'
    default: ''
    type: 'email'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_upgrade_admin_notice'
    section: 'section_general_plugin_options'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'delete_on_deactivate'
    section: 'section_general_plugin_options'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'secret_key'
    section: 'section_non_ui'
  -
    key: 'installation_time'
    section: 'section_non_ui'
  -
    key: 'feedback_admin_notice'
    section: 'section_non_ui'
  -
    key: 'capability_can_disk_write'
    section: 'section_non_ui'
  -
    key: 'capability_can_remote_get'
    section: 'section_non_ui'
  -
    key: 'active_plugin_features'
    section: 'section_non_ui'
    value:
      -
        slug: 'admin_access_restriction'
        storage_key: 'admin_access_restriction'
        load_priority: 20
      -
        slug: 'firewall'
        storage_key: 'firewall'
      -
        slug: 'login_protect'
        storage_key: 'loginprotect'
      -
        slug: 'user_management'
        storage_key: 'user_management'
      -
        slug: 'comments_filter'
        storage_key: 'commentsfilter'
      -
        slug: 'autoupdates'
        storage_key: 'autoupdates'
      -
        slug: 'lockdown'
        storage_key: 'lockdown'
      -
        slug: 'audit_trail'
        storage_key: 'audit_trail'
        load_priority: 10
        hidden: false
      -
        slug: 'email'
        storage_key: 'email'
";