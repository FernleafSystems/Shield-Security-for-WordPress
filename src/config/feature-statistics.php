{
  "properties":  {
    "slug":                  "statistics",
    "name":                  "Statistics",
    "show_module_menu_item": false,
    "storage_key":           "statistics",
    "tagline":               "Summary of the main security actions taken by this plugin",
    "show_central":          false,
    "premium":               false,
    "access_restricted":     true,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   false
  },
  "sections":    [
    {
      "slug":        "section_enable_plugin_feature_statistics",
      "primary":     true,
      "title":       "Enable Module: Statistics",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - Helps you see at a glance how effective the plugin has been.",
        "Recommendation - Keep the Statistics feature turned on."
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_reporting",
      "primary":     true,
      "title":       "Enable Module: Reporting",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - To track stats and issue reports.",
        "Recommendation - Keep the Reporting feature turned on."
      ]
    },
    {
      "slug":        "section_stats_sharing",
      "title":       "Statistics Sharing",
      "title_short": "Sharing",
      "summary":     [
        "Purpose - Help us to provide globally accessible statistics on the effectiveness of the plugin.",
        "Recommendation - Enabling this option helps us improve our plugin over time.All statistics data collection is 100% anonymous.Neither we nor anyone else will be able to trace the data back to the originating site."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":         "enable_statistics",
      "section":     "section_enable_plugin_feature_statistics",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Enable Statistics",
      "summary":     "Enable (or Disable) The Statistics module",
      "description": "Un-Checking this option will completely disable the Statistics module"
    },
    {
      "key":         "enable_reporting",
      "section":     "section_enable_plugin_feature_reporting",
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
    "statistics_table_name":             "statistics",
    "statistics_table_columns":          [
      "id",
      "stat_key",
      "parent_stat_key",
      "tally",
      "created_at",
      "modified_at",
      "deleted_at"
    ],
    "reporting_table_name":              "reporting",
    "reporting_consolidation_cron_name": "reporting-consolidation",
    "reporting_table_columns":           [
      "id",
      "stat_key",
      "tally",
      "created_at",
      "deleted_at"
    ]
  }
}