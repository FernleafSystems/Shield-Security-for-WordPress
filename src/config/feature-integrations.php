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
      "slug":        "section_spam_detect",
      "title":       "SPAM Detection",
      "title_short": "SPAM Detection"
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
      "key":           "spam_contactform7",
      "section":       "section_spam_detect",
      "premium":       true,
      "type":          "checkbox",
      "default":       "N",
      "link_info":     "",
      "link_blog":     "",
      "name":          "Contact Form 7",
      "summary":       "Use AntiBot To Detect SPAM Contact Form Submissions",
      "description":   "Use AntiBot To Detect SPAM Contact Form Submissions."
    },
    {
      "key":           "spam_wpforo",
      "section":       "section_spam_detect",
      "premium":       true,
      "type":          "checkbox",
      "default":       "N",
      "link_info":     "",
      "link_blog":     "",
      "name":          "WPForo",
      "summary":       "Use AntiBot To Detect SPAM Forum Submissions",
      "description":   "Use AntiBot To Detect SPAM Submissions."
    }
  ],
  "definitions": {
    "events": {
      "contactform7_spam_pass": {
        "stat":    true,
        "audit":   true,
        "offense": false
      },
      "contactform7_spam_fail": {
        "stat":  true,
        "audit": true,
        "offense": false
      },
      "contactform7_spam":      {
        "audit":   false,
        "stat":    false,
        "offense": true
      }
    }
  }
}