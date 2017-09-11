{
  "properties": {
    "slug": "plugin",
    "name": "Dashboard",
    "show_feature_menu_item": true,
    "storage_key": "plugin",
    "tagline": "Overview of the plugin settings",
    "show_central": true,
    "access_restricted": true,
    "is_premium": false,
    "order": 10
  },
  "admin_notices": {
    "override-forceoff": {
      "id": "override-forceoff",
      "schedule": "conditions",
      "valid_admin": true,
      "type": "error"
    },
    "php53-version-warning": {
      "id": "php53-version-warning",
      "schedule": "once",
      "valid_admin": true,
      "type": "warning"
    },
    "plugin-update-available": {
      "id": "plugin-update-available",
      "schedule": "version",
      "valid_admin": true,
      "type": "warning"
    },
    "allow-tracking": {
      "id": "allow-tracking",
      "schedule": "once",
      "valid_admin": true,
      "delay_days": 0,
      "type": "promo"
    },
    "plugin-mailing-list-signup": {
      "id": "plugin-mailing-list-signup",
      "schedule": "once",
      "valid_admin": true,
      "delay_days": 15,
      "type": "promo"
    },
    "rate-plugin": {
      "id": "rate-plugin",
      "schedule": "once",
      "valid_admin": true,
      "delay_days": 30,
      "type": "promo"
    },
    "translate-plugin": {
      "id": "translate-plugin",
      "schedule": "once",
      "valid_admin": true,
      "delay_days": 45,
      "type": "promo"
    }
  },
  "sections": [
    {
      "slug": "section_global_security_options",
      "primary": true,
      "title": "Global Plugin Security Options",
      "title_short": "Global Options"
    },
    {
      "slug": "section_general_plugin_options",
      "title": "General Plugin Options",
      "title_short": "General Options"
    },
    {
      "slug": "section_third_party_google",
      "title": "Google",
      "title_short": "Google"
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "global_enable_plugin_features",
      "section": "section_global_security_options",
      "default": "Y",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Enable Plugin Features",
      "summary": "Global Plugin On/Off Switch",
      "description": "Uncheck this option to disable all Shield features"
    },
    {
      "key": "enable_tracking",
      "section": "section_general_plugin_options",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/7i",
      "link_blog": "",
      "name": "Enable Information Gathering",
      "summary": "Permit Anonymous Usage Information Gathering",
      "description": "Allows us to gather information on statistics and features in-use across our client installations. This information is strictly anonymous and contains no personally, or otherwise, identifiable data."
    },
    {
      "key": "visitor_address_source",
      "section": "section_general_plugin_options",
      "sensitive": true,
      "type": "select",
      "default": "AUTO_DETECT_IP",
      "value_options": [
        {
          "value_key": "AUTO_DETECT_IP",
          "text": "Automatically Detect Visitor IP"
        },
        {
          "value_key": "REMOTE_ADDR",
          "text": "REMOTE_ADDR"
        },
        {
          "value_key": "HTTP_CF_CONNECTING_IP",
          "text": "HTTP_CF_CONNECTING_IP"
        },
        {
          "value_key": "HTTP_X_FORWARDED_FOR",
          "text": "HTTP_X_FORWARDED_FOR"
        },
        {
          "value_key": "HTTP_X_FORWARDED",
          "text": "HTTP_X_FORWARDED"
        },
        {
          "value_key": "HTTP_X_REAL_IP",
          "text": "HTTP_X_REAL_IP"
        },
        {
          "value_key": "HTTP_X_SUCURI_CLIENTIP",
          "text": "HTTP_X_SUCURI_CLIENTIP"
        },
        {
          "value_key": "HTTP_INCAP_CLIENT_IP",
          "text": "HTTP_INCAP_CLIENT_IP"
        },
        {
          "value_key": "HTTP_FORWARDED",
          "text": "HTTP_FORWARDED"
        },
        {
          "value_key": "HTTP_CLIENT_IP",
          "text": "HTTP_CLIENT_IP"
        }
      ],
      "link_info": "",
      "link_blog": "",
      "name": "Visitor IP Address",
      "summary": "Which Address Is Yours",
      "description": "There are many way to detect visitor IP addresses. Please select yours from the list."
    },
    {
      "key": "block_send_email_address",
      "section": "section_general_plugin_options",
      "sensitive": true,
      "default": "",
      "type": "email",
      "link_info": "",
      "link_blog": "",
      "name": "Report Email",
      "summary": "Where to send email reports",
      "description": "If this is empty, it will default to the blog admin email address."
    },
    {
      "key": "enable_upgrade_admin_notice",
      "section": "section_general_plugin_options",
      "default": "Y",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "In-Plugin Notices",
      "summary": "Display Plugin Specific Notices",
      "description": "Disable this option to hide certain plugin admin notices about available updates and post-update notices."
    },
    {
      "key": "display_plugin_badge",
      "section": "section_general_plugin_options",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/5v",
      "link_blog": "http://icwp.io/wpsf20",
      "name": "Show Plugin Badge",
      "summary": "Display Plugin Badge On Your Site",
      "description": "Enabling this option helps support the plugin by spreading the word about it on your website. The plugin badge also demonstrates to visitors that you take your website security seriously."
    },
    {
      "key": "delete_on_deactivate",
      "section": "section_general_plugin_options",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Delete Plugin Settings",
      "summary": "Delete All Plugin Settings Upon Plugin Deactivation",
      "description": "Careful: Removes all plugin options when you deactivate the plugin."
    },
    {
      "key": "unique_installation_id",
      "section": "section_general_plugin_options",
      "transferable": false,
      "default": "",
      "type": "noneditable_text",
      "link_info": "",
      "link_blog": "",
      "name": "Installation ID",
      "summary": "Unique Plugin Installation ID",
      "description": "Keep this ID private."
    },
    {
      "key": "google_recaptcha_site_key",
      "section": "section_third_party_google",
      "sensitive": true,
      "default": "",
      "type": "text",
      "link_info": "http://icwp.io/shld5",
      "link_blog": "",
      "name": "reCAPTCHA Secret",
      "summary": "Google reCAPTCHA Secret Key",
      "description": "Enter your Google reCAPTCHA secret key for use throughout the plugin."
    },
    {
      "key": "google_recaptcha_secret_key",
      "section": "section_third_party_google",
      "sensitive": true,
      "default": "",
      "type": "text",
      "link_info": "http://icwp.io/shld5",
      "link_blog": "",
      "name": "reCAPTCHA Site Key",
      "summary": "Google reCAPTCHA Site Key",
      "description": "Enter your Google reCAPTCHA site key for use throughout the plugin."
    },
    {
      "key": "tracking_last_sent_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "tracking_permission_set_at",
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "installation_time",
      "transferable": false,
      "section": "section_non_ui"
    }
  ],
  "definitions": {
    "help_video_id": "",
    "tracking_cron_handle": "plugin_tracking_cron",
    "tracking_post_url": "https://tracking.icontrolwp.com/track/plugin/shield",
    "active_plugin_features": [
      {
        "slug": "admin_access_restriction",
        "storage_key": "admin_access_restriction",
        "load_priority": 20
      },
      {
        "slug": "license",
        "storage_key": "license",
        "load_priority": 10
      },
      {
        "slug": "firewall",
        "storage_key": "firewall",
        "load_priority": 13
      },
      {
        "slug": "login_protect",
        "storage_key": "loginprotect"
      },
      {
        "slug": "user_management",
        "storage_key": "user_management"
      },
      {
        "slug": "comments_filter",
        "storage_key": "commentsfilter"
      },
      {
        "slug": "autoupdates",
        "storage_key": "autoupdates"
      },
      {
        "slug": "hack_protect",
        "storage_key": "hack_protect"
      },
      {
        "slug": "headers",
        "storage_key": "headers"
      },
      {
        "slug": "lockdown",
        "storage_key": "lockdown"
      },
      {
        "slug": "ips",
        "storage_key": "ips",
        "load_priority": 12
      },
      {
        "slug": "statistics",
        "storage_key": "statistics",
        "load_priority": 11,
        "hidden": false
      },
      {
        "slug": "audit_trail",
        "storage_key": "audit_trail",
        "load_priority": 11,
        "hidden": false
      },
      {
        "slug": "support",
        "storage_key": "support",
        "load_priority": 20,
        "hidden": false
      },
      {
        "slug": "email",
        "storage_key": "email"
      }
    ]
  }
}