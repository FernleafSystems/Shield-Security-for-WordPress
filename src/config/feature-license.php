{
  "slug":             "license",
  "properties":       {
    "slug":                  "license",
    "name":                  "Pro Security",
    "menu_title":            "",
    "show_module_menu_item": false,
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
  "admin_notices":    {
    "wphashes-token-fail": {
      "id":               "wphashes-token-fail",
      "schedule":         "conditions",
      "valid_admin":      true,
      "plugin_page_only": true,
      "can_dismiss":      false,
      "type":             "error"
    }
  },
  "menu_items":       [
    {
      "title":     "Go PRO!",
      "slug":      "pro-redirect",
      "highlight": true
    }
  ],
  "custom_redirects": [
    {
      "source_mod_page": "pro-redirect",
      "target_mod_page": "insights",
      "query_args":      {
        "inav": "license"
      }
    }
  ],
  "sections":         [
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":          [
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
      "key":          "keyless_handshake_hash",
      "section":      "section_non_ui",
      "sensitive":    true,
      "transferable": false,
      "type":         "text",
      "default":      ""
    },
    {
      "key":          "keyless_handshake_until",
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
    },
    {
      "key":          "wphashes_api_token",
      "transferable": false,
      "section":      "section_non_ui",
      "type":         "array",
      "default":      []
    }
  ],
  "definitions":      {
    "license_store_url_api":        "https://api.getshieldsecurity.com/wp-json/odp-eddkeyless/v1",
    "keyless_cp":                   "https://shsec.io/c5",
    "license_item_name":            "Shield Security Pro",
    "license_item_id":              "6047",
    "license_item_name_sc":         "Shield Security Pro (via Shield Central)",
    "lic_verify_expire_days":       7,
    "lic_verify_expire_grace_days": 3,
    "keyless":                      true,
    "keyless_handshake_expire":     90,
    "events":                       {
      "lic_check_success":   {
        "stat": false
      },
      "lic_fail_email":      {
        "stat": false
      },
      "lic_fail_deactivate": {
        "cat":  2,
        "stat": false
      }
    }
  }
}