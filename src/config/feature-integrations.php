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
      "key":           "cf7",
      "section":       "section_integrations",
      "premium":       true,
      "type":          "multiple_select",
      "default":       [],
      "value_options": [
        {
          "value_key": "antibot",
          "text":      "Use Shield's AntiBot Detection"
        },
        {
          "value_key": "human",
          "text":      "Use Shield's Human SPAM Detection"
        },
        {
          "value_key": "offense",
          "text":      "Register an IP offense when SPAM is detected by any method, not only Shield"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Contact Form 7",
      "summary":       "Contact Form 7 Integration Configuration",
      "description":   "Select the options you want to enable in Shield's Contact Form 7 Integration."
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