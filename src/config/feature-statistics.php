{
  "properties": {
    "slug": "statistics",
    "name": "Statistics",
    "show_feature_menu_item": false,
    "storage_key": "statistics",
    "tagline": "Summary of the main security actions taken by this plugin"
  },
  "sections": [
    {
      "slug": "section_enable_plugin_feature_statistics",
      "primary": true,
      "title": "Enable Plugin Feature: Statistics",
      "title_short": "Enable / Disable",
      "summary": [
        "Purpose - Helps you see at a glance how effective the plugin has been.",
        "Recommendation - Keep the Statistics feature turned on."
      ]
    },
    {
      "slug": "section_stats_sharing",
      "title": "Statistics Sharing",
      "title_short": "Sharing",
      "summary": [
        "Purpose - Help us to provide globally accessible statistics on the effectiveness of the plugin.",
        "Recommendation - Enabling this option helps us improve our plugin over time.All statistics data collection is 100% anonymous.Neither we nor anyone else will be able to trace the data back to the originating site."
      ]
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_statistics",
      "section": "section_enable_plugin_feature_statistics",
      "default": "Y",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Enable Statistics",
      "summary": "Enable (or Disable) The Statistics Feature",
      "description": "Checking/Un-Checking this option will completely turn on/off the whole Statistics feature"
    }
  ],
  "definitions": {
    "statistics_table_name": "statistics",
    "statistics_table_columns": [
      "id",
      "stat_key",
      "parent_stat_key",
      "tally",
      "created_at",
      "modified_at",
      "deleted_at"
    ]
  }
}