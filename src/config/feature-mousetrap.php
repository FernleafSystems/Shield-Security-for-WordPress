{
  "slug":        "mousetrap",
  "properties":  {
    "slug":                  "mousetrap",
    "name":                  "MouseTrap",
    "show_module_menu_item": false,
    "show_module_options":   true,
    "storage_key":           "mousetrap",
    "tagline":               "Identify, Trap and Block Bots",
    "show_central":          true,
    "access_restricted":     true,
    "premium":               false,
    "run_if_whitelisted":    false,
    "run_if_verified_bot":   false,
    "order":                 30
  },
  "sections":    [
    {
      "slug":        "section_linkcheese",
      "title":       "Tempt Bots With Links",
      "title_short": "Link Cheese",
      "summary":     [
        "Recommendation - Enable to capture bots/spiders that don't honour 'nofollow' directives."
      ]
    },
    {
      "slug":        "section_enable_plugin_feature_mousetrap",
      "title":       "Enable Module: MouseTrap",
      "title_short": "Enable Module",
      "summary":     [
        "Purpose - MouseTrap monitors a typical set of bot behaviours to help identify probing bots.",
        "Recommendation - Enable as many mouse traps as possible."
      ]
    },
    {
      "slug":   "section_non_ui",
      "hidden": true
    }
  ],
  "options":     [
    {
      "key":         "enable_mousetrap",
      "section":     "section_enable_plugin_feature_mousetrap",
      "default":     "Y",
      "type":        "checkbox",
      "link_info":   "",
      "link_blog":   "",
      "name":        "Enable MouseTrap",
      "summary":     "Enable (or Disable) The MouseTrap module",
      "description": "Un-Checking this option will completely disable the MouseTrap module"
    },
    {
      "key":          "insights_last_firewall_block_at",
      "transferable": false,
      "section":      "section_non_ui",
      "default":      0
    }
  ],
  "definitions": {
  }
}