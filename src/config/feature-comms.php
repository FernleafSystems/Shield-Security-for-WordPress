{
  "slug":        "comms",
  "properties":  {
    "slug":                  "comms",
    "storage_key":           "comms",
    "name":                  "Comms",
    "menu_title":            "Comms",
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
    "tracking_exclude":      true
  },
  "wpcli":       {
    "enabled": true
  },
  "sections":    [
    {
      "slug":        "section_suresend",
      "primary":     true,
      "title":       "SureSend Email",
      "title_short": "SureSend Email"
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":           "suresend_emails",
      "section":       "section_suresend",
      "type":          "multiple_select",
      "premium":       true,
      "default":       [],
      "value_options": [
        {
          "value_key": "2fa",
          "text":      "2FA Login Codes (admins only)"
        }
      ],
      "link_info":     "https://shsec.io/ih",
      "link_blog":     "",
      "name":          "SureSend Emails",
      "summary":       "SureSend Emails",
      "description":   "SureSend Emails."
    }
  ],
  "definitions": {
    "events": {
      "suresend_success": {
      },
      "suresend_fail":    {
      }
    }
  }
}