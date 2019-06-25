{
  "slug":          "ips",
  "properties":    {
    "slug":                  "ips",
    "name":                  "Block Bad IPs/Visitors",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "ips",
    "tagline":               "Automatically detect bots and malicious visitors and stop them dead.",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   true,
    "run_if_wpcli":          false,
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
      "title_short": "Auto IP Blocking Rules",
      "summary":     [
        "Purpose - The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of transgressions.",
        "Recommendation - Keep the Automatic IP Black List feature turned on."
      ]
    },
    {
      "slug":        "section_logins",
      "title":       "Capture Login Bots",
      "title_short": "Login Bots",
      "summary":     [
        "Recommendation - Enable to capture bots/spiders that don't honour 'nofollow' directives."
      ]
    },
    {
      "slug":        "section_probes",
      "title":       "Capture Probing Bots",
      "title_short": "Probing Bots",
      "summary":     [
        "Recommendation - Enable to capture bots/spiders that don't honour 'nofollow' directives."
      ]
    },
    {
      "slug":        "section_behaviours",
      "title":       "Identify Common Bot Behaviours",
      "title_short": "Bot Behaviours",
      "summary":     [
        "Recommendation - Enable to capture bots/spiders that don't honour 'nofollow' directives."
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_bottrap",
      "title":       "Enable Module: BotTrap",
      "title_short": "Enable Module",
      "summary":     [
        "Purpose - BotTrap monitors a typical set of bot behaviours to help identify probing bots.",
        "Recommendation - Enable as many bot traps as possible."
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
      "name":        "Offense Limit",
      "summary":     "Visitor IP address will be Black Listed after X bad actions on your site",
      "description": "A black mark is set against an IP address each time a visitor trips the defenses of the Shield plugin. When the number of these offenses exceeds specified limit, they are automatically blocked from accessing the site. Set this to 0 to turn off the Automatic IP Black List feature."
    },
    {
      "key":           "auto_expire",
      "section":       "section_auto_black_list",
      "default":       "day",
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
        },
        {
          "value_key": "month",
          "text":      "Month"
        }
      ],
      "link_info":     "https://icwp.io/wpsf25",
      "link_blog":     "https://icwp.io/wpsf26",
      "name":          "Auto Block Expiration",
      "summary":       "After 1 'X' a black listed IP will be removed from the black list",
      "description":   "Permanent and lengthy IP Black Lists are harmful to performance. You should allow IP addresses on the black list to be eventually removed over time. Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system."
    },
    {
      "key":           "user_auto_recover",
      "section":       "section_auto_black_list",
      "premium":       true,
      "default":       "disabled",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "gasp",
          "text":      "With Shield Bot Protection"
        }
      ],
      "link_info":     "https://icwp.io/f8",
      "link_blog":     "",
      "name":          "User Auto Unblock",
      "summary":       "Allow Visitors To Unblock Their IP",
      "description":   "Allow visitors blocked by the plugin to automatically unblock themselves."
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
      "summary":     "Visitor Triggers The IP Offenses System Through A Failed Login",
      "description": "This message is displayed if the visitor fails a login attempt."
    },
    {
      "key":           "track_404",
      "section":       "section_probes",
      "premium":       true,
      "default":       "log",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "log",
          "text":      "Audit Log Only"
        },
        {
          "value_key": "transgression-single",
          "text":      "Increment Offense Counter"
        },
        {
          "value_key": "transgression-double",
          "text":      "Double-Increment Offense Counter"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "https://icwp.io/f5",
      "link_blog":     "https://icwp.io/f7",
      "name":          "404 Detect",
      "summary":       "Identify A Bot When It Hits A 404",
      "description":   "Detect When A Visitor Browses To A Non-Existent Page."
    },
    {
      "key":           "track_linkcheese",
      "section":       "section_probes",
      "premium":       true,
      "default":       "disabled",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "log",
          "text":      "Audit Log Only"
        },
        {
          "value_key": "transgression-single",
          "text":      "Increment Offense Counter"
        },
        {
          "value_key": "transgression-double",
          "text":      "Double-Increment Offense Counter"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "https://icwp.io/f5",
      "link_blog":     "https://icwp.io/f6",
      "name":          "Link Cheese",
      "summary":       "Tempt A Bot With A Fake Link To Follow",
      "description":   "Detect A Bot That Follows A 'no-follow' Link."
    },
    {
      "key":           "track_xmlrpc",
      "section":       "section_probes",
      "default":       "log",
      "premium":       true,
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "log",
          "text":      "Audit Log Only"
        },
        {
          "value_key": "transgression-single",
          "text":      "Increment Offense Counter"
        },
        {
          "value_key": "transgression-double",
          "text":      "Double-Increment Offense Counter"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "https://icwp.io/f5",
      "link_blog":     "https://icwp.io/f7",
      "name":          "XML-RPC Access",
      "summary":       "Identify A Bot When It Accesses XML-RPC",
      "description":   "If you don't use XML-RPC, why would anyone access it?"
    },
    {
      "key":           "track_loginfailed",
      "section":       "section_logins",
      "default":       "transgression-single",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "log",
          "text":      "Audit Log Only"
        },
        {
          "value_key": "transgression-single",
          "text":      "Increment Offense Counter"
        },
        {
          "value_key": "transgression-double",
          "text":      "Double-Increment Offense Counter"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "https://icwp.io/f5",
      "link_blog":     "https://icwp.io/f7",
      "name":          "Failed Login",
      "summary":       "Detect Failed Login Attempts By Valid Usernames",
      "description":   "Penalise a visitor who fails to login using a valid username."
    },
    {
      "key":           "track_logininvalid",
      "section":       "section_logins",
      "premium":       true,
      "default":       "log",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "log",
          "text":      "Audit Log Only"
        },
        {
          "value_key": "transgression-single",
          "text":      "Increment Offense Counter"
        },
        {
          "value_key": "transgression-double",
          "text":      "Double-Increment Offense Counter"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "https://icwp.io/f5",
      "link_blog":     "https://icwp.io/f7",
      "name":          "Invalid Usernames",
      "summary":       "Detect Invalid Username Logins",
      "description":   "Identify A Bot When It Tries To Login With A Non-Existent Username."
    },
    {
      "key":           "track_fakewebcrawler",
      "section":       "section_behaviours",
      "premium":       true,
      "default":       "log",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "log",
          "text":      "Audit Log Only"
        },
        {
          "value_key": "transgression-single",
          "text":      "Increment Offense Counter"
        },
        {
          "value_key": "transgression-double",
          "text":      "Double-Increment Offense Counter"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "https://icwp.io/f5",
      "link_blog":     "https://icwp.io/f7",
      "name":          "Fake Web Crawler",
      "summary":       "Detect Fake Search Engine Crawlers",
      "description":   "Identify a Bot when it presents as an official web crawler, but analysis shows it's fake."
    },
    {
      "key":           "track_useragent",
      "section":       "section_behaviours",
      "premium":       true,
      "default":       "log",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "log",
          "text":      "Audit Log Only"
        },
        {
          "value_key": "transgression-single",
          "text":      "Increment Offense Counter"
        },
        {
          "value_key": "transgression-double",
          "text":      "Double-Increment Offense Counter"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "https://icwp.io/fi",
      "link_blog":     "https://icwp.io/f7",
      "name":          "Empty User Agents",
      "summary":       "Detect Requests With Empty User Agents",
      "description":   "Identify a request as a bot if the user agent is not provided."
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
      "name":        "Remaining Offenses",
      "summary":     "Visitor Triggers The IP Offenses System Through A Firewall Block",
      "description": "This message is displayed if the visitor triggered the IP Offenses system and reports how many offenses remain before being blocked."
    },
    {
      "key":          "this_server_ip",
      "section":      "section_non_ui",
      "transferable": false,
      "sensitive":    true,
      "type":         "text",
      "default":      ""
    },
    {
      "key":          "this_server_ip_last_check_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "insights_last_transgression_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "insights_last_ip_block_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "autounblock_ips",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "array",
      "default":      []
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
    ],
    "events":                {
      "custom_offense":               {
        "cat": 3,
        "offense": true
      },
      "conn_kill":               {
        "cat": 3
      },
      "ip_offense":              {
        "cat": 2
      },
      "ip_blocked":              {
        "cat": 2
      },
      "ip_unblock_flag":            {
        "cat": 1
      },
      "bottrack_404":            {
        "cat": 2,
        "offense": true
      },
      "bottrack_fakewebcrawler": {
        "cat": 2,
        "offense": true
      },
      "bottrack_linkcheese":     {
        "cat": 2,
        "offense": true
      },
      "bottrack_loginfailed":    {
        "cat": 2,
        "offense": true
      },
      "bottrack_logininvalid":   {
        "cat": 2,
        "offense": true
      },
      "bottrack_useragent":      {
        "cat": 2,
        "offense": true
      },
      "bottrack_xmlrpc":         {
        "cat": 2,
        "offense": true
      }
    }
  }
}