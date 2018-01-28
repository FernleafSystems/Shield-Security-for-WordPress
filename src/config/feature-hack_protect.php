{
  "slug":        "hack_protect",
  "properties":  {
    "slug":                   "hack_protect",
    "name":                   "Hack Protection",
    "show_module_menu_item": false,
    "storage_key":            "hack_protect",
    "tagline": "Automatically detect and repair vulnerable and suspicious items",
    "show_central":           true,
    "access_restricted":      true,
    "premium":                false,
    "order":                  70
  },
  "sections":    [
    {
      "slug":        "section_core_file_integrity_scan",
      "primary":     true,
      "title":       "Core File Integrity Scanner",
      "title_short": "Core File Scanner",
      "summary":     [
        "Purpose - Regularly scan your WordPress core files for changes compared to official WordPress files.",
        "Recommendation - Keep the Core File Integrity Scanner feature turned on."
      ]
    },
    {
      "slug":        "section_unrecognised_file_scan",
      "title":       "Unrecognised Files Scanner",
      "title_short": "Unrecognised Files Scanner",
      "summary":     [
        "Purpose - Scan your WordPress core folders for unrecognised files that don't belong.",
        "Recommendation - Keep the Unrecognised Files Scanner feature turned on."
      ]
    },
    {
      "slug":        "section_wpvuln_scan",
      "title":       "Vulnerability Scanner",
      "title_short": "Vulnerability Scanner",
      "summary":     [
        "Purpose - Regularly scan your WordPress plugins and themes for known security vulnerabilities.",
        "Recommendation - Ensure this is turned on and you will always know if any of your assets have known security vulnerabilities."
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_hack_protection_tools",
      "title":       "Enable Plugin Feature: Hack Protection",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - The Hack Protection system is a set of tools to warn you and protect you against hacks on your site.",
        "Recommendation - Keep the Hack Protection feature turned on."
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
      "link_info":   "http://icwp.io/wpsf38",
      "link_blog":   "http://icwp.io/9x",
      "name":        "Enable Hack Protection",
      "summary":     "Enable (or Disable) The Hack Protection module",
      "description": "Un-Checking this option will completely disable the Hack Protection module"
    },
    {
      "key":           "enable_wpvuln_scan",
      "section":       "section_wpvuln_scan",
      "premium":       true,
      "default":       "enabled_email",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Scan Disabled"
        },
        {
          "value_key": "enabled_email",
          "text":      "Enabled - Send Email Notification"
        },
        {
          "value_key": "enabled_no_email",
          "text":      "Enabled - No Email Notification"
        }
      ],
      "link_info":     "http://icwp.io/ah",
      "link_blog":     "",
      "name":          "Vulnerability Scanner",
      "summary":       "Enable The Vulnerability Scanner",
      "description":   "Scan all your WordPress assets for known security vulnerabilities."
    },
    {
      "key":         "wpvuln_scan_autoupdate",
      "section":     "section_wpvuln_scan",
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
      "section":       "section_wpvuln_scan",
      "default":       "enabled_admin",
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
      "key":         "enable_core_file_integrity_scan",
      "section":     "section_core_file_integrity_scan",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "http://icwp.io/wpsf36",
      "link_blog":   "http://icwp.io/wpsf37",
      "name":        "Core File Scanner",
      "summary":     "Scans WordPress Core Files For Alterations",
      "description": "Compares all WordPress core files on your site against the official WordPress files. WordPress Core files should never be altered for any reason."
    },
    {
      "key":         "attempt_auto_file_repair",
      "section":     "section_core_file_integrity_scan",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "http://icwp.io/wpsf36",
      "link_blog":   "http://icwp.io/wpsf37",
      "name":        "Auto Repair",
      "summary":     "Automatically Repair WordPress Core Files That Have Been Altered",
      "description": "Attempts to automatically repair WordPress Core files with the official WordPress file data, for files that have been altered or are missing."
    },
    {
      "key":         "scan_frequency",
      "section":     "section_enable_plugin_feature_hack_protection_tools",
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
      "link_info":   "http://icwp.io/b2",
      "link_blog":   "",
      "name":        "Daily Frequency",
      "summary":     "Number Of Times To Automatically Scan Core Files In 24 Hours",
      "description": "Default: Once every 24hrs. To improve security, increase the number of scans per day."
    },
    {
      "key":           "enable_unrecognised_file_cleaner_scan",
      "section":       "section_unrecognised_file_scan",
      "default":       "enabled_report_only",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Scan Disabled"
        },
        {
          "value_key": "enabled_report_only",
          "text":      "Email Report Only"
        },
        {
          "value_key": "enabled_delete_only",
          "text":      "Automatically Delete Files"
        },
        {
          "value_key": "enabled_delete_report",
          "text":      "Auto Delete Files and Email Report"
        }
      ],
      "link_info":     "http://icwp.io/9y",
      "link_blog":     "http://icwp.io/95",
      "name":          "Unrecognised Files Scanner",
      "summary":       "Scans Core Directories For Unrecognised Files",
      "description":   "Scans for, and automatically deletes, any files in your core WordPress folders that are not part of your WordPress installation."
    },
    {
      "key":         "ufc_scan_uploads",
      "section":     "section_unrecognised_file_scan",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "http://icwp.io/95",
      "link_blog":   "",
      "name":        "Scan Uploads",
      "summary":     "Scan Uploads Folder For PHP and Javascript",
      "description": "The Uploads folder is primarily for media, but could be used to store nefarious files."
    },
    {
      "key":         "ufc_exclusions",
      "section":     "section_unrecognised_file_scan",
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
      "link_info":   "http://icwp.io/9z",
      "link_blog":   "http://icwp.io/95",
      "name":        "File Exclusions",
      "summary":     "Provide A List Of Files To Be Excluded From The Scan",
      "description": "Take a new line for each file you wish to exclude from the scan. No commas are necessary."
    },
    {
      "key":          "wpvuln_notified_ids",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      []
    }
  ],
  "definitions": {
    "plugin_vulnerabilities_data_source":   "https://raw.githubusercontent.com/FernleafSystems/wp-plugin-vulnerabilities/master/vulnerabilities.yaml",
    "notifications_cron_name":              "plugin-vulnerabilities-notification",
    "wpvulnscan_cron_name":                 "wpvulnscan-notification",
    "corechecksum_cron_name":               "core-checksum-notification",
    "unrecognisedscan_cron_name":           "unrecognised-scan-notification",
    "url_checksum_api":                     "https://api.wordpress.org/core/checksums/1.0/",
    "url_wordress_core_svn":                "https://core.svn.wordpress.org/",
    "url_wordress_core_svn_il8n":           "https://svn.automattic.com/wordpress-i18n/",
    "wpvulndb_api_url_root":                "https://wpvulndb.com/api/v2/",
    "corechecksum_exclusions":              [
      "readme.html",
      "license.txt",
      "licens-sv_SE.txt",
      "wp-config-sample.php",
      "wp-content/"
    ],
    "corechecksum_exclusions_missing_only": [
      "wp-admin/install.php",
      "xmlrpc.php"
    ],
    "corechecksum_autofix":                 [
      "wp-content/index.php",
      "wp-content/plugins/index.php",
      "wp-content/themes/index.php"
    ],
    "wizards":                              {
      "ufc": {
        "title": "Manually Run Unrecognised File Scanner",
        "desc": "Walks you through the scanning for unrecognised files present in your WordPress core installation.",
        "min_user_permissions": "manage_options",
        "steps":                {
          "start":      {
            "security_admin": false,
            "title":             "Start: Unrecognised File Scanner"
          },
          "exclusions": {
            "title": "Exclude Files"
          },
          "scanresult": {
            "title": "Scan Results"
          },
          "config":     {
            "title": "Setup Scan Automation"
          },
          "finished":   {
            "security_admin": false,
            "title":             "Finished: Unrecognised File Scanner"
          }
        }
      },
      "wcf": {
        "title": "Manually Run WordPress Core File Scanner",
        "desc": "Walks you through the scanning for unintended changes to your official WordPress core files.",
        "min_user_permissions": "manage_options",
        "steps":                {
          "start":      {
            "security_admin": false,
            "title":             "Start: WordPress Core File Scanner"
          },
          "scanresult": {
            "title": "Scan Results"
          },
          "config":     {
            "title": "Setup Scan Automation"
          },
          "finished":   {
            "security_admin": false,
            "title":             "Finished: WordPress Core File Scanner"
          }
        }
      }
    }
  }
}