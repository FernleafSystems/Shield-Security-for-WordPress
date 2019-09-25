{
  "slug":        "hack_protect",
  "properties":  {
    "slug":                  "hack_protect",
    "name":                  "Hack Guard",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "hack_protect",
    "tagline":               "Automatically detect and repair vulnerable and suspicious items",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "order":                 70,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   true,
    "run_if_wpcli":          false
  },
  "menu_items":    [
    {
      "title": "Scans",
      "slug": "scans-redirect",
      "callback": ""
    }
  ],
  "custom_redirects":    [
    {
      "source_mod_page": "scans-redirect",
      "target_mod_page": "insights",
      "query_args": {
        "inav": "scans"
      }
    }
  ],
  "sections":    [
    {
      "slug":        "section_scan_options",
      "title":       "Scan Options",
      "title_short": "Schedule",
      "summary":     [
        "Purpose - Set how often the Hack Guard scans will run."
      ]
    },
    {
      "slug":        "section_scan_wcf",
      "primary":     true,
      "title":       "WordPress Core File Scanner",
      "title_short": "WP Core File Scanner",
      "summary":     [
        "Purpose - Regularly scan your WordPress core files for changes compared to official WordPress files.",
        "Recommendation - Keep the Core File Integrity Scanner feature turned on."
      ]
    },
    {
      "slug":        "section_scan_ufc",
      "title":       "Unrecognised Files Scanner",
      "title_short": "Unrecognised Files Scanner",
      "summary":     [
        "Purpose - Scan your WordPress core folders for unrecognised files that don't belong.",
        "Recommendation - Keep the Unrecognised Files Scanner feature turned on."
      ]
    },
    {
      "slug":        "section_scan_apc",
      "title":       "Abandoned Plugin Check",
      "title_short": "Abandoned Plugin Check",
      "summary":     [
        "Purpose - Regularly scan your WordPress plugins and themes for plugins that have been abandoned.",
        "Recommendation - Ensure this is turned on and you will always know if any of your assets have known security vulnerabilities."
      ]
    },
    {
      "slug":        "section_realtime",
      "title":       "Realtime Protection",
      "title_short": "Realtime Protection",
      "summary":     [
        "Purpose - Provides realtime protection for certain key files.",
        "Recommendation - Keep realtime protection turned on to protect key files."
      ]
    },
    {
      "slug":        "section_scan_mal",
      "title":       "Malware Scan",
      "title_short": "Malware Scan",
      "summary":     [
        "Purpose - Detect malicious changes to your themes and plugins.",
        "Recommendation - Keep the Plugins/Theme Guard feature turned on."
      ]
    },
    {
      "slug":        "section_scan_ptg",
      "help_video":  {
        "provider":  "vimeo",
        "embed_url": "https://player.vimeo.com/video/256755089?color=3fde23&byline=0",
        "id":        "256755089"
      },
      "title":       "Plugins/Themes Guard",
      "title_short": "Plugins/Themes Guard",
      "summary":     [
        "Purpose - Detect malicious changes to your themes and plugins.",
        "Recommendation - Keep the Plugins/Theme Guard feature turned on."
      ]
    },
    {
      "slug":        "section_scan_wpv",
      "title":       "Vulnerability Scanner",
      "title_short": "Vulnerability Scanner",
      "summary":     [
        "Purpose - Regularly scan your WordPress plugins and themes for known security vulnerabilities.",
        "Recommendation - Ensure this is turned on and you will always know if any of your assets have known security vulnerabilities."
      ]
    },
    {
      "slug":        "section_integrity_checking",
      "title":       "Integrity Checks",
      "title_short": "Integrity Checks",
      "summary":     [
        "Purpose - Monitor for unrecognised changes to your system.",
        "Recommendation - Enable these to automatically recover from unauthorized changes to your WordPress site."
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_hack_protection_tools",
      "title":       "Enable Module: Hack Guard",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - Hack Guard is a set of tools to warn you and protect you against hacks on your site.",
        "Recommendation - Keep the Hack Guard module turned on."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":         "enable_hack_protect",
      "section":     "section_enable_plugin_feature_hack_protection_tools",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/wpsf38",
      "link_blog":   "https://icwp.io/9x",
      "name":        "Enable Hack Guard",
      "summary":     "Enable (or Disable) The Hack Guard Module",
      "description": "Un-Checking this option will completely disable the Hack Guard module"
    },
    {
      "key":         "rt_file_wpconfig",
      "section":     "section_non_ui",
      "premium":     true,
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "WP Config",
      "summary":     "Lock WP Config Against Any Changes",
      "description": "As soon as changes are detected to the WP config file the file will be reverted."
    },
    {
      "key":           "enable_wpvuln_scan",
      "section":       "section_scan_wpv",
      "premium":       true,
      "default":       "enabled_email",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Automatic Scan Disabled"
        },
        {
          "value_key": "enabled_email",
          "text":      "Scan Enabled - Send Email Notification"
        },
        {
          "value_key": "enabled_no_email",
          "text":      "Scan Enabled - No Email Notification"
        }
      ],
      "link_info":     "https://icwp.io/du",
      "link_blog":     "https://icwp.io/ah",
      "name":          "Vulnerability Scanner",
      "summary":       "Enable The Vulnerability Scanner",
      "description":   "Scan all your WordPress assets for known security vulnerabilities."
    },
    {
      "key":         "wpvuln_scan_autoupdate",
      "section":     "section_scan_wpv",
      "premium":     true,
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Automatic Updates",
      "summary":     "Apply Updates Automatically To Vulnerable Plugins",
      "description": "When an update becomes available, automatically apply updates to items with known vulnerabilities."
    },
    {
      "key":           "wpvuln_scan_display",
      "section":       "section_scan_wpv",
      "default":       "enabled_admin",
      "premium":       true,
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Display Disabled"
        },
        {
          "value_key": "enabled_admin",
          "text":      "Display Enabled"
        },
        {
          "value_key": "enabled_securityadmin",
          "text":      "Display Only For Security Admins"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Highlight Plugins",
      "summary":       "Highlight Vulnerable Plugins",
      "description":   "Vulnerable plugins will be highlighted on the main plugins page."
    },
    {
      "key":           "enabled_scan_apc",
      "section":       "section_scan_apc",
      "default":       "enabled_email",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Automatic Scan Disabled"
        },
        {
          "value_key": "enabled_email",
          "text":      "Scan Enabled - Send Email Notification"
        },
        {
          "value_key": "enabled_no_email",
          "text":      "Scan Enabled - No Email Notification"
        }
      ],
      "link_info":     "https://icwp.io/ew",
      "link_blog":     "https://icwp.io/eo",
      "name":          "Abandoned Plugin Scanner",
      "summary":       "Enable The Abandoned Plugin Scanner",
      "description":   "Scan your WordPress.org assets for whether they've been abandoned."
    },
    {
      "key":         "enable_core_file_integrity_scan",
      "section":     "section_scan_wcf",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/wpsf36",
      "link_blog":   "https://icwp.io/wpsf37",
      "name":        "WP Core File Scanner",
      "summary":     "Automatically Scans WordPress Core Files For Alterations",
      "description": "Compares all WordPress core files on your site against the official WordPress files. WordPress Core files should never be altered for any reason."
    },
    {
      "key":         "attempt_auto_file_repair",
      "section":     "section_scan_wcf",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/wpsf36",
      "link_blog":   "https://icwp.io/wpsf37",
      "name":        "Auto Repair",
      "summary":     "Automatically Repair WordPress Core Files That Have Been Altered",
      "description": "Attempts to automatically repair WordPress Core files with the official WordPress file data, for files that have been altered or are missing."
    },
    {
      "key":           "scan_frequency",
      "section":       "section_scan_options",
      "premium":       true,
      "default":       1,
      "type":          "select",
      "value_options": [
        {
          "value_key": "1",
          "text":      "Once"
        },
        {
          "value_key": "2",
          "text":      "Twice (scan every 12hrs)"
        },
        {
          "value_key": "3",
          "text":      "3 Times (scan every 8hrs)"
        },
        {
          "value_key": "4",
          "text":      "4 Times (scan every 6hrs)"
        },
        {
          "value_key": "6",
          "text":      "6 Times (scan every 4hrs)"
        },
        {
          "value_key": "8",
          "text":      "8 Times (scan every 3hrs)"
        },
        {
          "value_key": "12",
          "text":      "12 Times (scan every 2hrs)"
        },
        {
          "value_key": "24",
          "text":      "24 Times (scan every hour)"
        }
      ],
      "link_info":     "https://icwp.io/b2",
      "link_blog":     "",
      "name":          "Scan Frequency",
      "summary":       "Number Of Times To Automatically Scan Core Files In 24 Hours",
      "description":   "Default: Once every 24hrs. To improve security, increase the number of scans per day."
    },
    {
      "key":         "notification_interval",
      "section":     "section_scan_options",
      "premium":     true,
      "default":     "7",
      "min":         0,
      "type":        "integer",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Repeat Notifications",
      "summary":     "Item Repeat Notifications Suppression Interval",
      "description": "How long the automated scans should wait before repeating a notification about an item."
    },
    {
      "key":         "email_files_list",
      "section":     "section_scan_options",
      "premium":     true,
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Email Files List",
      "summary":     "Scan Notification Emails Should Include Full Listing Of Files",
      "description": "Scanner notification emails will include a summary list of all affected files."
    },
    {
      "key":           "enable_unrecognised_file_cleaner_scan",
      "section":       "section_scan_ufc",
      "default":       "enabled_report_only",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Automatic Scan Disabled"
        },
        {
          "value_key": "enabled_report_only",
          "text":      "Scan Enabled - Send Email Notification"
        },
        {
          "value_key": "enabled_delete_only",
          "text":      "Scan Enabled - Automatically Delete Files"
        },
        {
          "value_key": "enabled_delete_report",
          "text":      "Scan Enabled - Delete Files and Send Email Notification"
        }
      ],
      "link_info":     "https://icwp.io/9y",
      "link_blog":     "https://icwp.io/95",
      "name":          "Unrecognised Files Scanner",
      "summary":       "Scans Core Directories For Unrecognised Files",
      "description":   "Scans for, and automatically deletes, any files in your core WordPress folders that are not part of your WordPress installation."
    },
    {
      "key":         "ufc_scan_uploads",
      "section":     "section_scan_ufc",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/95",
      "link_blog":   "",
      "name":        "Scan Uploads",
      "summary":     "Scan Uploads Folder For PHP and Javascript",
      "description": "The Uploads folder is primarily for media, but could be used to store nefarious files."
    },
    {
      "key":         "ufc_exclusions",
      "section":     "section_scan_ufc",
      "default":     [
        "error_log",
        ".htaccess",
        ".htpasswd",
        ".user.ini",
        "php.ini",
        "web.config",
        "php_mail.log",
        "mail.log"
      ],
      "type":        "array",
      "link_info":   "https://icwp.io/9z",
      "link_blog":   "https://icwp.io/95",
      "name":        "File Exclusions",
      "summary":     "Provide A List Of Files To Be Excluded From The Scan",
      "description": "Take a new line for each file you wish to exclude from the scan. No commas are necessary."
    },
    {
      "key":         "ic_enabled",
      "section":     "section_non_ui",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Enable Integrity Checking Scan",
      "summary":     "Scans For Critical Changes Made To Your WordPress Site",
      "description": "Detects changes made to your WordPress site outside of WordPress."
    },
    {
      "key":         "ic_users",
      "section":     "section_non_ui",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Monitor User Accounts",
      "summary":     "Scans For Critical Changes Made To User Accounts",
      "description": "Detects changes made to critical user account information that were made directly on the database and outside of the WordPress system."
    },
    {
      "key":           "mal_scan_enable",
      "section":       "section_scan_mal",
      "premium":       true,
      "default":       "disabled",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Automatic Scan Disabled"
        },
        {
          "value_key": "enabled",
          "text":      "Automatic Scan Enabled"
        }
      ],
      "link_info":     "https://icwp.io/fp",
      "link_blog":     "https://icwp.io/fx",
      "name":          "Automatic Malware Scan",
      "summary":       "Enable Malware File Scanner",
      "description":   "When enabled the Malware scanner will run automatically."
    },
    {
      "key":         "mal_autorepair_core",
      "section":     "section_scan_mal",
      "premium":     true,
      "type":        "checkbox",
      "default":     "N",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Auto-Repair WP Core",
      "summary":     "Automatically Repair WordPress Core Files",
      "description": "Automatically reinstall any core files found to have potential malware."
    },
    {
      "key":         "mal_autorepair_plugins",
      "section":     "section_scan_mal",
      "premium":     true,
      "type":        "checkbox",
      "default":     "N",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Auto-Repair WP Plugins",
      "summary":     "Automatically Repair WordPress.org Plugins",
      "description": "Automatically repair any plugin files found to have potential malware."
    },
    {
      "key":         "mal_autorepair_surgical",
      "section":     "section_non_ui",
      "premium":     true,
      "type":        "checkbox",
      "default":     "N",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Surgical Auto-Repair",
      "summary":     "Automatically Attempt To Surgically Remove Malware Code",
      "description": "Attempts to automatically remove code from infected files."
    },
    {
      "key":           "ptg_enable",
      "section":       "section_scan_ptg",
      "premium":       true,
      "default":       "disabled",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Scan Disabled"
        },
        {
          "value_key": "enabled",
          "text":      "Scan Enabled"
        }
      ],
      "link_info":     "https://icwp.io/bl",
      "link_blog":     "https://icwp.io/bm",
      "name":          "Enable/Disable Guard",
      "summary":       "Enable The Guard For Plugin And Theme Files",
      "description":   "When enabled the Guard will automatically scan for changes to your Plugin and Theme files."
    },
    {
      "key":         "ptg_depth",
      "section":     "section_scan_ptg",
      "premium":     true,
      "type":        "integer",
      "default":     1,
      "min":         0,
      "link_info":   "https://icwp.io/bn",
      "link_blog":   "https://icwp.io/bm",
      "name":        "Guard/Scan Depth",
      "summary":     "How Deep Into The Plugin Directories To Scan And Guard",
      "description": "The Guard normally operates scan only the top level of a plugin folder. Increasing depth increases scan times."
    },
    {
      "key":         "ptg_extensions",
      "section":     "section_scan_ptg",
      "premium":     true,
      "default":     [
        "php",
        "php5",
        "js",
        "svg",
        "htaccess"
      ],
      "type":        "array",
      "link_info":   "https://icwp.io/bo",
      "link_blog":   "",
      "name":        "File Types",
      "summary":     "The File Types Included In The Scan",
      "description": "Take a new line for each file extension. No commas(,) or periods(.) necessary."
    },
    {
      "key":         "ptg_reinstall_links",
      "section":     "section_scan_ptg",
      "premium":     true,
      "type":        "checkbox",
      "default":     "Y",
      "link_info":   "https://icwp.io/bp",
      "link_blog":   "",
      "name":        "Show Re-Install Links",
      "summary":     "Show Re-Install Links For Plugins",
      "description": "Show links to re-install plugins and offer re-install when activating plugins."
    },
    {
      "key":          "ptg_last_build_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "ptg_candiskwrite",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "boolean",
      "default":      false
    },
    {
      "key":          "ptg_candiskwrite_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      false
    },
    {
      "key":          "snapshot_users",
      "section":      "section_non_ui",
      "transferable": false,
      "sensitive":    true,
      "type":         "array",
      "default":      []
    },
    {
      "key":          "insights_last_scan_ufc_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "insights_last_scan_apc_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "insights_last_scan_wcf_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "insights_last_scan_ptg_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "insights_last_scan_wpv_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "rebuild_self",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "boolean",
      "default":      false
    },
    {
      "key":          "ptg_update_store_format",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "boolean",
      "default":      false
    },
    {
      "key":          "rt_file_hashes",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "array",
      "default":      []
    },
    {
      "key":          "rt_file_backup_names",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "array",
      "default":      []
    },
    {
      "key":          "rt_can_write_files",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "array",
      "default":      []
    },
    {
      "key":          "scans_to_build",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "array",
      "default":      []
    },
    {
      "key":          "is_scan_cron",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "boolean",
      "default":      false
    }
  ],
  "definitions": {
    "all_scan_slugs":              [
      "apc",
      "mal",
      "ptg",
      "wpv",
      "wcf",
      "ufc"
    ],
    "table_name_scanner":          "scanner",
    "table_columns_scanner":       [
      "id",
      "hash",
      "meta",
      "scan",
      "severity",
      "ignored_at",
      "notified_at",
      "created_at",
      "deleted_at"
    ],
    "table_name_scanqueue":        "scanq",
    "table_columns_scanqueue":     [
      "id",
      "scan",
      "items",
      "results",
      "meta",
      "started_at",
      "finished_at",
      "created_at",
      "deleted_at"
    ],
    "url_mal_sigs_simple":         "https://raw.githubusercontent.com/scr34m/php-malware-scanner/master/definitions/patterns_raw.txt",
    "url_mal_sigs_regex":          "https://raw.githubusercontent.com/scr34m/php-malware-scanner/master/definitions/patterns_re.txt",
    "malware_whitelist_paths":     [
      "wp-content/wflogs/",
      "wp-content/cache/"
    ],
    "cron_all_scans":              "all-scans",
    "url_checksum_api":            "https://api.wordpress.org/core/checksums/1.0/",
    "url_wordress_core_svn":       "https://core.svn.wordpress.org/",
    "url_wordress_core_svn_il8n":  "https://svn.automattic.com/wordpress-i18n/",
    "url_wp_hashes_api":           "https://wphashes/api/",
    "wpvulndb_api_url_root":       "https://wpvulndb.com/api/v2/",
    "wcf_exclusions":              [
      "readme.html",
      "license.txt",
      "licens-sv_SE.txt",
      "wp-config-sample.php",
      "wp-content/"
    ],
    "wcf_exclusions_missing_only": [
      "wp-admin/install.php",
      "xmlrpc.php"
    ],
    "corechecksum_autofix":        [
      "wp-content/index.php",
      "wp-content/plugins/index.php",
      "wp-content/themes/index.php"
    ],
    "events":                      {
      "apc_alert_sent":          {
      },
      "mal_alert_sent":          {
      },
      "ptg_alert_sent":          {
      },
      "ufc_alert_sent":          {
      },
      "wcf_alert_sent":          {
      },
      "wpv_alert_sent":          {
      },
      "apc_scan_run":            {
        "audit":  false,
        "recent": true
      },
      "mal_scan_run":            {
        "audit":  false,
        "recent": true
      },
      "ptg_scan_run":            {
        "audit":  false,
        "recent": true
      },
      "ufc_scan_run":            {
        "audit":  false,
        "recent": true
      },
      "wcf_scan_run":            {
        "audit":  false,
        "recent": true
      },
      "wpv_scan_run":            {
        "audit":  false,
        "recent": true
      },
      "apc_scan_found":          {
        "cat":    2,
        "recent": true
      },
      "mal_scan_found":          {
        "cat":    3,
        "recent": true
      },
      "ptg_scan_found":          {
        "cat":    3,
        "recent": true
      },
      "ufc_scan_found":          {
        "cat":    3,
        "recent": true
      },
      "wcf_scan_found":          {
        "cat":    3,
        "recent": true
      },
      "wpv_scan_found":          {
        "cat":    3,
        "recent": true
      },
      "apc_item_repair_success": {
      },
      "apc_item_repair_fail":    {
      },
      "mal_item_repair_success": {
        "recent": true
      },
      "mal_item_repair_fail":    {
      },
      "ptg_item_repair_success": {
      },
      "ptg_item_repair_fail":    {
      },
      "ufc_item_repair_success": {
        "recent": true
      },
      "ufc_item_repair_fail":    {
      },
      "wcf_item_repair_success": {
        "recent": true
      },
      "wcf_item_repair_fail":    {
      },
      "wpv_item_repair_success": {
      },
      "wpv_item_repair_fail":    {
      }
    }
  }
}