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
    "run_if_whitelisted":    true,
    "run_if_wpcli":          true,
    "skip_processor":        true,
    "tracking_exclude":      true
  },
  "wpcli": {
    "enabled": false
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
      "key":     "send_throttle_limit",
      "section": "section_non_ui",
      "default": 10,
      "type":    "integer"
    }
  ]
}