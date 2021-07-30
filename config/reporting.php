{
  "properties":       {
    "slug":                  "reporting",
    "name":                  "Reporting",
    "storage_key":           "reporting",
    "tagline":               "Shield Reporting",
    "show_central":          true,
    "show_module_menu_item": false,
    "show_module_options":   true,
    "premium":               false,
    "access_restricted":     true,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   false,
    "run_if_wpcli":          true,
    "tracking_exclude":      true
  },
  "menu_items":       [
    {
      "title": "Stats (beta)",
      "slug":  "stats-redirect"
    }
  ],
  "custom_redirects": [
    {
      "source_mod_page": "stats-redirect",
      "target_mod_page": "insights",
      "query_args":      {
        "inav": "reports"
      }
    }
  ],
  "sections":         [
    {
      "slug":        "section_timings",
      "primary":     true,
      "title":       "Report Frequencies",
      "title_short": "Report Frequencies",
      "beacon_id":   136,
      "summary":     [
        "Purpose - Choose the most appropriate frequency to receive alerts from Shield according to your schedule."
      ]
    },
    {
      "slug":        "section_enable_mod_reporting",
      "title":       "Enable Module: Reports",
      "title_short": "Disable Module",
      "beacon_id":   136,
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
  "options":          [
    {
      "key":         "enable_reporting",
      "section":     "section_enable_mod_reporting",
      "advanced":    true,
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/hb",
      "link_blog":   "",
      "beacon_id":   136,
      "name":        "Enable Reporting",
      "summary":     "Enable (or Disable) The Reporting module",
      "description": "Un-Checking this option will completely disable the Reporting module"
    },
    {
      "key":           "frequency_alert",
      "section":       "section_timings",
      "type":          "select",
      "default":       "daily",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "hourly",
          "text":      "Hourly"
        },
        {
          "value_key": "daily",
          "text":      "Daily"
        },
        {
          "value_key": "weekly",
          "text":      "Weekly"
        }
      ],
      "link_info":     "https://shsec.io/h9",
      "link_blog":     "",
      "beacon_id":     233,
      "name":          "Alert Frequency",
      "summary":       "How Often Should You Be Sent Important Alerts",
      "description":   "Decide when you should be sent important and critical alerts about your site security."
    },
    {
      "key":           "frequency_info",
      "section":       "section_timings",
      "type":          "select",
      "default":       "weekly",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "hourly",
          "text":      "Hourly"
        },
        {
          "value_key": "daily",
          "text":      "Daily"
        },
        {
          "value_key": "weekly",
          "text":      "Weekly"
        },
        {
          "value_key": "biweekly",
          "text":      "Bi-Weekly"
        },
        {
          "value_key": "monthly",
          "text":      "Monthly"
        }
      ],
      "link_info":     "https://shsec.io/ha",
      "link_blog":     "",
      "beacon_id":     232,
      "name":          "Info Frequency",
      "summary":       "How Often Should You Be Sent Information Reports",
      "description":   "Decide when you should be sent non-critical information and reports about your site security."
    }
  ],
  "definitions":      {
    "db_classes":       {
      "reports": "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\Reports\\Handler"
    },
    "db_table_reports": {
      "slug":            "reports",
      "autoexpire":      30,
      "cols_custom":     {
        "rid":       "int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Report ID'",
        "type":      "varchar(3) NOT NULL DEFAULT '' COMMENT 'Report Type'",
        "frequency": "varchar(10) NOT NULL DEFAULT '' COMMENT 'Report Interval/Frequency'"
      },
      "cols_timestamps": {
        "interval_end_at": "Reporting Interval End",
        "sent_at":         "Report Sent"
      }
    }
  }
}