{
  "slug":             "audit_trail",
  "properties":       {
    "slug":                  "audit_trail",
    "name":                  "Audit Trail",
    "sidebar_name":          "Audit Trail",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "audit_trail",
    "tagline":               "Track All Site Activity: Who, What, When and Where",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   false,
    "run_if_wpcli":          true,
    "order":                 110
  },
  "menu_items":       [
    {
      "title": "Audit Trail",
      "slug":  "audit-redirect"
    }
  ],
  "custom_redirects": [
    {
      "source_mod_page": "audit-redirect",
      "target_mod_page": "insights",
      "query_args":      {
        "inav": "audit"
      }
    }
  ],
  "sections":         [
    {
      "slug":        "section_audit_trail_options",
      "primary":     true,
      "title":       "Audit Trail Options",
      "title_short": "Options",
      "summary":     [
        "Purpose - Provides finer control over the audit trail itself.",
        "Recommendation - These settings are dependent on your requirements."
      ]
    },
    {
      "slug":        "section_change_tracking",
      "hidden":      true,
      "title":       "Change Tracking",
      "title_short": "Change Tracking",
      "summary":     [
        "Purpose - Track significant changes to your site.",
        "Recommendation - Keep this Reporting feature turned on."
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_audit_trail",
      "title":       "Enable Module: Audit Trail",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - The Audit Trail is designed so you can look back on events and analyse what happened and what may have gone wrong.",
        "Recommendation - Keep the Audit Trail feature turned on."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":          [
    {
      "key":         "enable_audit_trail",
      "section":     "section_enable_plugin_feature_audit_trail",
      "advanced":    true,
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/5p",
      "link_blog":   "https://shsec.io/a1",
      "name":        "Enable Audit Trail",
      "summary":     "Enable (or Disable) The Audit Trail module",
      "description": "Un-Checking this option will completely disable the Audit Trail module"
    },
    {
      "key":         "audit_trail_auto_clean",
      "section":     "section_audit_trail_options",
      "default":     14,
      "min":         1,
      "type":        "integer",
      "link_info":   "https://shsec.io/a2",
      "link_blog":   "https://shsec.io/a1",
      "name":        "Auto Clean",
      "summary":     "Enable Audit Auto Cleaning",
      "description": "Events older than the number of days specified will be automatically cleaned from the database"
    },
    {
      "key":         "audit_trail_max_entries",
      "section":     "section_audit_trail_options",
      "premium":     true,
      "default":     1000,
      "min":         0,
      "type":        "integer",
      "link_info":   "https://shsec.io/hc",
      "link_blog":   "",
      "name":        "Max Trail Length",
      "summary":     "Maximum Audit Trail Length To Keep",
      "description": "Automatically remove any audit trail entries when this limit is exceeded."
    },
    {
      "key":           "enable_change_tracking",
      "section":       "section_change_tracking",
      "default":       "disabled",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "enabled",
          "text":      "Enabled"
        },
        {
          "value_key": "enabled_with_email",
          "text":      "Enabled With Email Reports"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Enable Change Tracking",
      "summary":       "Track Major Changes To Your Site",
      "description":   "Tracking major changes to your site will help you monitor and catch malicious damage."
    },
    {
      "key":         "ct_snapshots_per_week",
      "section":     "section_change_tracking",
      "type":        "integer",
      "default":     7,
      "min":         1,
      "link_info":   "",
      "link_blog":   "",
      "name":        "Snapshot Per Week",
      "summary":     "Number Of Snapshots To Take Per Week",
      "description": "The number of snapshots to take per week. For daily snapshots, select 7."
    },
    {
      "key":         "ct_max_snapshots",
      "section":     "section_change_tracking",
      "type":        "integer",
      "default":     28,
      "min":         1,
      "link_info":   "",
      "link_blog":   "",
      "name":        "Snapshot Per Week",
      "summary":     "Number Of Snapshots To Take Per Week",
      "description": "The number of snapshots to take per week. For daily snapshots, select 7."
    },
    {
      "key":          "ct_last_snapshot_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    }
  ],
  "definitions":      {
    "db_classes":                   {
      "audit_trail": "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\AuditTrail\\Handler"
    },
    "db_table_audit_trail":         {
      "slug":           "audit_trail",
      "has_updated_at": true,
      "cols_custom":    {
        "rid":         "varchar(10) NOT NULL DEFAULT '' COMMENT 'Request ID'",
        "ip":          "varchar(40) NOT NULL DEFAULT 0 COMMENT 'Visitor IP Address'",
        "wp_username": "varchar(255) NOT NULL DEFAULT '-' COMMENT 'WP User'",
        "context":     "varchar(32) NOT NULL DEFAULT 'none' COMMENT 'Audit Context'",
        "event":       "varchar(50) NOT NULL DEFAULT 'none' COMMENT 'Specific Audit Event'",
        "category":    "int(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Severity'",
        "meta":        "text COMMENT 'Audit Event Data'",
        "count":       "SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Repeat Count'"
      }
    },
    "audit_trail_free_max_entries": 100,
    "audit_trail_table_name":       "audit_trail",
    "events":                       {
      "plugin_activated":        {
        "context":        "plugins",
        "audit_multiple": true
      },
      "plugin_deactivated":      {
        "context":        "plugins",
        "audit_multiple": true
      },
      "plugin_file_edited":      {
        "context": "plugins"
      },
      "plugin_upgraded":         {
        "context":        "plugins",
        "audit_multiple": true
      },
      "theme_activated":         {
        "context": "themes"
      },
      "theme_file_edited":       {
        "context": "themes"
      },
      "theme_upgraded":          {
        "context":        "themes",
        "audit_multiple": true
      },
      "core_updated":            {
        "context": "wordpress"
      },
      "permalinks_structure":    {
        "context": "wordpress"
      },
      "post_deleted":            {
        "context":        "posts",
        "audit_multiple": true
      },
      "post_trashed":            {
        "context":        "posts",
        "audit_multiple": true
      },
      "post_recovered":          {
        "context":        "posts",
        "audit_multiple": true
      },
      "post_updated":            {
        "context":        "posts",
        "audit_multiple": true
      },
      "post_published":          {
        "context":        "posts",
        "audit_multiple": true
      },
      "post_unpublished":        {
        "context":        "posts",
        "audit_multiple": true
      },
      "user_login":              {
        "context": "users"
      },
      "user_login_app":          {
        "context": "users"
      },
      "user_registered":         {
        "context": "users"
      },
      "user_deleted":            {
        "context":        "users",
        "audit_multiple": true
      },
      "user_deleted_reassigned": {
        "context": "users"
      },
      "email_attempt_send":      {
        "context":        "emails",
        "audit_multiple": true
      },
      "email_send_invalid":      {
        "context":        "emails",
        "audit_multiple": true
      }
    }
  }
}