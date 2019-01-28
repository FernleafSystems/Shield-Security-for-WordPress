{
  "slug":          "comments_filter",
  "properties":    {
    "slug":                  "comments_filter",
    "name":                  "Comments SPAM",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "commentsfilter",
    "tagline":               "Block comment SPAM and retain your privacy",
    "use_sessions":          true,
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    false,
    "run_if_verified_bot":   false,
    "order":                 50
  },
  "admin_notices": {
    "akismet-running": {
      "id":          "akismet-running",
      "schedule":    "conditions",
      "valid_admin": true,
      "type":        "warning"
    }
  },
  "sections":      [
    {
      "slug":        "section_bot_comment_spam_protection_filter",
      "primary":     true,
      "title":       "Automatic Bot Comment SPAM Protection Filter",
      "title_short": "Bot SPAM",
      "summary":     [
        "Purpose - Blocks 100% of all automated bot-generated comment SPAM.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug":        "section_recaptcha",
      "title":       "Google reCAPTCHA",
      "title_short": "reCAPTCHA",
      "summary":     [
        "Purpose - Adds Google reCAPTCHA to the Comment Forms.",
        "Recommendation - Keep this turned on.",
        "Note - You will need to register for Google reCAPTCHA keys and store them in the Shield 'Dashboard' settings."
      ]
    },
    {
      "slug":        "section_human_spam_filter",
      "title":       "Human Comment SPAM Protection Filter",
      "title_short": "Human SPAM",
      "summary":     [
        "Purpose - Uses a 3rd party SPAM dictionary to detect human-based comment SPAM.",
        "Recommendation - Use of this feature is highly recommend.This tool, unlike other SPAM tools such as Akismet, will not send your comment data to 3rd party services for analysis."
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
      "slug":        "section_enable_plugin_feature_spam_comments_protection_filter",
      "title":       "Enable Module: Comments SPAM Protection",
      "title_short": "Disable Module",
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
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/3z",
      "link_blog":   "https://icwp.io/wpsf04",
      "name":        "Enable SPAM Protection",
      "summary":     "Enable (or Disable) The Comments SPAM Protection module",
      "description": "Un-Checking this option will completely disable the Comments SPAM Protection module"
    },
    {
      "key":         "enable_comments_gasp_protection",
      "section":     "section_bot_comment_spam_protection_filter",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/3n",
      "link_blog":   "https://icwp.io/2n",
      "name":        "GASP Protection",
      "summary":     "Block Bot Comment SPAM",
      "description": "Taking the lead from the original GASP plugin for WordPress, we have extended it to include advanced spam-bot protection."
    },
    {
      "key":         "comments_cooldown_interval",
      "section":     "section_bot_comment_spam_protection_filter",
      "default":     10,
      "min":         0,
      "type":        "integer",
      "link_info":   "https://icwp.io/3o",
      "link_blog":   "",
      "name":        "Comments Cooldown",
      "summary":     "Limit posting comments to X seconds after the page has loaded",
      "description": "By forcing a comments cooldown period, you restrict a Spambot's ability to post multiple times to your posts."
    },
    {
      "key":           "comments_default_action_spam_bot",
      "section":       "section_bot_comment_spam_protection_filter",
      "default":       "trash",
      "type":          "select",
      "value_options": [
        {
          "value_key": 0,
          "text":      "Mark As Pending Moderation"
        },
        {
          "value_key": "spam",
          "text":      "Mark As SPAM"
        },
        {
          "value_key": "trash",
          "text":      "Move To Trash"
        },
        {
          "value_key": "reject",
          "text":      "Reject And Redirect"
        }
      ],
      "link_info":     "https://icwp.io/6j",
      "link_blog":     "",
      "name":          "Default SPAM Action",
      "summary":       "How To Categorise Comments When Identified To Be SPAM",
      "description":   "When a comment is detected as being SPAM from an automatic bot, the comment will be categorised based on this setting."
    },
    {
      "key":         "enable_comments_human_spam_filter",
      "section":     "section_human_spam_filter",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/57",
      "link_blog":   "https://icwp.io/9w",
      "name":        "Human SPAM Filter",
      "summary":     "Enable (or Disable) The Human SPAM Filter module",
      "description": "Scans the content of WordPress comments for keywords that are indicative of SPAM and marks the comment according to your preferred setting below."
    },
    {
      "key":           "enable_comments_human_spam_filter_items",
      "section":       "section_human_spam_filter",
      "type":          "multiple_select",
      "default":       [
        "author_name",
        "author_email",
        "comment_content",
        "url",
        "ip_address",
        "user_agent"
      ],
      "value_options": [
        {
          "value_key": "author_name",
          "text":      "Author Name"
        },
        {
          "value_key": "author_email",
          "text":      "Author Email"
        },
        {
          "value_key": "comment_content",
          "text":      "Comment Content"
        },
        {
          "value_key": "url",
          "text":      "URL"
        },
        {
          "value_key": "ip_address",
          "text":      "IP Address"
        },
        {
          "value_key": "user_agent",
          "text":      "Browser User Agent"
        }
      ],
      "link_info":     "https://icwp.io/58",
      "link_blog":     "",
      "name":          "Comment Filter Items",
      "summary":       "Select The Items To Scan For SPAM",
      "description":   "When a user submits a comment, only the selected parts of the comment data will be scanned for SPAM content."
    },
    {
      "key":           "comments_default_action_human_spam",
      "section":       "section_human_spam_filter",
      "default":       0,
      "type":          "select",
      "value_options": [
        {
          "value_key": 0,
          "text":      "Mark As Pending Moderation"
        },
        {
          "value_key": "spam",
          "text":      "Mark As SPAM"
        },
        {
          "value_key": "trash",
          "text":      "Move To Trash"
        },
        {
          "value_key": "reject",
          "text":      "Reject And Redirect"
        }
      ],
      "name":          "Default SPAM Action",
      "summary":       "How To Categorise Comments When Identified To Be SPAM'",
      "description":   "When a comment is detected as being SPAM from a human commenter, the comment will be categorised based on this setting."
    },
    {
      "key":         "enable_google_recaptcha_comments",
      "section":     "section_recaptcha",
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "https://icwp.io/shld5",
      "link_blog":   "",
      "name":        "Google reCAPTCHA",
      "summary":     "Enable Google reCAPTCHA For Comments",
      "description": "Use Google reCAPTCHA on the comments form to prevent bot-spam comments."
    },
    {
      "key":           "google_recaptcha_style_comments",
      "section":       "section_recaptcha",
      "premium":       true,
      "default":       "default",
      "type":          "select",
      "value_options": [
        {
          "value_key": "default",
          "text":      "Default"
        },
        {
          "value_key": "light",
          "text":      "Light Theme"
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
          "text":      "Invisible reCAPTCHA"
        }
      ],
      "link_info":     "https://icwp.io/e4",
      "link_blog":     "",
      "name":          "reCAPTCHA Style",
      "summary":       "How Google reCAPTCHA Will Be Displayed",
      "description":   "You can choose the reCAPTCHA display format that best suits your site, including the new Invisible Recaptcha."
    },
    {
      "key":         "comments_token_expire_interval",
      "section":     "section_bot_comment_spam_protection_filter",
      "default":     600,
      "min":         0,
      "type":        "integer",
      "link_info":   "https://icwp.io/3o",
      "link_blog":   "https://icwp.io/9v",
      "name":        "Comment Token Expire",
      "summary":     "A visitor has X seconds within which to post a comment",
      "description": "Default: 600 seconds (10 minutes). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors."
    },
    {
      "key":         "custom_message_checkbox",
      "section":     "section_user_messages",
      "sensitive":   true,
      "default":     "default",
      "type":        "text",
      "link_info":   "https://icwp.io/3p",
      "link_blog":   "",
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
      "link_info":   "https://icwp.io/3p",
      "link_blog":   "",
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
      "link_info":   "https://icwp.io/3p",
      "link_blog":   "",
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
      "link_info":   "https://icwp.io/3p",
      "link_blog":   "",
      "name":        "Custom Reload Message",
      "summary":     "If you want a custom message when the comment token has expired, please provide this here.",
      "description": "This message is displayed on the submit-button when the comment token is expired."
    },
    {
      "key":          "insights_last_comment_block_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    }
  ],
  "definitions":   {
    "spambot_comments_filter_table_name":    "spambot_comments_filter",
    "spambot_comments_filter_table_columns": [
      "id",
      "post_id",
      "unique_token",
      "ip",
      "created_at",
      "deleted_at"
    ]
  }
}