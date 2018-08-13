{
  "slug": "traffic",
  "properties": {
    "slug": "traffic",
    "name": "Traffic Watch",
    "show_module_menu_item": true,
    "storage_key": "traffic",
    "tagline": "Watch All Requests To Your Site",
    "show_central": true,
    "access_restricted": true,
    "premium": true,
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
      "title": "Traffic Watch Options",
      "title_short": "Options",
      "summary": [
        "Purpose - Provides finer control over the live traffic system.",
        "Recommendation - These settings are dependent on your requirements."
      ]
    },
    {
      "slug": "section_enable_plugin_feature_traffic",
      "title": "Enable Module: Traffic Watch",
      "title_short": "Disable Module",
      "summary": [
        "Purpose - The Traffic Watch module lets you monitor and review all requests to your site.",
        "Recommendation - Required only if you need to review and investigate and monitor requests to your site."
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
      "name": "Enable Traffic Watch",
      "summary": "Enable (or Disable) The Traffic Watch Module",
      "description": "Un-Checking this option will completely disable the Traffic Watch module."
    },
    {
      "key": "type_exclusions",
      "section": "section_traffic_options",
      "type": "multiple_select",
      "default": [ "logged_in", "cron", "search", "uptime" ],
      "value_options": [
        {
          "value_key": "api",
          "text": "REST API"
        },
        {
          "value_key": "ajax",
          "text": "AJAX"
        },
        {
          "value_key": "logged_in",
          "text": "Logged-In Users"
        },
        {
          "value_key": "cron",
          "text": "WP CRON"
        },
        {
          "value_key": "search",
          "text": "Google Bot"
        },
        {
          "value_key": "uptime",
          "text": "Uptime Monitoring Services (e.g. Pingdom)"
        }
      ],
      "link_info": "",
      "link_blog": "",
      "name": "Traffic Log Exclusions",
      "summary": "Select Which Types Of Requests To Exclude",
      "description": "Deselect any requests that you don't want to appear in the traffic viewer."
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