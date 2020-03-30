{
  "properties":  {
    "slug":                  "reporting",
    "name":                  "Reporting",
    "storage_key":           "reporting",
    "tagline":               "Shield Reporting",
    "show_central":          true,
    "show_module_menu_item": true,
    "show_module_options":   true,
    "premium":               false,
    "access_restricted":     true,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   false,
    "run_if_wpcli":          true,
    "tracking_exclude":      true
  },
  "sections":    [
    {
      "slug":        "section_enable_mod_reporting",
      "primary":     true,
      "title":       "Enable Module: Reports",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - Helps you see at a glance how effective the plugin has been.",
        "Recommendation - Keep the Reporting feature turned on."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":         "enable_reporting",
      "section":     "section_enable_mod_reporting",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Enable Reporting",
      "summary":     "Enable (or Disable) The Reporting module",
      "description": "Un-Checking this option will completely disable the Reporting module"
    }
  ],
  "definitions": {
    "db_classes":             {
    },
    "events_table_name":                 "report",
    "events_table_columns":              [
      "id",
      "event",
      "count",
      "created_at",
      "deleted_at"
    ]
  }
}