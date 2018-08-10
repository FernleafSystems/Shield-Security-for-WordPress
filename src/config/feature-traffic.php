{
  "slug": "traffic",
  "properties": {
    "slug": "traffic",
    "name": "Live Traffic",
    "show_module_menu_item": true,
    "storage_key": "traffic",
    "tagline": "Watch All Requests To Your Site",
    "show_central": true,
    "access_restricted": true,
    "premium": false,
    "has_custom_actions": true,
    "order": 110
  },
  "requirements": {
    "php": {
      "version": "5.4"
    }
  },
  "sections": [
    {
      "slug": "section_traffic_options",
      "primary": true,
      "title": "Live Traffic Options",
      "title_short": "Options",
      "summary": [
        "Purpose - Provides finer control over the live traffic system.",
        "Recommendation - These settings are dependent on your requirements."
      ]
    },
    {
      "slug": "section_enable_plugin_feature_traffic",
      "title": "Enable Module: Live Traffic",
      "title_short": "Disable Module",
      "summary": [
        "Purpose - The Live Traffic system is so you can review requests to your site.",
        "Recommendation - Turn on live traffic only if you need to review and investigate traffic."
      ]
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_traffic",
      "section": "section_enable_plugin_feature_traffic",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Enable Live Traffic",
      "summary": "Enable (or Disable) The Live Traffic Module",
      "description": "Un-Checking this option will completely disable the Live Traffic module"
    },
    {
      "key": "log_users",
      "section": "section_traffic_options",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Include Users",
      "summary": "Include Traffic From Logged-In Users",
      "description": "Check this option on to include traffic requests from logged-in users."
    },
    {
      "key": "auto_clean",
      "section": "section_traffic_options",
      "default": 3,
      "type": "integer",
      "link_info": "",
      "link_blog": "",
      "name": "Auto Clean",
      "summary": "Enable Traffic Log Auto Cleaning",
      "description": "Requests older than the number of days specified will be automatically cleaned from the database."
    },
    {
      "key": "max_entries",
      "section": "section_traffic_options",
      "default": 1000,
      "type": "integer",
      "link_info": "",
      "link_blog": "",
      "name": "Max Log Length",
      "summary": "Maximum Traffic Log Length To Keep",
      "description": "Automatically remove any traffic log entries when this limit is exceeded."
    }
  ],
  "definitions": {
    "default_per_page": 25,
    "default_max_entries": 1000,
    "traffic_table_name": "traffic",
    "traffic_table_columns": [
      "id",
      "rid",
      "uid",
      "ip",
      "path",
      "code",
      "ua",
      "verb",
      "trans",
      "created_at",
      "deleted_at"
    ]
  }
}