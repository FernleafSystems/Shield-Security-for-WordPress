{
  "properties":    {
    "slug":                  "plugin",
    "name":                  "General Settings",
    "sidebar_name":          "General",
    "menu_title":            "Settings",
    "show_module_menu_item": true,
    "show_module_options":   true,
    "storage_key":           "plugin",
    "tagline":               "General Plugin Settings",
    "auto_enabled":          true,
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   true,
    "run_if_wpcli":          true,
    "order":                 10
  },
  "admin_notices": {
    "override-forceoff":          {
      "id":               "override-forceoff",
      "schedule":         "conditions",
      "valid_admin":      true,
      "plugin_page_only": false,
      "can_dismiss":      false,
      "type":             "error"
    },
    "plugin-disabled":            {
      "id":               "plugin-disabled",
      "schedule":         "conditions",
      "valid_admin":      true,
      "plugin_page_only": true,
      "can_dismiss":      false,
      "type":             "error"
    },
    "update-available":           {
      "id":               "update-available",
      "schedule":         "conditions",
      "valid_admin":      true,
      "plugin_page_only": true,
      "can_dismiss":      false,
      "type":             "error"
    },
    "php7":                       {
      "id":               "php7",
      "schedule":         "conditions",
      "valid_admin":      true,
      "plugin_page_only": false,
      "can_dismiss":      true,
      "type":             "warning"
    },
    "compat-sgoptimize":          {
      "id":               "compat-sgoptimize",
      "schedule":         "conditions",
      "valid_admin":      true,
      "plugin_admin":     "ignore",
      "plugin_page_only": false,
      "can_dismiss":      false,
      "type":             "warning"
    },
    "cloudflare-apo":             {
      "id":               "cloudflare-apo",
      "schedule":         "conditions",
      "valid_admin":      true,
      "plugin_page_only": true,
      "can_dismiss":      false,
      "type":             "error"
    },
    "wizard_welcome":             {
      "id":       "wizard_welcome",
      "per_user": false,
      "type":     "info"
    },
    "plugin-mailing-list-signup": {
      "id":               "plugin-mailing-list-signup",
      "min_install_days": 5,
      "type":             "promo",
      "drip_form_id":     "250437573"
    },
    "allow-tracking":             {
      "id":               "allow-tracking",
      "plugin_admin":     true,
      "min_install_days": 3,
      "type":             "promo"
    },
    "rate-plugin":                {
      "id":               "rate-plugin",
      "min_install_days": 30,
      "type":             "promo"
    }
  },
  "sections":      [
    {
      "slug":          "section_defaults",
      "primary":       true,
      "title":         "Plugin Defaults",
      "title_short":   "Plugin Defaults",
      "help_video_id": "338533495"
    },
    {
      "slug":          "section_general_plugin_options",
      "title":         "General Plugin Options",
      "title_short":   "General Options",
      "help_video_id": "338540386"
    },
    {
      "slug":          "section_third_party_captcha",
      "title":         "CAPTCHA",
      "title_short":   "CAPTCHA",
      "help_video_id": "338546796"
    },
    {
      "slug":        "section_importexport",
      "title":       "Import / Export",
      "title_short": "Import / Export"
    },
    {
      "slug":        "section_suresend",
      "title":       "SureSend Email",
      "title_short": "SureSend Email"
    },
    {
      "slug":        "section_global_security_options",
      "title":       "Global Plugin Security Options",
      "title_short": "Disable Shield"
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":       [
    {
      "key":         "global_enable_plugin_features",
      "section":     "section_global_security_options",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Enable/Disable All Plugin Modules",
      "summary":     "Global Plugin On/Off Switch",
      "description": "Uncheck this option to disable all Shield features"
    },
    {
      "key":         "enable_tracking",
      "section":     "section_general_plugin_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/7i",
      "link_blog":   "",
      "name":        "Enable Information Gathering",
      "summary":     "Permit Anonymous Usage Information Gathering",
      "description": "Allows us to gather information on statistics and features in-use across our client installations. This information is strictly anonymous and contains no personally, or otherwise, identifiable data."
    },
    {
      "key":           "visitor_address_source",
      "section":       "section_defaults",
      "sensitive":     false,
      "type":          "select",
      "default":       "AUTO_DETECT_IP",
      "value_options": [
        {
          "value_key": "AUTO_DETECT_IP",
          "text":      "Automatically Detect Visitor IP"
        },
        {
          "value_key": "REMOTE_ADDR",
          "text":      "REMOTE_ADDR"
        },
        {
          "value_key": "HTTP_CF_CONNECTING_IP",
          "text":      "HTTP_CF_CONNECTING_IP"
        },
        {
          "value_key": "HTTP_X_FORWARDED_FOR",
          "text":      "HTTP_X_FORWARDED_FOR"
        },
        {
          "value_key": "HTTP_X_FORWARDED",
          "text":      "HTTP_X_FORWARDED"
        },
        {
          "value_key": "HTTP_X_REAL_IP",
          "text":      "HTTP_X_REAL_IP"
        },
        {
          "value_key": "HTTP_X_SUCURI_CLIENTIP",
          "text":      "HTTP_X_SUCURI_CLIENTIP"
        },
        {
          "value_key": "HTTP_INCAP_CLIENT_IP",
          "text":      "HTTP_INCAP_CLIENT_IP"
        },
        {
          "value_key": "HTTP_X_SP_FORWARDED_IP",
          "text":      "HTTP_X_SP_FORWARDED_IP"
        },
        {
          "value_key": "HTTP_FORWARDED",
          "text":      "HTTP_FORWARDED"
        },
        {
          "value_key": "HTTP_CLIENT_IP",
          "text":      "HTTP_CLIENT_IP"
        }
      ],
      "link_info":     "https://shsec.io/dn",
      "link_blog":     "",
      "name":          "Visitor IP Address",
      "summary":       "Which Address Is Yours",
      "description":   "There are many way to detect visitor IP addresses. Please select yours from the list."
    },
    {
      "key":         "block_send_email_address",
      "section":     "section_defaults",
      "sensitive":   true,
      "default":     "",
      "type":        "email",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Report Email",
      "summary":     "Where to send email reports",
      "description": "If this is empty, it will default to the blog admin email address."
    },
    {
      "key":         "enable_upgrade_admin_notice",
      "section":     "section_general_plugin_options",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "In-Plugin Notices",
      "summary":     "Display Plugin Specific Notices",
      "description": "Disable this option to hide certain plugin admin notices about available updates and post-update notices."
    },
    {
      "key":         "display_plugin_badge",
      "section":     "section_general_plugin_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/5v",
      "link_blog":   "https://shsec.io/wpsf20",
      "name":        "Show Plugin Badge",
      "summary":     "Display Plugin Badge On Your Site",
      "description": "Enabling this option helps support the plugin by spreading the word about it on your website. The plugin badge also demonstrates to visitors that you take your website security seriously."
    },
    {
      "key":         "enable_wpcli",
      "section":     "section_general_plugin_options",
      "premium":     true,
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/i1",
      "link_blog":   "https://shsec.io/i2",
      "name":        "Allow WP-CLI",
      "summary":     "Allow Access And Control Of This Plugin Via WP-CLI",
      "description": "Turn off this option to disable this plugin's WP-CLI integration."
    },
    {
      "key":         "enable_xmlrpc_compatibility",
      "section":     "section_defaults",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "XML-RPC Compatibility",
      "summary":     "Allow Login Through XML-RPC To By-Pass Login Guard Rules",
      "description": "Enable this if you need XML-RPC functionality e.g. if you use the WordPress iPhone/Android App."
    },
    {
      "key":         "importexport_enable",
      "section":     "section_importexport",
      "premium":     true,
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/do",
      "link_blog":   "https://shsec.io/dp",
      "name":        "Allow Import/Export",
      "summary":     "Allow Import Of Options To, And Export Of Options From, This Site",
      "description": "Uncheck this box to completely disable import and export of options."
    },
    {
      "key":         "importexport_masterurl",
      "section":     "section_importexport",
      "default":     "",
      "type":        "text",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Auto-Import URL",
      "summary":     "Automatically Import Options From This Site",
      "description": "Supplying a valid site URL here will make this site an 'Options Slave' and will automatically import options daily."
    },
    {
      "key":          "importexport_whitelist",
      "section":      "section_importexport",
      "transferable": false,
      "sensitive":    true,
      "default":      [],
      "type":         "array",
      "link_info":    "",
      "link_blog":    "",
      "name":         "Export Whitelist",
      "summary":      "Whitelisted Sites Which Do Not Need The Secret Key To Export Options",
      "description":  "Each site on this list will be able to export options from this site without providing the secret key. Take a new line for each URL."
    },
    {
      "key":         "importexport_whitelist_notify",
      "section":     "section_importexport",
      "sensitive":   true,
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Notify Whitelist",
      "summary":     "Notify Sites On The Whitelist To Update Options From Master",
      "description": "When enabled, manual options saving will notify sites on the whitelist to export options from the Master site."
    },
    {
      "key":          "importexport_secretkey",
      "section":      "section_importexport",
      "transferable": false,
      "sensitive":    true,
      "default":      "",
      "type":         "noneditable_text",
      "link_info":    "",
      "link_blog":    "",
      "name":         "Secret Key",
      "summary":      "Import/Export Secret Key",
      "description":  "Keep this Secret Key private as it will allow the import and export of options."
    },
    {
      "key":         "delete_on_deactivate",
      "section":     "section_general_plugin_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Delete Plugin Settings",
      "summary":     "Delete All Plugin Settings Upon Plugin Deactivation",
      "description": "Careful: Removes all plugin options when you deactivate the plugin."
    },
    {
      "key":           "captcha_provider",
      "section":       "section_third_party_captcha",
      "default":       "grecaptcha",
      "type":          "select",
      "value_options": [
        {
          "value_key": "grecaptcha",
          "text":      "Google reCAPTCHA v2"
        },
        {
          "value_key": "hcaptcha",
          "text":      "hCaptcha"
        }
      ],
      "link_info":     "https://shsec.io/dq",
      "link_blog":     "",
      "name":          "CAPTCHA Provider",
      "summary":       "Which CAPTCHA Provider To Use Throughout",
      "description":   "You can choose the CAPTCHA provider depending on your preferences."
    },
    {
      "key":           "google_recaptcha_style",
      "section":       "section_third_party_captcha",
      "premium":       true,
      "default":       "light",
      "type":          "select",
      "value_options": [
        {
          "value_key": "light",
          "text":      "Light Theme"
        },
        {
          "value_key": "dark",
          "text":      "Dark Theme"
        },
        {
          "value_key": "invisible",
          "text":      "Invisible"
        }
      ],
      "link_info":     "https://shsec.io/dq",
      "link_blog":     "",
      "name":          "CAPTCHA Type",
      "summary":       "How Google reCAPTCHA Will Be Displayed By Default",
      "description":   "You can choose the reCAPTCHA display format that best suits your site, including the new Invisible Recaptcha."
    },
    {
      "key":         "google_recaptcha_site_key",
      "section":     "section_third_party_captcha",
      "sensitive":   true,
      "default":     "",
      "type":        "text",
      "link_info":   "https://shsec.io/shld5",
      "link_blog":   "",
      "name":        "reCAPTCHA Site Key",
      "summary":     "Google reCAPTCHA Site Key - Only v2 or Invisible. v3 NOT supported.",
      "description": "Enter your Google reCAPTCHA site key for use throughout the plugin."
    },
    {
      "key":         "google_recaptcha_secret_key",
      "section":     "section_third_party_captcha",
      "sensitive":   true,
      "default":     "",
      "type":        "text",
      "link_info":   "https://shsec.io/shld5",
      "link_blog":   "",
      "name":        "reCAPTCHA Secret",
      "summary":     "Google reCAPTCHA Secret Key - Only v2 or Invisible. v3 NOT supported.",
      "description": "Enter your Google reCAPTCHA secret key for use throughout the plugin."
    },
    {
      "key":           "suresend_emails",
      "section":       "section_suresend",
      "type":          "multiple_select",
      "premium":       true,
      "default":       [],
      "value_options": [
        {
          "value_key": "2fa",
          "text":      "2FA Login Codes (admins only)"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "SureSend Emails",
      "summary":       "SureSend Emails",
      "description":   "SureSend Emails."
    },
    {
      "key":          "tracking_last_sent_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0,
      "min":          0
    },
    {
      "key":          "unique_installation_id",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "text",
      "default":      0
    },
    {
      "key":     "tracking_permission_set_at",
      "section": "section_non_ui",
      "type":    "integer",
      "default": 0
    },
    {
      "key":          "installation_time",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "activated_at",
      "transferable": false,
      "section":      "section_non_ui",
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "importexport_secretkey_expires_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "importexport_handshake_expires_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "last_ip_detect_source",
      "transferable": false,
      "section":      "section_non_ui",
      "type":         "text",
      "default":      ""
    },
    {
      "key":          "openssl_private_key",
      "transferable": false,
      "sensitive":    true,
      "section":      "section_non_ui",
      "type":         "text",
      "default":      ""
    },
    {
      "key":          "snapi_data",
      "transferable": false,
      "sensitive":    true,
      "section":      "section_non_ui",
      "type":         "array",
      "default":      []
    },
    {
      "key":          "captcha_checked_at",
      "transferable": false,
      "section":      "section_non_ui",
      "type":         "int",
      "default":      -1
    },
    {
      "key":          "cache_dir_write_test",
      "transferable": false,
      "section":      "section_non_ui",
      "type":         "array",
      "default":      []
    }
  ],
  "definitions":   {
    "survey_email":           "c3VwcG9ydEBvbmVkb2xsYXJwbHVnaW4uY29t",
    "help_video_id":          "",
    "tracking_cron_handle":   "plugin_tracking_cron",
    "tracking_post_url":      "https://tracking.icontrolwp.com/track/plugin/shield",
    "importexport_cron_name": "autoimport",
    "href_privacy_policy":    "https://shsec.io/wpshieldprivacypolicy",
    "db_classes":             {
      "geoip": "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\GeoIp\\Handler",
      "notes": "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\AdminNotes\\Handler"
    },
    "db_autoexpire_notes":    0,
    "db_autoexpire_geoip":    30,
    "db_notes_name":          "notes",
    "db_notes_table_columns": [
      "id",
      "wp_username",
      "note",
      "created_at",
      "deleted_at"
    ],
    "geoip_table_name":       "geoip",
    "geoip_table_columns":    [
      "id",
      "ip",
      "meta",
      "created_at",
      "deleted_at"
    ],
    "active_plugin_features": [
      {
        "slug":          "insights",
        "storage_key":   "insights",
        "load_priority": 1,
        "menu_priority": 5
      },
      {
        "slug":          "admin_access_restriction",
        "storage_key":   "admin_access_restriction",
        "load_priority": 11
      },
      {
        "slug":          "ips",
        "storage_key":   "ips",
        "load_priority": 15
      },
      {
        "slug":          "audit_trail",
        "storage_key":   "audit_trail",
        "load_priority": 11,
        "hidden":        false
      },
      {
        "slug":        "hack_protect",
        "storage_key": "hack_protect"
      },
      {
        "slug":          "traffic",
        "storage_key":   "traffic",
        "load_priority": 12,
        "min_php":       "5.4"
      },
      {
        "slug":          "firewall",
        "storage_key":   "firewall",
        "load_priority": 1000
      },
      {
        "slug":        "login_protect",
        "storage_key": "loginprotect"
      },
      {
        "slug":        "user_management",
        "storage_key": "user_management"
      },
      {
        "slug":        "comments_filter",
        "storage_key": "commentsfilter"
      },
      {
        "slug":          "events",
        "storage_key":   "events",
        "load_priority": 11
      },
      {
        "slug":          "reporting",
        "storage_key":   "reporting",
        "load_priority": 12
      },
      {
        "slug":          "sessions",
        "storage_key":   "sessions",
        "load_priority": 5
      },
      {
        "slug":          "license",
        "storage_key":   "license",
        "load_priority": 10
      },
      {
        "slug":        "autoupdates",
        "storage_key": "autoupdates"
      },
      {
        "slug":        "headers",
        "storage_key": "headers"
      },
      {
        "slug":        "lockdown",
        "storage_key": "lockdown"
      },
      {
        "slug":        "email",
        "storage_key": "email"
      }
    ],
    "events":                 {
      "test_cron_run":          {
        "audit":  false,
        "recent": true
      },
      "suresend_success":       {
      },
      "suresend_fail":          {
      },
      "import_notify_sent":     {
        "stat": false
      },
      "import_notify_received": {
        "stat": false
      },
      "options_exported":       {
        "stat":   true,
        "recent": true
      },
      "options_imported":       {
        "stat":   true,
        "recent": true
      },
      "whitelist_site_added":   {
        "stat": false
      },
      "whitelist_site_removed": {
        "stat": false
      },
      "master_url_set":         {
        "stat": false
      },
      "recaptcha_success":      {
        "audit": false
      },
      "recaptcha_fail":         {
        "audit": true
      }
    },
    "wizards":                {
      "welcome": {
        "title":                "Getting Started Setup Wizard",
        "desc":                 "An introduction to this security plugin, helping you get setup and started quickly with the core features.",
        "min_user_permissions": "manage_options",
        "steps":                {
          "welcome":                  {
            "security_admin": false,
            "title":          "Welcome"
          },
          "ip_detect":                {
            "title": "IP Detection"
          },
          "admin_access_restriction": {
            "title": "Security Admin"
          },
          "audit_trail":              {
            "title": "Audit Trail"
          },
          "ips":                      {
            "title": "IP Blacklist"
          },
          "login_protect":            {
            "title": "Login Protection"
          },
          "comments_filter":          {
            "title": "Comment SPAM"
          },
          "how_shield_works":         {
            "title": "How Shield Works"
          },
          "optin":                    {
            "title": "Join Us!"
          },
          "thankyou":                 {
            "security_admin": false,
            "title":          "Thank You!"
          }
        }
      }
    }
  }
}