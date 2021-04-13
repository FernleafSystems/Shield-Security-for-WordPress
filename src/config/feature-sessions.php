{
  "properties":  {
    "slug":                  "sessions",
    "name":                  "Sessions",
    "show_module_menu_item": false,
    "storage_key":           "sessions",
    "tagline":               "User Sessions",
    "auto_enabled":          true,
    "show_central":          false,
    "premium":               false,
    "access_restricted":     true,
    "auto_load_processor":   true,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   true,
    "run_if_wpcli":          false,
    "tracking_exclude":      true
  },
  "wpcli":       {
    "enabled": false
  },
  "sections":    [
    {
      "slug":        "section_enable_plugin_feature_sessions",
      "primary":     true,
      "title":       "Enable Module: Sessions",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - Creates and Manages User Sessions.",
        "Recommendation - Keep the Sessions feature turned on."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":         "enable_sessions",
      "section":     "section_enable_plugin_feature_sessions",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Enable Sessions",
      "summary":     "Enable (or Disable) The Sessions module",
      "description": "Un-Checking this option will completely disable the Sessions module"
    },
    {
      "key":          "autoadd_sessions_started_at",
      "section":      "section_non_ui",
      "type":         "integer",
      "transferable": false,
      "default":      0
    }
  ],
  "definitions": {
    "db_classes":                       {
      "sessions": "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\Session\\Handler"
    },
    "sessions_table_name":              "sessions",
    "db_table_sessions":                {
      "slug":            "sessions",
      "cols_custom":     {
        "session_id":        "varchar(32) NOT NULL DEFAULT ''",
        "wp_username":       "varchar(255) NOT NULL DEFAULT ''",
        "ip":                "varchar(60) NOT NULL DEFAULT ''",
        "browser":           "varchar(32) NOT NULL DEFAULT ''",
        "last_activity_uri": "text NOT NULL DEFAULT ''"
      },
      "cols_timestamps": {
        "logged_in_at":            "Session Started",
        "last_activity_at":        "Last Seen At",
        "login_intent_expires_at": "2FA Window Expires",
        "secadmin_at":             "Security Admin Authenticated"
      }
    },
    "events":                           {
      "session_start":     {
        "audit": false
      },
      "session_terminate": {
        "audit":  false,
        "recent": true
      },
      "login_success":     {
        "offense": false,
        "audit":   false,
        "stat":    false
      }
    }
  }
}