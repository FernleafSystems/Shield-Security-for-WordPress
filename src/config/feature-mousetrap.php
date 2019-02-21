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
      "slug":        "section_cheese",
      "primary":     true,
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
      "key":           "404_detect",
      "section":       "section_cheese",
      "default":       "disabled",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "transgression",
          "text":      "Increment Transgression"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "404 Detect",
      "summary":       "Identify A Bot When It Hits A 404",
      "description":   "Detect When A Visitor Browses To A Non-Existent Page."
    },
    {
      "key":           "link_cheese",
      "section":       "section_cheese",
      "default":       "transgression",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "transgression",
          "text":      "Increment Transgression"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Link Cheese",
      "summary":       "Tempt A Bot With A Link To Follow",
      "description":   "Detect A Bot That Follows A 'no-follow' Link."
    },
    {
      "key":           "invalid_username",
      "section":       "section_cheese",
      "default":       "transgression",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "transgression",
          "text":      "Increment Transgression"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Invalid Usernames",
      "summary":       "Detect Invalid Username Logins",
      "description":   "Identify A Bot When It Tries To Login With A Non-Existent Username."
    },
    {
      "key":           "fake_webcrawler",
      "section":       "section_cheese",
      "default":       "transgression",
      "type":          "select",
      "value_options": [
        {
          "value_key": "disabled",
          "text":      "Disabled"
        },
        {
          "value_key": "transgression",
          "text":      "Increment Transgression"
        },
        {
          "value_key": "block",
          "text":      "Immediate Block"
        }
      ],
      "link_info":     "",
      "link_blog":     "",
      "name":          "Fake Web Crawler",
      "summary":       "Detect Fake Search Engine Crawlers",
      "description":   "Identify a Bot when it presents as an official web crawler, but analysis shows it's fake."
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