{
  "slug": "hack_protect",
  "properties": {
    "name": "Hack Protection",
    "show_feature_menu_item": true,
    "storage_key": "hack_protect"
  },
  "sections": [
    {
      "slug": "section_enable_plugin_feature_hack_protection_tools",
      "primary": true,
      "title": "Enable Plugin Feature: Hack Protection",
      "title_short": "Enable / Disable",
      "summary": [
        "Purpose - The Hack Protection system is a set of tools to warn you and protect you against hacks on your site.",
        "Recommendation - Keep the Hack Protection feature turned on."
      ]
    },
    {
      "slug": "section_plugin_vulnerabilities_scan",
      "title": "Plugin Vulnerabilities Scanner",
      "title_short": "Plugin Vulnerabilities",
      "summary": [
        "Purpose - Regularly scan your plugins against a database of known vulnerabilities.",
        "Recommendation - Keep the Plugin Vulnerabilities Scanner feature turned on."
      ]
    },
    {
      "slug": "section_core_file_integrity_scan",
      "title": "Core File Integrity Scanner",
      "title_short": "Core File Scanner",
      "summary": [
        "Purpose - Regularly scan your WordPress core files for changes compared to official WordPress files.",
        "Recommendation - Keep the Core File Integrity Scanner feature turned on."
      ]
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_hack_protect",
      "section": "section_enable_plugin_feature_hack_protection_tools",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/wpsf38",
      "link_blog": "",
      "name": "Enable Hack Protection",
      "summary": "Enable (or Disable) The Hack Protection Feature",
      "description": "Checking/Un-Checking this option will completely turn on/off the whole Hack Protection feature"
    },
    {
      "key": "enable_core_file_integrity_scan",
      "section": "section_core_file_integrity_scan",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/wpsf36",
      "link_blog": "http://icwp.io/wpsf37",
      "name": "Core File Scanner",
      "summary": "Daily Cron - Scans WordPress Core Files For Alterations",
      "description": "Compares all WordPress core files on your site against the official WordPress files. WordPress Core files should never be altered for any reason."
    },
    {
      "key": "attempt_auto_file_repair",
      "section": "section_core_file_integrity_scan",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/wpsf36",
      "link_blog": "http://icwp.io/wpsf37",
      "name": "Auto Repair",
      "summary": "Automatically Repair WordPress Core Files That Have Been Altered",
      "description": "Attempts to automatically repair WordPress Core files with the official WordPress file data, for files that have been altered or are missing."
    }
  ],
  "definitions": {
    "plugin_vulnerabilities_data_source": "https://raw.githubusercontent.com/FernleafSystems/wp-plugin-vulnerabilities/master/vulnerabilities.yaml",
    "notifications_cron_name": "plugin-vulnerabilities-notification",
    "corechecksum_cron_name": "core-checksum-notification",
    "url_checksum_api": "https://api.wordpress.org/core/checksums/1.0/",
    "url_wordress_core_svn": "https://core.svn.wordpress.org/",
    "corechecksum_exclusions": [
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
    "corechecksum_autofix_index_files": [
      "wp-content/index.php",
      "wp-content/plugins/index.php",
      "wp-content/themes/index.php"
    ]
  }
}