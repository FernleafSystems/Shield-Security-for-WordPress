{
  "slug": "lockdown",
  "properties": {
    "slug": "lockdown",
    "name": "Lockdown",
    "show_feature_menu_item": true,
    "storage_key": "lockdown",
    "tagline": "Harden the more loosely controlled settings of your site",
    "show_central": true,
    "access_restricted": true,
    "premium": false,
    "order": 90
  },
  "sections": [
    {
      "slug": "section_system_lockdown",
      "primary": true,
      "title": "WordPress System Lockdown",
      "title_short": "System",
      "summary": [
        "Purpose - Lockdown certain core WordPress system features.",
        "Recommendation - This depends on your usage and needs for certain WordPress functions and features."
      ]
    },
    {
      "slug": "section_permission_access_options",
      "title": "Permissions and Access Options",
      "title_short": "Permissions",
      "summary": [
        "Purpose - Provides finer control of certain WordPress permissions.",
        "Recommendation - Only enable SSL if you have a valid certificate installed."
      ]
    },
    {
      "slug": "section_wordpress_obscurity_options",
      "title": "WordPress Obscurity Options",
      "title_short": "Obscurity",
      "summary": [
        "Purpose - Obscures certain WordPress settings from public view.",
        "Recommendation - Obscurity is not true security and so these settings are down to your personal tastes."
      ]
    },
    {
      "slug": "section_enable_plugin_feature_wordpress_lockdown",
      "title": "Enable Plugin Feature: Lockdown",
      "title_short": "Disable Module",
      "summary": [
        "Purpose - Lockdown helps secure-up certain loosely-controlled WordPress settings on your site.",
        "Recommendation - Keep the Lockdown feature turned on."
      ]
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_lockdown",
      "section": "section_enable_plugin_feature_wordpress_lockdown",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/4r",
      "link_blog": "",
      "name": "Enable Lockdown",
      "summary": "Enable (or Disable) The Lockdown Feature",
      "description": "Checking/Un-Checking this option will completely turn on/off the whole Lockdown feature"
    },
    {
      "key": "disable_xmlrpc",
      "section": "section_system_lockdown",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Disable XML-RPC",
      "summary": "Disable The XML-RPC System",
      "description": "Checking this option will completely turn off the whole XML-RPC system."
    },
    {
      "key": "disable_anonymous_restapi",
      "section": "section_system_lockdown",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Disable Anonymous Rest API",
      "summary": "Disable The Anonymous Rest API",
      "description": "Checking this option will completely turn off the whole Anonymous Rest API system."
    },
    {
      "key": "disable_file_editing",
      "section": "section_permission_access_options",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/4q",
      "link_blog": "",
      "name": "Disable File Editing",
      "summary": "Disable Ability To Edit Files From Within WordPress",
      "description": "Removes the option to directly edit any files from within the WordPress admin area. Equivalent to setting 'DISALLOW_FILE_EDIT' to TRUE."
    },
    {
      "key": "force_ssl_admin",
      "section": "section_permission_access_options",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/4t",
      "link_blog": "",
      "name": "Force SSL Admin",
      "summary": "Forces WordPress Admin Dashboard To Be Delivered Over SSL",
      "description": "Please only enable this option if you have a valid SSL certificate installed. Equivalent to setting 'FORCE_SSL_ADMIN' to TRUE."
    },
    {
      "key": "mask_wordpress_version",
      "section": "section_wordpress_obscurity_options",
      "default": "",
      "type": "text",
      "link_info": "http://icwp.io/43",
      "link_blog": "",
      "name": "Mask WordPress Version",
      "summary": "Prevents Public Display Of Your WordPress Version",
      "description": "Enter how you would like your WordPress version displayed publicly. Leave blank to disable this feature. Warning: This may interfere with WordPress plugins that rely on the $wp_version variable."
    },
    {
      "key": "hide_wordpress_generator_tag",
      "section": "section_wordpress_obscurity_options",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "WP Generator Tag",
      "summary": "Remove WP Generator Meta Tag",
      "description": "Remove a meta tag from your WordPress pages that publicly displays that your site is WordPress and its current version."
    },
    {
      "key": "block_author_discovery",
      "section": "section_wordpress_obscurity_options",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/wpsf23",
      "link_blog": "",
      "name": "Block Username Fishing",
      "summary": "Block the ability to discover WordPress usernames based on author IDs",
      "description": "When enabled, any URL requests containing 'author=' will be killed. Warning: Enabling this option may interfere with expected operations of your site."
    }
  ]
}