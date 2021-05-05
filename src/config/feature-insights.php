{
  "slug":         "insights",
  "properties":   {
    "slug":                  "insights",
    "name":                  "Dashboard",
    "menu_title":            "Security Dashboard",
    "menu_priority":         "5",
    "show_module_menu_item": true,
    "show_module_options":   false,
    "auto_enabled":          true,
    "storage_key":           "insights",
    "show_central":          false,
    "premium":               false,
    "access_restricted":     true,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   false,
    "run_if_wpcli":          false,
    "skip_processor":        true,
    "tracking_exclude":      true
  },
  "wpcli": {
    "enabled": false
  },
  "sections":     [
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":      [
  ],
  "definitions": {
    "wizards":                         {
      "welcome": {
        "title":                "Getting Started Setup Wizard",
        "desc":                 "An introduction to this security plugin, helping you get setup and started quickly with the core features.",
        "min_user_permissions": "manage_options",
        "steps":                {
          "welcome":                  {
            "security_admin": false,
            "title":          "Welcome"
          },
          "ip_detect":                {
            "title": "IP Detection"
          },
          "admin_access_restriction": {
            "title": "Security Admin"
          },
          "audit_trail":              {
            "title": "Audit Trail"
          },
          "ips":                      {
            "title": "IP Blacklist"
          },
          "login_protect":            {
            "title": "Login Protection"
          },
          "comments_filter":          {
            "title": "Comment SPAM"
          },
          "how_shield_works":         {
            "title": "How Shield Works"
          },
          "optin":                    {
            "title": "Join Us!"
          },
          "thankyou":                 {
            "security_admin": false,
            "title":          "Thank You!"
          }
        }
      }
    }
  }
}