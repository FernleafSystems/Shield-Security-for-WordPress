{
  "slug":             "ips",
  "properties":       {
    "slug":                  "ips",
    "name":                  "Block Bad IPs/Visitors",
    "sidebar_name":          "IP Blocking",
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
  "menu_items":       [
    {
      "title": "IP Manager",
      "slug":  "ips-redirect"
    }
  ],
  "custom_redirects": [
    {
      "source_mod_page": "ips-redirect",
      "target_mod_page": "insights",
      "query_args":      {
        "inav": "ips"
      }
    }
  ],
  "admin_notices":    {
    "visitor-whitelisted": {
      "id":               "visitor-whitelisted",
      "schedule":         "conditions",
      "plugin_page_only": true,
      "per_user":         true,
      "type":             "info"
    }
  },
  "requirements":     {
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
  "sections":         [
    {
      "slug":        "section_auto_black_list",
      "primary":     true,
      "title":       "Automatic IP Black List",
      "title_short": "Auto IP Blocking Rules",
      "beacon_id":   208,
      "summary":     [
        "Purpose - The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of transgressions.",
        "Recommendation - Keep the Automatic IP Black List feature turned on."
      ]
    },
    {
      "slug":        "section_antibot",
      "title":       "AntiBot System",
      "title_short": "AntiBot System"
    },
    {
      "slug":        "section_logins",
      "title":       "Capture Login Bots",
      "title_short": "Login Bots",
      "beacon_id":   122,
      "summary":     [
        "Recommendation - Enable to capture bots/spiders that don't honour 'nofollow' directives."
      ]
    },
    {
      "slug":        "section_probes",
      "title":       "Capture Probing Bots",
      "title_short": "Probing Bots",
      "beacon_id":   123,
      "summary":     [
        "Recommendation - Enable to capture bots/spiders that don't honour 'nofollow' directives."
      ]
    },
    {
      "slug":        "section_behaviours",
      "title":       "Identify Common Bot Behaviours",
      "title_short": "Bot Behaviours",
      "beacon_id":   124,
      "summary":     [
        "Recommendation - Enable to capture bots/spiders that don't honour 'nofollow' directives."
      ]
    },
    {
      "slug":        "section_user_messages",
      "title":       "Customize Messages Shown To User",
      "title_short": "Visitor Messages",
      "beacon_id":   139,
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
  "options":          [
    {
      "key":         "enable_ips",
      "section":     "section_enable_plugin_feature_ips",
      "advanced":    true,
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/ea",
      "link_blog":   "https://shsec.io/wpsf26",
      "beacon_id":   208,
      "name":        "Enable IP Manager",
      "summary":     "Enable (or Disable) The IP Manager module",
      "description": "Un-Checking this option will completely disable the IP Manager module"
    },
    {
      "key":         "antibot_minimum",
      "section":     "section_antibot",
      "default":     45,
      "type":        "integer",
      "min":         0,
      "max":         99,
      "link_info":   "https://shsec.io/jy",
      "link_blog":   "https://shsec.io/jz",
      "beacon_id":   424,
      "name":        "AntiBot Threshold",
      "summary":     "AntiBot Testing Threshold (Percentage)",
      "description": "When using Shield's AntiBot system, this is the threshold used for testing (between 1 and 99)."
    },
    {
      "key":         "antibot_high_reputation_minimum",
      "section":     "section_antibot",
      "default":     200,
      "type":        "integer",
      "min":         0,
      "link_info":   "https://shsec.io/jy",
      "link_blog":   "https://shsec.io/jz",
      "beacon_id":   431,
      "name":        "High Reputation Bypass",
      "summary":     "Prevent IPs/Visitors With High Reputation Scores From Being Blocked",
      "description": "Ensures that visitors with a high reputation are never blocked by Shield."
    },
    {
      "key":         "transgression_limit",
      "section":     "section_auto_black_list",
      "default":     10,
      "type":        "integer",
      "link_info":   "https://shsec.io/wpsf24",
      "link_blog":   "https://shsec.io/wpsf26",
      "beacon_id":   207,
      "name":        "Offense Limit",
      "summary":     "Visitor IP address will be Black Listed after X bad actions on your site",
      "description": "A black mark is set against an IP address each time a visitor trips the defenses of the Shield plugin. When the number of these offenses exceeds specified limit, they are automatically blocked from accessing the site. Set this to 0 to turn off the Automatic IP Black List feature."
    },
    {
      "key":           "auto_expire",
      "section":       "section_auto_black_list",
      "advanced":      true,
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
      "link_info":     "https://shsec.io/wpsf25",
      "link_blog":     "https://shsec.io/wpsf26",
      "beacon_id":     210,
      "name":          "Auto Block Expiration",
      "summary":       "After 1 'X' a black listed IP will be removed from the black list",
      "description":   "Permanent and lengthy IP Black Lists are harmful to performance. You should allow IP addresses on the black list to be eventually removed over time. Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system."
    },
    {
      "key":           "user_auto_recover",
      "section":       "section_auto_black_list",
      "advanced":      true,
      "premium":       true,
      "default":       [],
      "type":          "multiple_select",
      "value_options": [
        {
          "value_key": "gasp",
          "text":      "With Shield Bot Protection"
        },
        {
          "value_key": "email",
          "text":      "Magic Email Links To Unblock Logged-In Users"
        }
      ],
      "link_info":     "https://shsec.io/f8",
      "link_blog":     "",
      "beacon_id":     125,
      "name":          "User Auto Unblock",
      "summary":       "Allow Visitors To Unblock Their IP",
      "description":   "Allow visitors blocked by the plugin to automatically unblock themselves."
    },
    {
      "key":         "request_whitelist",
      "section":     "section_auto_black_list",
      "advanced":    true,
      "premium":     true,
      "default":     [],
      "type":        "array",
      "link_info":   "https://shsec.io/gd",
      "link_blog":   "",
      "beacon_id":   126,
      "name":        "Request Path Whitelist",
      "summary":     "Request Path Whitelist",
      "description": "Request Path Whitelist."
    },
    {
      "key":         "text_loginfailed",
      "section":     "section_user_messages",
      "sensitive":   true,
      "premium":     true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://shsec.io/e8",
      "link_blog":   "",
      "beacon_id":   139,
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
      "link_info":     "https://shsec.io/fo",
      "link_blog":     "https://shsec.io/f7",
      "beacon_id":     123,
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
      "link_info":     "https://shsec.io/fo",
      "link_blog":     "https://shsec.io/f6",
      "beacon_id":     123,
      "name":          "Link Cheese",
      "summary":       "Tempt A Bot With A Fake Link To Follow",
      "description":   "Detect A Bot That Follows A 'no-follow' Link."
    },
    {
      "key":           "track_xmlrpc",
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
      "link_info":     "https://shsec.io/fo",
      "link_blog":     "https://shsec.io/f7",
      "beacon_id":     123,
      "name":          "XML-RPC Access",
      "summary":       "Identify A Bot When It Accesses XML-RPC",
      "description":   "If you don't use XML-RPC, why would anyone access it?"
    },
    {
      "key":           "track_invalidscript",
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
      "link_info":     "https://shsec.io/fo",
      "link_blog":     "https://shsec.io/f7",
      "beacon_id":     123,
      "name":          "Invalid Script Load",
      "summary":       "Identify A Bot Attempts To Load WordPress In A Non-Standard Way",
      "description":   "WordPress should only be loaded in a limited number of ways."
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
      "link_info":     "https://shsec.io/fn",
      "link_blog":     "https://shsec.io/f7",
      "beacon_id":     122,
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
      "link_info":     "https://shsec.io/fn",
      "link_blog":     "https://shsec.io/f7",
      "beacon_id":     122,
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
      "link_info":     "https://shsec.io/f5",
      "link_blog":     "https://shsec.io/f7",
      "beacon_id":     206,
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
      "link_info":     "https://shsec.io/fi",
      "link_blog":     "https://shsec.io/f7",
      "beacon_id":     124,
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
      "link_info":   "https://shsec.io/e9",
      "link_blog":   "",
      "beacon_id":   139,
      "name":        "Remaining Offenses",
      "summary":     "Visitor Triggers The IP Offenses System Through A Firewall Block",
      "description": "This message is displayed if the visitor triggered the IP Offenses system and reports how many offenses remain before being blocked."
    },
    {
      "key":          "autounblock_ips",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "array",
      "default":      []
    },
    {
      "key":          "autounblock_emailids",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "array",
      "default":      []
    }
  ],
  "definitions":      {
    "allowable_ext_404s":  [
      "js",
      "css",
      "gif",
      "jpg",
      "jpeg",
      "png",
      "map",
      "ttf",
      "woff",
      "woff2"
    ],
    "db_classes":          {
      "botsignals": "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\BotSignals\\Handler",
      "ip_lists":   "\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Databases\\IPs\\Handler"
    },
    "ip_lists_table_name": "ip_lists",
    "db_table_ip_lists":   {
      "slug":            "ip_lists",
      "cols_custom":     {
        "ip":             "varchar(60) NOT NULL DEFAULT '' COMMENT 'Human readable IP address or range'",
        "label":          "varchar(255) NOT NULL DEFAULT '' COMMENT 'Description'",
        "list":           "varchar(4) NOT NULL DEFAULT '' COMMENT 'Block or Bypass'",
        "ip6":            "tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Is IPv6'",
        "is_range":       "tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Is Range'",
        "transgressions": "int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total Offenses'"
      },
      "cols_timestamps": {
        "last_access_at": "Last Access By IP",
        "blocked_at":     "IP Blocked"
      }
    },
    "db_table_botsignals": {
      "autoexpire":      3,
      "slug":            "botsignals",
      "col_older_than":  "updated_at",
      "has_updated_at":  true,
      "cols_custom":     {
        "ip": "varbinary(16) DEFAULT NULL COMMENT 'IP Address'"
      },
      "cols_timestamps": {
        "notbot_at":          "NotBot",
        "frontpage_at":       "Any Frontend Page Loaded",
        "loginpage_at":       "Login Page Loaded",
        "bt404_at":           "BotTrack 404",
        "btfake_at":          "BotTrack FakeWebCrawler",
        "btcheese_at":        "BotTrack LinkCheese",
        "btloginfail_at":     "BotTrack LoginFailed",
        "btua_at":            "BotTrack Useragent Fail",
        "btxml_at":           "BotTrack XMLRPC Access",
        "btlogininvalid_at":  "BotTrack LoginInvalid",
        "btinvalidscript_at": "BotTrack InvalidScript",
        "cooldown_at":        "Triggered Cooldown",
        "humanspam_at":       "Comment Marked As Human SPAM",
        "markspam_at":        "Mark Comment As SPAM",
        "unmarkspam_at":      "Unmark Comment As SPAM",
        "captchapass_at":     "Captcha Passed",
        "captchafail_at":     "Captcha Failed",
        "auth_at":            "Successful Login",
        "firewall_at":        "Triggered Firewall",
        "ratelimit_at":       "Rate Limit Exceeded",
        "offense_at":         "Last Offense",
        "blocked_at":         "Last Block",
        "unblocked_at":       "Unblocked",
        "bypass_at":          "Bypass",
        "snsent_at":          "Sent To ShieldNET"
      }
    },
    "events":              {
      "custom_offense":          {
        "cat":     3,
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
      "ip_unblock":              {
        "offense": false,
        "audit":   false,
        "stat":    false
      },
      "ip_block_auto":           {
        "offense": false,
        "stat":    false,
        "cat":     1
      },
      "ip_block_manual":         {
        "offense": false,
        "stat":    false,
        "cat":     1
      },
      "ip_bypass":               {
        "offense": false,
        "audit":   false
      },
      "ip_unblock_flag":         {
        "cat": 1
      },
      "bottrack_notbot":         {
        "cat":     0,
        "offense": false,
        "audit":   false,
        "stat":    false
      },
      "bottrack_404":            {
        "cat":     1,
        "offense": true
      },
      "bottrack_fakewebcrawler": {
        "cat":     2,
        "offense": true
      },
      "bottrack_linkcheese":     {
        "cat":     2,
        "offense": true
      },
      "bottrack_loginfailed":    {
        "cat":     2,
        "offense": true
      },
      "bottrack_logininvalid":   {
        "cat":     2,
        "offense": true
      },
      "bottrack_useragent":      {
        "cat":     1,
        "offense": true
      },
      "bottrack_xmlrpc":         {
        "cat":     2,
        "offense": true
      },
      "bottrack_invalidscript":  {
        "cat":     2,
        "offense": true
      },
      "comment_markspam":        {
        "cat":     2,
        "offense": true
      },
      "comment_unmarkspam":      {
        "audit":   false,
        "offense": false,
        "stat":    false
      }
    }
  }
}