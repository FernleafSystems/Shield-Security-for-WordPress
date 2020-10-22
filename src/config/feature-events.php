{
  "properties":  {
    "slug":                  "events",
    "name":                  "Events",
    "show_module_menu_item": false,
    "storage_key":           "events",
    "tagline":               "Collection of plugin events and stats",
    "show_central":          false,
    "premium":               false,
    "access_restricted":     true,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   true,
    "run_if_wpcli":          true,
    "tracking_exclude":      true
  },
  "wpcli": {
    "enabled": false
  },
  "sections":    [
    {
      "slug":        "section_enable_plugin_feature_events",
      "primary":     true,
      "title":       "Enable Module: Events",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - Helps you see at a glance how effective the plugin has been.",
        "Recommendation - Keep the Events feature turned on."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":         "enable_events",
      "section":     "section_enable_plugin_feature_events",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Enable Events",
      "summary":     "Enable (or Disable) The Events module",
      "description": "Un-Checking this option will completely disable the Events module"
    }
  ],
  "definitions": {
    "db_classes":             {
      "events": "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\Events\\Handler"
    },
    "events_table_name":                 "events",
    "events_table_columns":              {
      "event": "varchar(50) NOT NULL DEFAULT 'none' COMMENT 'Event ID'",
      "count": "int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total'"
    }
  }
}