{
  "properties":    {
    "slug":                  "plugin",
    "name":                  "General",
    "menu_title":            "Settings",
    "show_module_menu_item": true,
    "show_module_options":   true,
    "storage_key":           "plugin",
    "tagline":               "General Plugin Settings",
    "auto_enabled":          true,
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "has_custom_actions":    false,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   true,
    "order":                 10
  },
  "admin_notices": {
    "override-forceoff":          {
      "id":          "override-forceoff",
      "schedule":    "conditions",
      "valid_admin": true,
      "plugin_page_only": false,
      "can_dismiss": false,
      "type":        "error"
    },
    "plugin-update-available":    {
      "id":          "plugin-update-available",
      "schedule":    "conditions",
      "valid_admin": true,
      "type":        "warning"
    },
    "wizard_welcome":             {
      "id":          "wizard_welcome",
      "schedule":    "once",
      "valid_admin": true,
      "delay_days":  0,
      "type":        "promo"
    },
    "allow-tracking":             {
      "id":          "allow-tracking",
      "schedule":    "conditions",
      "valid_admin": true,
      "delay_days":  1,
      "type":        "promo"
    },
    "plugin-mailing-list-signup": {
      "id":          "plugin-mailing-list-signup",
      "schedule":    "once",
      "valid_admin": true,
      "delay_days":  15,
      "type":        "promo"
    },
    "rate-plugin":                {
      "id":          "rate-plugin",
      "schedule":    "once",
      "valid_admin": true,
      "delay_days":  30,
      "type":        "promo"
    }
  },
  "sections":      [
    {
      "slug":        "section_defaults",
      "primary":     true,
      "title":       "Plugin Defaults",
      "title_short": "Plugin Defaults"
    },
    {
      "slug":        "section_general_plugin_options",
      "title":       "General Plugin Options",
      "title_short": "General Options"
    },
    {
      "slug":        "section_importexport",
      "title":       "Import / Export",
      "title_short": "Import / Export"
    },
    {
      "slug":        "section_third_party_google",
      "title":       "Google",
      "title_short": "Google"
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
      "link_info":   "https://icwp.io/7i",
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
      "link_info":     "https://icwp.io/dn",
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
      "link_info":   "https://icwp.io/5v",
      "link_blog":   "https://icwp.io/wpsf20",
      "name":        "Show Plugin Badge",
      "summary":     "Display Plugin Badge On Your Site",
      "description": "Enabling this option helps support the plugin by spreading the word about it on your website. The plugin badge also demonstrates to visitors that you take your website security seriously."
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
      "link_info":   "https://icwp.io/do",
      "link_blog":   "https://icwp.io/dp",
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
      "key":           "google_recaptcha_style",
      "section":       "section_third_party_google",
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
          "text":      "Invisible reCAPTCHA"
        }
      ],
      "link_info":     "https://icwp.io/dq",
      "link_blog":     "",
      "name":          "reCAPTCHA Style",
      "summary":       "How Google reCAPTCHA Will Be Displayed By Default",
      "description":   "You can choose the reCAPTCHA display format that best suits your site, including the new Invisible Recaptcha."
    },
    {
      "key":         "google_recaptcha_site_key",
      "section":     "section_third_party_google",
      "sensitive":   true,
      "default":     "",
      "type":        "text",
      "link_info":   "https://icwp.io/shld5",
      "link_blog":   "",
      "name":        "reCAPTCHA Site Key",
      "summary":     "Google reCAPTCHA Site Key",
      "description": "Enter your Google reCAPTCHA site key for use throughout the plugin."
    },
    {
      "key":         "google_recaptcha_secret_key",
      "section":     "section_third_party_google",
      "sensitive":   true,
      "default":     "",
      "type":        "text",
      "link_info":   "https://icwp.io/shld5",
      "link_blog":   "",
      "name":        "reCAPTCHA Secret",
      "summary":     "Google reCAPTCHA Secret Key",
      "description": "Enter your Google reCAPTCHA secret key for use throughout the plugin."
    },
    {
      "key":          "tracking_last_sent_at",
      "transferable": false,
      "default":      0,
      "section":      "section_non_ui"
    },
    {
      "key":          "unique_installation_id",
      "section":      "section_non_ui",
      "transferable": false,
      "default":      ""
    },
    {
      "key":     "tracking_permission_set_at",
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key":          "installation_time",
      "transferable": false,
      "section":      "section_non_ui"
    },
    {
      "key":          "importexport_secretkey_expires_at",
      "transferable": false,
      "section":      "section_non_ui"
    },
    {
      "key":          "importexport_handshake_expires_at",
      "transferable": false,
      "section":      "section_non_ui"
    },
    {
      "key":          "importexport_last_import_hash",
      "transferable": false,
      "section":      "section_non_ui"
    },
    {
      "key":          "this_server_ip",
      "transferable": false,
      "sensitive":    true,
      "section":      "section_non_ui",
      "default":      ""
    },
    {
      "key":          "this_server_ip_last_check_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    },
    {
      "key":          "insights_test_cron_last_run_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    },
    {
      "key":          "last_ip_detect_source",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      ""
    }
  ],
  "definitions":   {
    "survey_email":           "c3VwcG9ydEBvbmVkb2xsYXJwbHVnaW4uY29t",
    "help_video_id":          "",
    "tracking_cron_handle":   "plugin_tracking_cron",
    "tracking_post_url":      "https://tracking.icontrolwp.com/track/plugin/shield",
    "importexport_cron_name": "autoimport",
    "href_privacy_policy":    "https://icwp.io/wpshieldprivacypolicy",
    "db_notes_name":          "notes",
    "db_notes_table_columns": [
      "id",
      "wp_username",
      "note",
      "created_at",
      "deleted_at"
    ],
    "active_plugin_features": [
      {
        "slug":          "insights",
        "storage_key":   "insights",
        "menu_priority": 5,
        "min_php":       "5.4"
      },
      {
        "slug":          "admin_access_restriction",
        "storage_key":   "admin_access_restriction",
        "load_priority": 20
      },
      {
        "slug":        "hack_protect",
        "storage_key": "hack_protect"
      },
      {
        "slug":        "login_protect",
        "storage_key": "loginprotect"
      },
      {
        "slug":          "firewall",
        "storage_key":   "firewall",
        "load_priority": 13
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
        "slug":          "ips",
        "storage_key":   "ips",
        "load_priority": 12
      },
      {
        "slug":          "statistics",
        "storage_key":   "statistics",
        "load_priority": 11,
        "hidden":        false,
        "min_php":       "5.4"
      },
      {
        "slug":          "sessions",
        "storage_key":   "sessions",
        "load_priority": 5
      },
      {
        "slug":          "audit_trail",
        "storage_key":   "audit_trail",
        "load_priority": 11,
        "hidden":        false
      },
      {
        "slug":          "traffic",
        "storage_key":   "traffic",
        "load_priority": 12,
        "min_php":       "5.4"
      },
      {
        "slug":          "license",
        "storage_key":   "license",
        "load_priority": 10
      },
      {
        "slug":        "email",
        "storage_key": "email"
      }
    ],
    "wizards":                {
      "welcome":      {
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
          "license":                  {
            "title": "Go Pro"
          },
          "import":                   {
            "title": "Import"
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
      },
      "gdpr":         {
        "title":                "GDPR Data Wizard",
        "desc":                 "Walks you through the searching and removal of personally identifiable data.",
        "min_user_permissions": "manage_options",
        "has_premium":          true,
        "steps":                {
          "start":    {
            "security_admin": false,
            "title":          "Start: GDPR Compliance"
          },
          "search":   {
            "title": "Input Search"
          },
          "results":  {
            "title": "Search Results"
          },
          "finished": {
            "security_admin": false,
            "title":          "Finished: GDPR Compliance"
          }
        }
      },
      "importexport": {
        "title":                "Options Import/Export Wizard",
        "desc":                 "Walks you through the import and export of options, as well as configuring ongoing automated options-sync.",
        "min_user_permissions": "manage_options",
        "has_premium":          true,
        "steps":                {
          "start":    {
            "security_admin": false,
            "title":          "Start: Options Import"
          },
          "import":   {
            "title": "Run Options Import"
          },
          "finished": {
            "security_admin": false,
            "title":          "Finished: Options Import"
          }
        }
      }
    }
  }
}