{
  "slug": "autoupdates",
  "properties": {
    "slug": "autoupdates",
    "name": "Automatic Updates",
    "show_feature_menu_item": true,
    "storage_key": "autoupdates",
    "tagline": "Take back full control of WordPress automatic updates",
    "show_central": true,
    "access_restricted": true,
    "premium": false,
    "order": 60
  },
  "sections": [
    {
      "slug": "section_enable_plugin_feature_automatic_updates_control",
      "primary": true,
      "title": "Enable Plugin Feature: Automatic Updates",
      "title_short": "Enable / Disable",
      "summary": [
        "Purpose - Automatic Updates lets you manage the WordPress automatic updates engine so you choose what exactly gets updated automatically.",
        "Recommendation - Keep the Automatic Updates feature turned on."
      ]
    },
    {
      "slug": "section_disable_all_wordpress_automatic_updates",
      "title": "Disable ALL WordPress Automatic Updates",
      "title_short": "Turn Off",
      "summary": [
        "Purpose - If you never want WordPress to automatically update anything on your site, turn on this option.",
        "Recommendation - Do not turn on this option unless you really need to block updates."
      ]
    },
    {
      "slug": "section_automatic_updates_for_wordpress_components",
      "title": "Automatic Updates For WordPress Components",
      "title_short": "WordPress Components",
      "summary": [
        "Purpose - Control how automatic updates for each WordPress component is handled.",
        "Recommendation - You should at least allow minor updates for the WordPress core."
      ]
    },
    {
      "slug": "section_automatic_update_email_notifications",
      "title": "Automatic Update Email Notifications",
      "title_short": "Notifications",
      "summary": "Purpose - Control how you are notified of automatic updates that have occurred."
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_autoupdates",
      "section": "section_enable_plugin_feature_automatic_updates_control",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/3w",
      "link_blog": "",
      "name": "Enable Automatic Updates",
      "summary": "Enable (or Disable) The Automatic Updates Feature",
      "description": "Checking/Un-Checking this option will completely turn on/off the whole Automatic Updates feature"
    },
    {
      "key": "enable_autoupdate_disable_all",
      "section": "section_disable_all_wordpress_automatic_updates",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/3v",
      "link_blog": "",
      "name": "Disable All",
      "summary": "Completely Disable WordPress Automatic Updates",
      "description": "When selected, regardless of any other settings, all WordPress automatic updates on this site will be completely disabled!"
    },
    {
      "key": "autoupdate_core",
      "section": "section_automatic_updates_for_wordpress_components",
      "default": "core_minor",
      "type": "select",
      "value_options": [
        {
          "value_key": "core_never",
          "text": "Never"
        },
        {
          "value_key": "core_minor",
          "text": "Minor Versions Only"
        },
        {
          "value_key": "core_major",
          "text": "Major and Minor Versions"
        }
      ],
      "link_info": "http://icwp.io/3x",
      "link_blog": "",
      "name": "WordPress Core Updates",
      "summary": "Decide how the WordPress Core will automatically update, if at all",
      "description": "At least automatically upgrading minor versions is recommended (and is the WordPress default)."
    },
    {
      "key": "enable_autoupdate_plugins",
      "section": "section_automatic_updates_for_wordpress_components",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Plugins",
      "summary": "Automatically Update Plugins",
      "description": "Note: Automatic updates for plugins are disabled on WordPress by default."
    },
    {
      "key": "enable_autoupdate_themes",
      "section": "section_automatic_updates_for_wordpress_components",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Themes",
      "summary": "Automatically Update Themes",
      "description": "Note: Automatic updates for themes are disabled on WordPress by default."
    },
    {
      "key": "enable_autoupdate_translations",
      "section": "section_automatic_updates_for_wordpress_components",
      "default": "Y",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Translations",
      "summary": "Automatically Update Translations",
      "description": "Note: Automatic updates for translations are enabled on WordPress by default."
    },
    {
      "key": "enable_autoupdate_ignore_vcs",
      "section": "section_automatic_updates_for_wordpress_components",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Ignore Version Control",
      "summary": "Ignore Version Control Systems Such As GIT and SVN",
      "description": "If you use SVN or GIT and WordPress detects it, automatic updates are disabled by default. Check this box to ignore version control systems and allow automatic updates."
    },
    {
      "key": "enable_upgrade_notification_email",
      "section": "section_automatic_update_email_notifications",
      "sensitive": true,
      "default": "",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Send Report Email",
      "summary": "Send email notices after automatic updates",
      "description": "You can turn on/off email notices from automatic updates by un/checking this box."
    },
    {
      "key": "override_email_address",
      "section": "section_automatic_update_email_notifications",
      "sensitive": true,
      "default": "",
      "type": "email",
      "link_info": "",
      "link_blog": "",
      "name": "Report Email Address",
      "summary": "Where to send upgrade notification reports",
      "description": "If this is empty, it will default to the Site Admin email address"
    }
  ],
  "definitions": {
    "action_hook_priority": 1000
  }
}