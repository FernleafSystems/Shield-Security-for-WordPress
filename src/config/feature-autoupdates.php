<?php
return "---
slug: 'autoupdates'
properties:
  name: 'Automatic Updates'
  show_feature_menu_item: true
  storage_key: 'autoupdates' # should correspond exactly to that in the plugin.yaml
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_automatic_updates_control'
    primary: true
  -
    slug: 'section_disable_all_wordpress_automatic_updates'
  -
    slug: 'section_automatic_plugin_self_update'
  -
    slug: 'section_automatic_updates_for_wordpress_components'
  -
    slug: 'section_automatic_update_email_notifications'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options and assign to section slug
options:
  -
    key: 'enable_autoupdates'
    section: 'section_enable_plugin_feature_automatic_updates_control'
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/3w'
    link_blog: ''
  -
    key: 'enable_autoupdate_disable_all'
    section: 'section_disable_all_wordpress_automatic_updates'
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/3v'
    link_blog: ''
  -
    key: 'autoupdate_plugin_self'
    section: 'section_automatic_plugin_self_update'
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/3u'
    link_blog: ''
  -
    key: 'autoupdate_core'
    section: 'section_automatic_updates_for_wordpress_components'
    default: 'core_minor'
    type: 'select'
    value_options:
      -
        value_key: 'core_never'
        text: 'Never'
      -
        value_key: 'core_minor'
        text: 'Minor Versions Only'
      -
        value_key: 'core_major'
        text: 'Major and Minor Versions'
    link_info: 'http://icwp.io/3x'
    link_blog: ''
  -
    key: 'enable_autoupdate_plugins'
    section: 'section_automatic_updates_for_wordpress_components'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_autoupdate_themes'
    section: 'section_automatic_updates_for_wordpress_components'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_autoupdate_translations'
    section: 'section_automatic_updates_for_wordpress_components'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_autoupdate_ignore_vcs'
    section: 'section_automatic_updates_for_wordpress_components'
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'enable_upgrade_notification_email'
    section: 'section_automatic_update_email_notifications'
    default: ''
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'override_email_address'
    section: 'section_automatic_update_email_notifications'
    default: ''
    type: 'email'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'action_hook_priority'
    section: 'section_non_ui'
    default: 1000
";