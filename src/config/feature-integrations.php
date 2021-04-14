{
  "slug":        "integrations",
  "properties":  {
    "slug":                  "integrations",
    "storage_key":           "integrations",
    "name":                  "Integrations",
    "menu_title":            "Integrations",
    "show_module_options":   true,
    "show_module_menu_item": false,
    "auto_enabled":          true,
    "show_central":          true,
    "premium":               false,
    "access_restricted":     true,
    "run_if_whitelisted":    true,
    "run_if_wpcli":          true,
    "run_if_verified_bot":   true,
    "skip_processor":        false,
    "tracking_exclude":      false
  },
  "wpcli":       {
    "enabled": true
  },
  "sections":    [
    {
      "slug":        "section_integrations",
      "primary":     true,
      "title":       "Integrations",
      "title_short": "Integrations"
    },
    {
      "slug":        "section_spam",
      "title":       "SPAM Detection",
      "title_short": "SPAM Detection"
    },
    {
      "slug":        "section_user_forms",
      "title":       "User Forms Bot Detection",
      "title_short": "User Forms Bot Detection"
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":         "enable_mainwp",
      "section":     "section_integrations",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "https://shsec.io/ir",
      "link_blog":   "",
      "name":        "Enable MainWP",
      "summary":     "Enable The Built-In MainWP Extension",
      "description": "This option will enable Shield's built-in MainWP extension for both server and client."
    },
    {
      "key":         "enable_spam_antibot",
      "section":     "section_spam",
      "premium":     true,
      "default":     "N",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "AntiBot SPAM Detection",
      "summary":     "Enable The AntiBot SPAM Detection",
      "description": "Use Shield's built-in AntiBot Detection Engine to identify contact form SPAM."
    },
    {
      "key":           "form_spam_providers",
      "section":       "section_spam",
      "premium":       true,
      "advanced":      true,
      "type":          "multiple_select",
      "default":       [],
      "value_options": [
        {
          "value_key": "contactform7",
          "text":      "Contact Form 7"
        },
        {
          "value_key": "elementorpro",
          "text":      "Elementor Pro"
        },
        {
          "value_key": "fluentforms",
          "text":      "Fluent Forms"
        },
        {
          "value_key": "formidableforms",
          "text":      "Formidable Forms"
        },
        {
          "value_key": "forminator",
          "text":      "Forminator"
        },
        {
          "value_key": "gravityforms",
          "text":      "Gravity Forms"
        },
        {
          "value_key": "kaliforms",
          "text":      "Kali Forms"
        },
        {
          "value_key": "ninjaforms",
          "text":      "Ninja Forms"
        },
        {
          "value_key": "wpforo",
          "text":      "wpForo"
        },
        {
          "value_key": "wpforms",
          "text":      "WPForms"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "SPAM Form Checking",
      "summary":       "Select The Form Providers That Should Be Checked For SPAM",
      "description":   "Select The Form Providers That Should Be Checked For SPAM."
    },
    {
      "key":           "user_form_providers",
      "section":       "section_user_forms",
      "premium":       true,
      "advanced":      true,
      "type":          "multiple_select",
      "default":       [ "wordpress" ],
      "value_options": [
        {
          "value_key": "buddypress",
          "text":      "BuddyPress"
        },
        {
          "value_key": "easydigitaldownloads",
          "text":      "Easy Digital Downloads"
        },
        {
          "value_key": "learnpress",
          "text":      "LearnPress"
        },
        {
          "value_key": "lifterlms",
          "text":      "LifterLMS"
        },
        {
          "value_key": "memberpress",
          "text":      "MemberPress"
        },
        {
          "value_key": "paidmembersubscriptions",
          "text":      "Paid Member Subscriptions"
        },
        {
          "value_key": "profilebuilder",
          "text":      "Profile Builder"
        },
        {
          "value_key": "ultimatemember",
          "text":      "Ultimate Member"
        },
        {
          "value_key": "woocommerce",
          "text":      "WooCommerce"
        },
        {
          "value_key": "wordpress",
          "text":      "WordPress"
        },
        {
          "value_key": "wpmembers",
          "text":      "WP Members"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "User Form Checking",
      "summary":       "Select The User Form Providers That Should Be Checked For SPAM Registrations and Logins",
      "description":   "Select The User Form Providers That Should Be Checked For SPAM Registrations and Logins"
    }
  ],
  "definitions": {
    "events": {
      "spam_form_pass": {
        "stat":    true,
        "audit":   true,
        "offense": false
      },
      "spam_form_fail": {
        "stat":  true,
        "audit": true,
        "offense": false
      }
    }
  }
}