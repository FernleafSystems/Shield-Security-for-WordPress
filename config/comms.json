{
  "slug":        "comms",
  "properties":  {
    "slug":                  "comms",
    "storage_key":           "comms",
    "load_priority":         100,
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
      "primary":     true,
      "slug":        "section_suresend",
      "title":       "SureSend Email",
      "title_short": "SureSend Email",
      "beacon_id":   156
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
      "link_info":     "https://icwp.io/ij",
      "link_blog":     "https://icwp.io/ik",
      "name":          "SureSend Emails",
      "summary":       "SureSend Emails",
      "description":   "SureSend Emails."
    }
  ],
  "definitions": {
    "events": {
      "suresend_success": {
        "audit_params": [
          "email",
          "slug"
        ],
        "level":        "info"
      },
      "suresend_fail":    {
        "audit_params": [
          "email",
          "slug"
        ],
        "level":        "warning"
      }
    }
  }
}