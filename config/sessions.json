{
  "properties":  {
    "slug":                  "sessions",
    "load_priority":         5,
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
    }
  ],
  "definitions": {
    "events":              {
      "session_start":             {
        "audit_params": [
          "user_login",
          "session_id"
        ],
        "level":        "info"
      },
      "session_terminate":         {
        "level": "info"
      },
      "session_terminate_current": {
        "audit_params": [
          "user_login",
          "session_id"
        ],
        "level":        "info",
        "recent":       true
      },
      "login_success":             {
        "level":   "info",
        "offense": false,
        "stat":    false
      }
    }
  }
}