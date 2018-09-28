{
  "slug":       "email",
  "properties": {
    "slug":                  "email",
    "name":                  "Email",
    "show_module_menu_item": false,
    "auto_enabled":          true,
    "storage_key":           "email",
    "show_central":          false,
    "premium":               false,
    "access_restricted":     true,
    "run_if_whitelisted":    true
  },
  "sections":   [
    {
      "slug":    "section_email_options",
      "title":   "Email Options",
      "primary": true
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":    [
    {
      "key":         "send_email_throttle_limit",
      "section":     "section_email_options",
      "default":     10,
      "type":        "integer",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Email Throttle Limit",
      "summary":     "Limit Emails Per Second",
      "description": "You throttle emails sent by this plugin by limiting the number of emails sent every second. This is useful in case you get hit by a bot attack. Zero (0) turns this off. Suggested: 10."
    }
  ]
}