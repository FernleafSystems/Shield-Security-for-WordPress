<?php
return "---
properties:
  version: '4.7.0'
  slug_parent: 'icwp'
  slug_plugin: 'wpsf'
  human_name: 'WordPress Simple Firewall'
  menu_title: 'Simple Firewall'
  text_domain: 'wp-simple-firewall'
  base_permissions: 'manage_options'
  wpms_network_admin_only: true
  logging_enabled: true
  autoupdate: 'pass' #yes/block/pass/confidence - confidence is where the version update detected has been available for at least 48hrs.
paths:
  source: 'src'
  assets: 'resources'
  languages: 'languages'
  views: 'views'
  flags: 'flags'
includes:
  admin:
    css:
      - global-plugin
  plugin_admin:
    css:
      - bootstrap-wpadmin-legacy
      - bootstrap-wpadmin-fixes
      - plugin
    js:
      - bootstrap.min
  frontend:
    css:

menu:
  show: true
  title: 'Simple Firewall'
  top_level: true # to-do is allow for non-top-level menu items.
  do_submenu_fix: true
  callback: 'onDisplayTopMenu'
  icon_image: 'pluginlogo_16x16.png'
  has_submenu: true # to-do is allow for non-top-level menu items.


labels: #the keys below must correspond exactly for the 'all_plugins' filter
  Name: 'WordPress Simple Firewall'
  Description: 'Take Control Of All WordPress Sites From A Single Dashboard'
  Title: 'WordPress Simple Firewall'
  Author: 'iControlWP'
  AuthorName: 'iControlWP'
  PluginURI: 'http://icwp.io/home'
  AuthorURI: 'http://icwp.io/home'
  icon_url_16x16: 'pluginlogo_16x16.png'
  icon_url_32x32: 'pluginlogo_32x32.png'

# This is on the plugins.php page with the option to remove or add custom links.
action_links:
  remove:
  add:
    -
      name: 'Dashboard'
      url_method_name: 'getPluginUrl_AdminMainPage'
";