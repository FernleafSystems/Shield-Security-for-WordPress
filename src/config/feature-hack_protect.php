{
  "slug":             "hack_protect",
  "properties":       {
    "slug":                  "hack_protect",
    "name":                  "Hack Guard",
    "sidebar_name":          "Scanners",
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
  "menu_items":       [
    {
      "title":    "Scans",
      "slug":     "scans-redirect",
      "callback": ""
    }
  ],
  "custom_redirects": [
    {
      "source_mod_page": "scans-redirect",
      "target_mod_page": "insights",
      "query_args":      {
        "inav": "scans"
      }
    }
  ],
  "sections":         [
    {
      "slug":        "section_file_guard",
      "primary":     true,
      "title":       "File Guard",
      "title_short": "File Guard",
      "summary":     [
        "Purpose - Monitor WordPress files and protect against malicious intrusion and hacking.",
        "Recommendation - Keep the File Guard features turned on."
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
      "slug":        "section_realtime",
      "title":       "Realtime Change Detection",
      "title_short": "Realtime Change Detection",
      "summary":     [
        "Purpose - Monitor Your WordPress Site For Changes To Critical Components In Realtime.",
        "Recommendation - Keep The Realtime Change Detection Active."
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
      "slug":        "section_scan_options",
      "title":       "Scan Options",
      "title_short": "Scan Options",
      "summary":     [
        "Purpose - Set how often the Hack Guard scans will run."
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
  "options":          [
    {
      "key":         "enable_hack_protect",
      "section":     "section_enable_plugin_feature_hack_protection_tools",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/wpsf38",
      "link_blog":   "https://shsec.io/9x",
      "name":        "Enable Hack Guard",
      "summary":     "Enable (or Disable) The Hack Guard Module",
      "description": "Un-Checking this option will completely disable the Hack Guard module"
    },
    {
      "key":         "enabled_scan_apc",
      "section":     "section_scan_wpv",
      "type":        "checkbox",
      "default":     "Y",
      "link_info":   "https://shsec.io/ew",
      "link_blog":   "https://shsec.io/eo",
      "name":        "Abandoned Plugin Scanner",
      "summary":     "Enable The Abandoned Plugin Scanner",
      "description": "Scan your WordPress.org assets for whether they've been abandoned."
    },
    {
      "key":         "enable_wpvuln_scan",
      "section":     "section_scan_wpv",
      "premium":     true,
      "type":        "checkbox",
      "default":     "Y",
      "link_info":   "https://shsec.io/du",
      "link_blog":   "https://shsec.io/ah",
      "name":        "Vulnerability Scanner",
      "summary":     "Enable The Vulnerability Scanner",
      "description": "Scan all your WordPress assets for known security vulnerabilities."
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
      "key":         "enable_core_file_integrity_scan",
      "section":     "section_file_guard",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/wpsf36",
      "link_blog":   "https://shsec.io/wpsf37",
      "name":        "WP Core File Scanner",
      "summary":     "Automatically Scans WordPress Core Files For Alterations",
      "description": "Compares all WordPress core files on your site against the official WordPress files. WordPress Core files should never be altered for any reason."
    },
    {
      "key":         "mal_scan_enable",
      "section":     "section_file_guard",
      "premium":     true,
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/fp",
      "link_blog":   "https://shsec.io/fx",
      "name":        "Automatic Malware Scan",
      "summary":     "Enable Malware File Scanner",
      "description": "When enabled the Malware scanner will run automatically."
    },
    {
      "key":         "ptg_enable",
      "section":     "section_file_guard",
      "premium":     true,
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/bl",
      "link_blog":   "https://shsec.io/bm",
      "name":        "Enable/Disable Guard",
      "summary":     "Enable The Guard For Plugin And Theme Files",
      "description": "When enabled the Guard will automatically scan for changes to your Plugin and Theme files."
    },
    {
      "key":           "file_locker",
      "section":       "section_realtime",
      "premium":       true,
      "type":          "multiple_select",
      "default":       [],
      "value_options": [
        {
          "value_key": "wpconfig",
          "text":      "WP Config"
        },
        {
          "value_key": "root_htaccess",
          "text":      "Root .htaccess"
        },
        {
          "value_key": "root_index",
          "text":      "Root index.php"
        }
      ],
      "link_info":     "https://shsec.io/wpsf36",
      "link_blog":     "https://shsec.io/wpsf37",
      "name":          "File Locker",
      "summary":       "Lock Files Against Tampering and Changes",
      "description":   "As soon as changes are detected to any selected files, the contents may be examined and reverted."
    },
    {
      "key":           "file_repair_areas",
      "section":       "section_file_guard",
      "type":          "multiple_select",
      "default":       [
        "wp",
        "plugin"
      ],
      "value_options": [
        {
          "value_key": "wp",
          "text":      "WP Core"
        },
        {
          "value_key": "plugin",
          "text":      "Plugin Files"
        },
        {
          "value_key": "theme",
          "text":      "Theme Files"
        }
      ],
      "link_info":     "https://shsec.io/wpsf36",
      "link_blog":     "https://shsec.io/wpsf37",
      "name":          "Auto File Repair",
      "summary":       "Which Files Should Be Automatically Repaired?",
      "description":   "When a file is modified, or malware is detected, Shield can try to repair files."
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
      "link_info":     "https://shsec.io/b2",
      "link_blog":     "",
      "name":          "Scan Frequency",
      "summary":       "Number Of Times To Automatically Scan Core Files In 24 Hours",
      "description":   "Default: Once every 24hrs. To improve security, increase the number of scans per day."
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
          "text":      "Scan Enabled - Report Only"
        },
        {
          "value_key": "enabled_delete_only",
          "text":      "Scan Enabled - Automatically Delete Files"
        }
      ],
      "link_info":     "https://shsec.io/9y",
      "link_blog":     "https://shsec.io/95",
      "name":          "Unrecognised Files Scanner",
      "summary":       "Scans Core Directories For Unrecognised Files",
      "description":   "Scans for, and automatically deletes, any files in your core WordPress folders that are not part of your WordPress installation."
    },
    {
      "key":         "ufc_scan_uploads",
      "section":     "section_scan_ufc",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/95",
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
        "php_error_log",
        ".htaccess",
        ".htpasswd",
        ".user.ini",
        "php.ini",
        "web.config",
        "php_mail.log",
        "mail.log"
      ],
      "type":        "array",
      "link_info":   "https://shsec.io/9z",
      "link_blog":   "https://shsec.io/95",
      "name":        "File Exclusions",
      "summary":     "Provide A List Of Files To Be Excluded From The Scan",
      "description": "Take a new line for each file you wish to exclude from the scan. No commas are necessary."
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
      "key":         "ptg_reinstall_links",
      "section":     "section_scan_options",
      "premium":     true,
      "type":        "checkbox",
      "default":     "Y",
      "link_info":   "https://shsec.io/bp",
      "link_blog":   "",
      "name":        "Show Re-Install Links",
      "summary":     "Show Re-Install Links For Plugins",
      "description": "Show links to re-install plugins and offer re-install when activating plugins."
    },
    {
      "key":          "ptg_candiskwrite",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "boolean",
      "default":      false
    },
    {
      "key":              "ptg_candiskwrite_at",
      "section":          "section_non_ui",
      "transferable":     false,
      "tracking_exclude": true,
      "type":             "integer",
      "default":          false
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
      "key":              "scans_to_build",
      "section":          "section_non_ui",
      "transferable":     false,
      "tracking_exclude": true,
      "type":             "array",
      "default":          []
    },
    {
      "key":              "is_scan_cron",
      "section":          "section_non_ui",
      "transferable":     false,
      "tracking_exclude": true,
      "type":             "boolean",
      "default":          false
    },
    {
      "key":              "mal_fp_reports",
      "section":          "section_non_ui",
      "transferable":     false,
      "tracking_exclude": true,
      "type":             "array",
      "default":          []
    }
  ],
  "definitions":      {
    "db_classes":                  {
      "file_protect": "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\FileLocker\\Handler",
      "scanresults":  "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\Scanner\\Handler",
      "scanq":        "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\ScanQueue\\Handler"
    },
    "all_scan_slugs":              [
      "apc",
      "mal",
      "ptg",
      "wpv",
      "wcf",
      "ufc"
    ],
    "table_name_filelocker":       "filelocker",
    "table_columns_filelocker":    [
      "id",
      "file",
      "hash_original",
      "hash_current",
      "content",
      "public_key_id",
      "detected_at",
      "reverted_at",
      "notified_at",
      "updated_at",
      "created_at",
      "deleted_at"
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
      "wp-content/cache/",
      "wp-content/icwp/rollback/"
    ],
    "cron_all_scans":              "all-scans",
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