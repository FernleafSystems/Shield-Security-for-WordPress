<?php
return "---
properties:
  version: '5.3.1'
  slug_parent: 'icwp'
  slug_plugin: 'wpsf'
  human_name: 'Shield'
  menu_title: 'Shield'
  text_domain: 'wp-simple-firewall'
  base_permissions: 'manage_options'
  wpms_network_admin_only: true
  logging_enabled: true
  autoupdate: 'pass' #yes/block/pass/confidence - confidence is where the version update detected has been available for at least 48hrs.

requirements:
  php: '5.2.4'
  wordpress: '3.5.0'

paths:
  source: 'src'
  assets: 'resources'
  languages: 'languages'
  templates: 'templates'
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
      - icwp-options
  frontend:
    css:

menu:
  show: true
  title: 'Shield'
  top_level: true # to-do is allow for non-top-level menu items.
  do_submenu_fix: true
  callback: 'onDisplayTopMenu'
  icon_image: 'pluginlogo_16x16.png'
  has_submenu: true # to-do is allow for non-top-level menu items.

labels: #the keys below must correspond exactly for the 'all_plugins' filter
  Name: 'Shield'
  Description: \"Secure Your Sites With The World's Most Powerful WordPress Security Protection System\"
  Title: 'Shield'
  Author: 'iControlWP'
  AuthorName: 'iControlWP'
  PluginURI: 'http://icwp.io/home'
  AuthorURI: 'http://icwp.io/home'
  icon_url_16x16: 'pluginlogo_16x16.png'
  icon_url_32x32: 'pluginlogo_32x32.png'

# This is on the plugins.php page with the option to remove or add custom links.
plugin_meta:
    -
      name: '5&#10025; Rate This Plugin'
      href: 'http://icwp.io/wpsf29'
action_links:
  remove:
  add:
    -
      name: 'Dashboard'
      url_method_name: 'getPluginUrl_AdminMainPage'
";