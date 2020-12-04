{
  "properties":       {
    "version":                 "10.1.4",
    "release_timestamp":       1607089135,
    "build":                   "202012.0401",
    "slug_parent":             "icwp",
    "slug_plugin":             "wpsf",
    "human_name":              "Shield",
    "menu_title":              "Shield",
    "text_domain":             "wp-simple-firewall",
    "base_permissions":        "manage_options",
    "wpms_network_admin_only": true,
    "logging_enabled":         true,
    "show_dashboard_widget":   true,
    "show_admin_bar_menu":     true,
    "autoupdate":              "confidence",
    "autoupdate_days":         2,
    "options_encoding":        "json",
    "enable_premium":          true
  },
  "requirements":     {
    "php":       "7.0.0",
    "wordpress": "3.5.2"
  },
  "upgrade_reqs":     {
    "7.0":  {
      "php": "5.4",
      "wp":  "3.5.2"
    },
    "10.0": {
      "php": "7.0",
      "wp":  "3.5.2"
    }
  },
  "paths":            {
    "source":           "src",
    "autoload":         "lib/vendor/autoload.php",
    "assets":           "resources",
    "languages":        "languages",
    "templates":        "templates",
    "custom_templates": "shield_templates",
    "flags":            "flags",
    "cache":            "shield"
  },
  "includes":         {
    "admin":        {
      "css": [
        "global-plugin"
      ],
      "js":  [
        "jquery",
        "global-plugin"
      ]
    },
    "plugin_admin": {
      "css": [
        "bootstrap4.min",
        "bootstrap-select.min",
        "global-plugin",
        "plugin",
        "featherlight"
      ],
      "js":  [
        "bootstrap4.bundle.min",
        "bootstrap-select.min",
        "jquery",
        "global-plugin",
        "plugin",
        "base64.min",
        "lz-string.min",
        "featherlight",
        "jquery.fileDownload"
      ]
    },
    "frontend":     {
      "css": null
    }
  },
  "menu":             {
    "show":           true,
    "title":          "Shield Security",
    "top_level":      true,
    "do_submenu_fix": true,
    "callback":       "onDisplayTopMenu",
    "icon_image":     "pluginlogo_16x16.png",
    "has_submenu":    true
  },
  "labels":           {
    "Name":             "Shield",
    "Description":      "Ultimate WP Security Protection - Scans, 2FA, Firewall, SPAM, Audit Trail, Security Admin, and so much more.",
    "Title":            "Shield Security",
    "Author":           "Shield Security",
    "AuthorName":       "Shield Security",
    "PluginURI":        "https://shsec.io/2f",
    "AuthorURI":        "https://shsec.io/bv",
    "icon_url_16x16":   "pluginlogo_16x16.png",
    "icon_url_32x32":   "pluginlogo_32x32.png",
    "icon_url_128x128": "pluginlogo_128x128.png"
  },
  "meta":             {
    "url_repo_home":            "https://shsec.io/eh",
    "announcekit_changelog_id": "3ObUvS",
    "privacy_policy_href":      "https://shsec.io/shieldprivacypolicy"
  },
  "plugin_meta":      [
    {
      "name": "5&#10025; Rate This Plugin",
      "href": "https://shsec.io/wpsf29"
    }
  ],
  "version_upgrades": [
    "9.0.0",
    "9.0.3",
    "9.0.5",
    "9.1.1",
    "9.2.0",
    "9.2.2",
    "10.1.0"
  ],
  "action_links":     {
    "remove": null,
    "add":    [
      {
        "name":   "Security Dashboard",
        "title":  "Go To Security Dashboard",
        "href":   "getPluginUrl_AdminMainPage",
        "target": "_top",
        "show":   "always"
      },
      {
        "name":      "&uarr; Go Pro &uarr;",
        "title":     "For just $1/month. Seriously.",
        "href":      "https://shsec.io/d8",
        "target":    "_blank",
        "highlight": true,
        "show":      "free"
      }
    ]
  }
}