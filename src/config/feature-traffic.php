{
  "slug":         "traffic",
  "properties":   {
    "slug":                  "traffic",
    "name":                  "Traffic Watch",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "traffic",
    "tagline":               "Watch All Requests To Your Site",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               true,
    "run_if_whitelisted":    false,
    "run_if_verified_bot":   true,
    "run_if_wpcli":          false,
    "order":                 110
  },
  "requirements": {
    "php": {
      "version": "5.4"
    }
  },
  "sections":     [
    {
      "slug":        "section_traffic_options",
      "primary":     true,
      "title":       "Traffic Watch Options",
      "title_short": "Options",
      "summary":     [
        "Purpose - Provides finer control over the live traffic system.",
        "Recommendation - These settings are dependent on your requirements."
      ]
    },
    {
      "slug":        "section_traffic_limiter",
      "primary":     true,
      "title":       "Traffic Limiter",
      "title_short": "Options",
      "summary":     [
        "Purpose - Provides ability to restrict excessive requests from a single visitor.",
        "Recommendation - These settings are dependent on your requirements."
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_traffic",
      "title":       "Enable Module: Traffic Watch",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - The Traffic Watch module lets you monitor and review all requests to your site.",
        "Recommendation - Required only if you need to review and investigate and monitor requests to your site."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":      [
    {
      "key":         "enable_traffic",
      "section":     "section_enable_plugin_feature_traffic",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/ed",
      "link_blog":   "https://icwp.io/ee",
      "name":        "Enable Traffic Watch",
      "summary":     "Enable (or Disable) The Traffic Watch Module",
      "description": "Un-Checking this option will completely disable the Traffic Watch module."
    },
    {
      "key":           "type_exclusions",
      "section":       "section_traffic_options",
      "type":          "multiple_select",
      "default":       [
        "logged_in",
        "cron",
        "search",
        "uptime"
      ],
      "value_options": [
        {
          "value_key": "simple",
          "text":      "Simple Requests"
        },
        {
          "value_key": "api",
          "text":      "REST API"
        },
        {
          "value_key": "ajax",
          "text":      "AJAX"
        },
        {
          "value_key": "logged_in",
          "text":      "Logged-In Users"
        },
        {
          "value_key": "cron",
          "text":      "WP CRON"
        },
        {
          "value_key": "search",
          "text":      "Search Engines"
        },
        {
          "value_key": "uptime",
          "text":      "Uptime Monitoring Services"
        }
      ],
      "link_info":     "https://icwp.io/eb",
      "link_blog":     "",
      "name":          "Traffic Log Exclusions",
      "summary":       "Select Which Types Of Requests To Exclude",
      "description":   "Deselect any requests that you don't want to appear in the traffic viewer."
    },
    {
      "key":         "custom_exclusions",
      "section":     "section_traffic_options",
      "default":     [],
      "type":        "array",
      "link_info":   "https://icwp.io/ec",
      "link_blog":   "",
      "name":        "Custom Exclusions",
      "summary":     "Provide Custom Traffic Exclusions",
      "description": "For each entry, if the text is present in either the User Agent or Page/Path, it will be excluded."
    },
    {
      "key":         "auto_clean",
      "section":     "section_traffic_options",
      "default":     3,
      "min":         1,
      "type":        "integer",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Auto Expiry Cleaning",
      "summary":     "Enable Traffic Log Auto Expiry",
      "description": "Automated DB cleanup will delete logs older than this maximum value (in days)."
    },
    {
      "key":         "max_entries",
      "section":     "section_traffic_options",
      "default":     1000,
      "min":         0,
      "type":        "integer",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Max Log Length",
      "summary":     "Maximum Traffic Log Length To Keep",
      "description": "Automated DB cleanup will delete logs to maintain this maximum number of records."
    },
    {
      "key":         "auto_disable",
      "section":     "section_traffic_options",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Auto Disable",
      "summary":     "Auto Disable Traffic Logging After 1 Week",
      "description": "Turn on to prevent unnecessary long-term traffic logging. Timer resets each time you save."
    },
    {
      "key":         "limit_requests",
      "section":     "section_non_ui",
      "default":     "20",
      "min":         0,
      "type":        "integer",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Max Request Limit",
      "summary":     "Maximum Number Of Requests Allowed In Time Limit",
      "description": "The maximum number of requests that are allowed in the given time limit."
    },
    {
      "key":         "limit_time_span",
      "section":     "section_non_ui",
      "default":     "20",
      "min":         0,
      "type":        "integer",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Request Limit Time Interval",
      "summary":     "The Time Interval To Test For Excessive Requests",
      "description": "The time limit within which to monitor for excessive requests that exceed the limit."
    },
    {
      "key":          "autodisable_at",
      "section":      "section_non_ui",
      "type":         "integer",
      "transferable": false,
      "default":      0
    }
  ],
  "definitions":  {
    "traffic_table_name":    "traffic",
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
    ],
    "events":                {
      "request_limit_exceeded": {
        "cat":     3,
        "offense": true
      }
    }
  }
}