{
  "slug":         "insights",
  "properties":   {
    "slug":                  "insights",
    "name":                  "Dashboard",
    "menu_title":            "Security Dashboard",
    "menu_priority":         "5",
    "show_module_menu_item": true,
    "show_module_options":   true,
    "auto_enabled":          true,
    "storage_key":           "insights",
    "show_central":          false,
    "premium":               false,
    "access_restricted":     true,
    "run_if_whitelisted":    true,
    "run_if_verified_bot":   false
  },
  "requirements": {
    "php": {
      "version": "5.4"
    }
  },
  "sections":     [
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":      [
  ]
}