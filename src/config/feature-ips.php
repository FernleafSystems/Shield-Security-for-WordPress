{
  "slug":          "ips",
  "properties":    {
    "slug":                  "ips",
    "name":                  "IP Manager",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "ips",
    "tagline":               "Manage Visitor IP Address",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   true,
    "order":                 100
  },
  "admin_notices": {
    "visitor-whitelisted": {
      "id":          "visitor-whitelisted",
      "schedule":    "conditions",
      "valid_admin": true,
      "type":        "info"
    }
  },
  "requirements":  {
    "php": {
      "functions": [
        "filter_var"
      ],
      "constants": [
        "FILTER_VALIDATE_IP",
        "FILTER_FLAG_IPV4",
        "FILTER_FLAG_IPV6",
        "FILTER_FLAG_NO_PRIV_RANGE",
        "FILTER_FLAG_NO_RES_RANGE"
      ]
    }
  },
  "sections":      [
    {
      "slug":        "section_auto_black_list",
      "primary":     true,
      "title":       "Automatic IP Black List",
      "title_short": "Auto Black List",
      "summary":     [
        "Purpose - The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of transgressions.",
        "Recommendation - Keep the Automatic IP Black List feature turned on."
      ]
    },
    {
      "slug":        "section_reqtracking",
      "title":       "Bad Request Tracking",
      "title_short": "Request Tracking",
      "summary":     [
        "Purpose - Track strange behaviour to determine whether visitors are legitimate.",
        "Recommendation - These aren't security issues in their own right, but may indicate probing bots."
      ]
    },
    {
      "slug":        "section_user_messages",
      "title":       "Customize Messages Shown To User",
      "title_short": "Visitor Messages",
      "summary":     [
        "Purpose - Customize the messages shown to visitors.",
        "Recommendation - Be sure to change the messages to suit your audience.",
        "Hint - To reset any message to its default, enter the text exactly: default"
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_ips",
      "title":       "Enable Module: IP Manager",
      "title_short": "Disable Module",
      "summary":     [
        "Purpose - The IP Manager allows you to whitelist, blacklist and configure auto-blacklist rules.",
        "Recommendation - Keep the IP Manager feature turned on. You should also carefully review the automatic black list settings."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":       [
    {
      "key":         "enable_ips",
      "section":     "section_enable_plugin_feature_ips",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/ea",
      "link_blog":   "https://icwp.io/wpsf26",
      "name":        "Enable IP Manager",
      "summary":     "Enable (or Disable) The IP Manager module",
      "description": "Un-Checking this option will completely disable the IP Manager module"
    },
    {
      "key":         "transgression_limit",
      "section":     "section_auto_black_list",
      "default":     10,
      "type":        "integer",
      "link_info":   "https://icwp.io/wpsf24",
      "link_blog":   "https://icwp.io/wpsf26",
      "name":        "Transgression Limit",
      "summary":     "Visitor IP address will be Black Listed after X bad actions on your site",
      "description": "A black mark is set against an IP address each time a visitor trips the defenses of the Shield plugin. When the number of these transgressions exceeds specified limit, they are automatically blocked from accessing the site. Set this to 0 to turn off the Automatic IP Black List feature."
    },
    {
      "key":           "auto_expire",
      "section":       "section_auto_black_list",
      "default":       "minute",
      "type":          "select",
      "value_options": [
        {
          "value_key": "minute",
          "text":      "Minute"
        },
        {
          "value_key": "hour",
          "text":      "Hour"
        },
        {
          "value_key": "day",
          "text":      "Day"
        },
        {
          "value_key": "week",
          "text":      "Week"
        }
      ],
      "link_info":     "https://icwp.io/wpsf25",
      "link_blog":     "https://icwp.io/wpsf26",
      "name":          "Auto Block Expiration",
      "summary":       "After 1 'X' a black listed IP will be removed from the black list",
      "description":   "Permanent and lengthy IP Black Lists are harmful to performance. You should allow IP addresses on the black list to be eventually removed over time. Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system."
    },
    {
      "key":         "text_loginfailed",
      "section":     "section_user_messages",
      "sensitive":   true,
      "premium":     true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://icwp.io/e8",
      "link_blog":   "",
      "name":        "Login Failed",
      "summary":     "Visitor Triggers The IP Transgression System Through A Failed Login",
      "description": "This message is displayed if the visitor fails a login attempt."
    },
    {
      "key":           "track_404",
      "section":       "section_reqtracking",
      "sensitive":     false,
      "type":          "select",
      "premium":       true,
      "default":       "disabled",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Ignore 404s"
        },
        {
          "value_key": "log-only",
          "text":      "Log Only (Audit Trail)"
        },
        {
          "value_key": "assign-transgression",
          "text":      "Increment Transgression"
        }
      ],
      "link_info":     "https://icwp.io/e7",
      "link_blog":     "",
      "name":          "Track 404s",
      "summary":       "Use 404s As An Transgression",
      "description":   "Repeated 404s may indicate a probing bot especially where WP Login has been renamed."
    },
    {
      "key":         "text_remainingtrans",
      "section":     "section_user_messages",
      "sensitive":   true,
      "premium":     true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://icwp.io/e9",
      "link_blog":   "",
      "name":        "Remaining Transgressions",
      "summary":     "Visitor Triggers The IP Transgression System Through A Firewall Block",
      "description": "This message is displayed if the visitor triggered the IP Transgression system and reports how many transgressions remain before being blocked."
    },
    {
      "key":          "this_server_ip",
      "transferable": false,
      "sensitive":    true,
      "section":      "section_non_ui",
      "value":        ""
    },
    {
      "key":          "this_server_ip_last_check_at",
      "transferable": false,
      "section":      "section_non_ui",
      "value":        0
    },
    {
      "key":          "insights_last_transgression_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    },
    {
      "key":          "insights_last_ip_block_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    }
  ],
  "definitions":   {
    "ip_lists_table_name":   "ip_lists",
    "ip_list_table_columns": [
      "id",
      "ip",
      "label",
      "list",
      "ip6",
      "is_range",
      "transgressions",
      "last_access_at",
      "created_at",
      "deleted_at"
    ]
  }
}