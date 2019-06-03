{
  "slug":        "license",
  "properties":  {
    "slug":                  "license",
    "name":                  "Pro Security",
    "menu_title":            "Go Pro!",
    "show_module_menu_item": true,
    "highlight_menu_item":   true,
    "tagline":               "The Best In WordPress Security, Only Better.",
    "auto_enabled":          true,
    "storage_key":           "license",
    "show_central":          false,
    "premium":               false,
    "access_restricted":     true,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   true,
    "run_if_wpcli":          true
  },
  "sections":    [
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":          "license_key",
      "section":      "section_non_ui",
      "sensitive":    true,
      "transferable": false,
      "type":         "text",
      "default":      ""
    },
    {
      "key":          "license_activated_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "license_deactivated_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "license_last_checked_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "license_deactivated_reason",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "text",
      "default":      ""
    },
    {
      "key":          "last_warning_email_sent_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "last_deactivated_email_sent_at",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "last_errors",
      "section":      "section_non_ui",
      "transferable": false,
      "type":         "array",
      "default":      ""
    },
    {
      "key":          "last_error_at",
      "section":      "section_non_ui",
      "sensitive":    true,
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "keyless_request_hash",
      "section":      "section_non_ui",
      "sensitive":    true,
      "transferable": false,
      "type":         "text",
      "default":      ""
    },
    {
      "key":          "keyless_request_at",
      "section":      "section_non_ui",
      "sensitive":    true,
      "transferable": false,
      "type":         "integer",
      "default":      0
    },
    {
      "key":          "license_data",
      "section":      "section_non_ui",
      "sensitive":    true,
      "transferable": false,
      "type":         "array",
      "default":      []
    }
  ],
  "definitions": {
    "license_store_url":            "https://onedollarplugin.com/edd-sl/",
    "keyless_cp":                   "https://icwp.io/c5",
    "license_item_name":            "Shield Security Pro",
    "license_item_id":              "6047",
    "license_item_name_sc":         "Shield Security Pro (via Shield Central)",
    "license_item_id_sc":           "968",
    "lic_verify_expire_days":       7,
    "lic_verify_expire_grace_days": 3,
    "license_key_length":           32,
    "license_key_type":             "alphanumeric",
    "keyless":                      true,
    "keyless_handshake_expire":     90,
    "events":                       {
      "check_success":         {
        "audit": true
      },
      "check_fail_email":      {
        "audit": true
      },
      "check_fail_deactivate": {
        "audit": true
      }
    }
  }
}