{
  "slug":          "comments_filter",
  "properties":    {
    "slug":                  "comments_filter",
    "name":                  "Comments SPAM",
    "sidebar_name":          "SPAM",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "commentsfilter",
    "tagline":               "Block comment SPAM and retain your privacy",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    false,
    "run_if_verified_bot":   false,
    "run_if_wpcli":          false,
    "order":                 50
  },
  "admin_notices": {
    "akismet-running": {
      "id":               "akismet-running",
      "plugin_admin":     "yes",
      "plugin_page_only": true,
      "type":             "warning"
    }
  },
  "sections":      [
    {
      "primary":     true,
      "slug":        "section_bot_comment_spam_protection_filter",
      "title":       "Automatic Bot Comment SPAM Protection Filter",
      "title_short": "Bot SPAM",
      "beacon_id":   260,
      "summary":     [
        "Purpose - Blocks 100% of all automated bot-generated comment SPAM.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug":        "section_human_spam_filter",
      "title":       "Human Comment SPAM Protection Filter",
      "title_short": "Human SPAM",
      "beacon_id":   262,
      "summary":     [
        "Purpose - Uses a 3rd party SPAM dictionary to detect human-based comment SPAM.",
        "Recommendation - Use of this feature is highly recommend.This tool, unlike other SPAM tools such as Akismet, will not send your comment data to 3rd party services for analysis."
      ]
    },
    {
      "slug":        "section_bot_comment_spam_common",
      "title":       "Common Settings For All SPAM Scanning",
      "title_short": "Common Settings",
      "beacon_id":   152,
      "summary":     [
        "Purpose - Settings that apply to all comment SPAM scanning."
      ]
    },
    {
      "slug":        "section_user_messages",
      "title":       "Customize Messages Shown To User",
      "title_short": "Visitor Messages",
      "beacon_id":   403,
      "summary":     [
        "Purpose - Customize the messages shown to visitors.",
        "Recommendation - Be sure to change the messages to suit your audience.",
        "Hint - To reset any message to its default, enter the text exactly: default"
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_spam_comments_protection_filter",
      "title":       "Enable Module: Comments SPAM Protection",
      "title_short": "Disable Module",
      "beacon_id":   257,
      "summary":     [
        "Purpose - The Comments Filter can block 100% of automated spam bots and also offer the option to analyse human-generated spam.",
        "Recommendation - Keep the Comments Filter feature turned on."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":       [
    {
      "key":         "enable_comments_filter",
      "section":     "section_enable_plugin_feature_spam_comments_protection_filter",
      "advanced":    true,
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/3z",
      "link_blog":   "https://shsec.io/wpsf04",
      "beacon_id":   257,
      "name":        "Enable SPAM Protection",
      "summary":     "Enable (or Disable) The Comments SPAM Protection module",
      "description": "Un-Checking this option will completely disable the Comments SPAM Protection module"
    },
    {
      "key":         "trusted_commenter_minimum",
      "section":     "section_bot_comment_spam_common",
      "default":     1,
      "min":         1,
      "type":        "integer",
      "link_info":   "https://shsec.io/fu",
      "link_blog":   "",
      "beacon_id":   152,
      "name":        "Trusted Commenter Minimum",
      "summary":     "Minimum Number Of Approved Comments Before Commenter Is Trusted",
      "description": "Specify how many approved comments must exist before a commenter is trusted and their comments are no longer scanned."
    },
    {
      "key":         "trusted_user_roles",
      "section":     "section_bot_comment_spam_common",
      "premium":     true,
      "default":     [
        "administrator",
        "editor",
        "author",
        "contributor",
        "subscriber"
      ],
      "type":        "array",
      "link_info":   "https://shsec.io/fu",
      "link_blog":   "",
      "beacon_id":   152,
      "name":        "Trusted Users",
      "summary":     "Don't Scan Comments For Users With The Following Roles",
      "description": "Shield doesn't normally scan comments from logged-in or registered users. Specify user roles here that shouldn't be scanned."
    },
    {
      "key":         "enable_antibot_check",
      "section":     "section_bot_comment_spam_protection_filter",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/k1",
      "link_blog":   "https://shsec.io/jo",
      "beacon_id":   427,
      "name":        "AntiBot Detection Engine",
      "summary":     "Use Experimental AntiBot Detection Engine",
      "description": "Use Shield's AntiBot Detection Engine In-Place of GASP Bot checking."
    },
    {
      "key":           "comments_default_action_spam_bot",
      "section":       "section_bot_comment_spam_protection_filter",
      "default":       "spam",
      "type":          "select",
      "value_options": [
        {
          "value_key": "0",
          "text":      "Move To Pending Moderation"
        },
        {
          "value_key": "spam",
          "text":      "Move To SPAM"
        },
        {
          "value_key": "trash",
          "text":      "Move To Trash"
        },
        {
          "value_key": "reject",
          "text":      "Block And Redirect"
        }
      ],
      "link_info":     "https://shsec.io/6j",
      "link_blog":     "",
      "beacon_id":     260,
      "name":          "SPAM Action",
      "summary":       "How To Categorise Comments When Identified To Be SPAM",
      "description":   "When a comment is detected as being SPAM from an automatic bot, the comment will be categorised based on this setting."
    },
    {
      "key":           "google_recaptcha_style_comments",
      "section":       "section_bot_comment_spam_protection_filter",
      "default":       "disabled",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "default",
          "text":      "Default Style"
        },
        {
          "value_key": "light",
          "text":      "Light Theme"
        },
        {
          "value_key": "dark",
          "text":      "Dark Theme"
        },
        {
          "value_key": "invisible",
          "text":      "Invisible"
        }
      ],
      "link_info":     "https://shsec.io/e4",
      "link_blog":     "",
      "beacon_id":     269,
      "name":          "CAPTCHA",
      "summary":       "Enable CAPTCHA To Protect Against SPAM Comments",
      "description":   "You can choose the CAPTCHA display format that best suits your site, including the newer Invisible CAPTCHA."
    },
    {
      "key":         "enable_comments_gasp_protection",
      "section":     "section_bot_comment_spam_protection_filter",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/3n",
      "link_blog":   "https://shsec.io/2n",
      "beacon_id":   401,
      "name":        "GASP Protection",
      "summary":     "Block Bot Comment SPAM",
      "description": "Taking the lead from the original GASP plugin for WordPress, we have extended it to include advanced spam-bot protection."
    },
    {
      "key":         "enable_comments_human_spam_filter",
      "section":     "section_human_spam_filter",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/57",
      "link_blog":   "https://shsec.io/9w",
      "beacon_id":   262,
      "name":        "Human SPAM Filter",
      "summary":     "Enable (or Disable) The Human SPAM Filter module",
      "description": "Scans the content of WordPress comments for keywords that are indicative of SPAM and marks the comment according to your preferred setting below."
    },
    {
      "key":           "comments_default_action_human_spam",
      "section":       "section_human_spam_filter",
      "default":       "0",
      "type":          "select",
      "value_options": [
        {
          "value_key": "0",
          "text":      "Move To Pending Moderation"
        },
        {
          "value_key": "spam",
          "text":      "Move To SPAM"
        },
        {
          "value_key": "trash",
          "text":      "Move To Trash"
        },
        {
          "value_key": "reject",
          "text":      "Block And Redirect"
        }
      ],
      "name":          "SPAM Action",
      "summary":       "How To Categorise Comments When Identified To Be SPAM'",
      "description":   "When a comment is detected as being SPAM from a human commenter, the comment will be categorised based on this setting."
    },
    {
      "key":         "custom_message_checkbox",
      "section":     "section_user_messages",
      "sensitive":   true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://shsec.io/3p",
      "link_blog":   "",
      "beacon_id":   403,
      "name":        "Custom Checkbox Message",
      "summary":     "If you want a custom checkbox message, please provide this here",
      "description": "You can customise the message beside the checkbox."
    },
    {
      "key":         "custom_message_alert",
      "section":     "section_user_messages",
      "sensitive":   true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://shsec.io/3p",
      "link_blog":   "",
      "beacon_id":   403,
      "name":        "Custom Alert Message",
      "summary":     "If you want a custom alert message, please provide this here",
      "description": "This alert message is displayed when a visitor attempts to submit a comment without checking the box."
    },
    {
      "key":         "custom_message_comment_wait",
      "section":     "section_user_messages",
      "sensitive":   true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://shsec.io/3p",
      "link_blog":   "",
      "beacon_id":   403,
      "name":        "Custom Wait Message",
      "summary":     "If you want a custom submit-button wait message, please provide this here.",
      "description": "Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these."
    },
    {
      "key":         "custom_message_comment_reload",
      "section":     "section_user_messages",
      "sensitive":   true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://shsec.io/3p",
      "link_blog":   "",
      "beacon_id":   403,
      "name":        "Custom Reload Message",
      "summary":     "If you want a custom message when the comment token has expired, please provide this here.",
      "description": "This message is displayed on the submit-button when the comment token is expired."
    },
    {
      "key":     "comments_cooldown",
      "section": "section_non_ui",
      "default": 10,
      "min":     0,
      "type":    "integer"
    },
    {
      "key":     "human_spam_items",
      "section": "section_non_ui",
      "type":    "array",
      "default": [
        "author_name",
        "author_email",
        "comment_content",
        "url",
        "ip_address",
        "user_agent"
      ]
    }
  ],
  "definitions":   {
    "comments_expire":          1800,
    "url_spam_blacklist_terms": "https://raw.githubusercontent.com/splorp/wordpress-comment-blacklist/master/blacklist.txt",
    "events":                   {
      "comment_spam_block":   {
        "audit":   false,
        "stat":    false,
        "offense": true
      },
      "spam_block_antibot":   {
      },
      "spam_block_bot":       {
      },
      "spam_block_recaptcha": {
      },
      "spam_block_human":     {
      }
    }
  }
}