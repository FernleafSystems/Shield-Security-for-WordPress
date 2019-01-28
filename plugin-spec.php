{
  "properties":   {
    "version":                 "7.0.2",
    "release_timestamp":       1548689761,
    "slug_parent":             "icwp",
    "slug_plugin":             "wpsf",
    "human_name":              "Shield",
    "menu_title":              "Shield",
    "text_domain":             "wp-simple-firewall",
    "base_permissions":        "manage_options",
    "wpms_network_admin_only": true,
    "logging_enabled":         true,
    "show_dashboard_widget":   true,
    "autoupdate":              "confidence",
    "autoupdate_days":         2,
    "options_encoding":        "json",
    "enable_premium":          true
  },
  "requirements": {
    "php":       "5.4.0",
    "wordpress": "3.5.0"
  },
  "paths":        {
    "source":    "src",
    "autoload":  "lib/vendor/autoload.php",
    "assets":    "resources",
    "languages": "languages",
    "templates": "templates",
    "flags":     "flags"
  },
  "includes":     {
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
        "bootstrap4",
        "plugin",
        "featherlight"
      ],
      "js":  [
        "bootstrap4.bundle.min",
        "jquery",
        "plugin",
        "featherlight",
        "jquery.fileDownload"
      ]
    },
    "frontend":     {
      "css": null
    }
  },
  "menu":         {
    "show":           true,
    "title":          "Shield Security",
    "top_level":      true,
    "do_submenu_fix": true,
    "callback":       "onDisplayTopMenu",
    "icon_image":     "pluginlogo_16x16.png",
    "has_submenu":    true
  },
  "labels":       {
    "Name":             "Shield",
    "Description":      "Ultimate WP Security Protection - Scans, 2FA, Firewall, SPAM, Audit Trail, Security Admin, and so much more.",
    "Title":            "Shield Security",
    "Author":           "One Dollar Plugin",
    "AuthorName":       "One Dollar Plugin",
    "PluginURI":        "https://icwp.io/2f",
    "AuthorURI":        "https://icwp.io/bv",
    "icon_url_16x16":   "pluginlogo_16x16.png",
    "icon_url_32x32":   "pluginlogo_32x32.png",
    "icon_url_128x128": "pluginlogo_128x128.png"
  },
  "plugin_meta":  [
    {
      "name": "5&#10025; Rate This Plugin",
      "href": "https://icwp.io/wpsf29"
    }
  ],
  "action_links": {
    "remove": null,
    "add":    [
      {
        "name":   "Dashboard",
        "title":  "Go To Shield Dashboard",
        "href":   "getPluginUrl_AdminMainPage",
        "target": "_top",
        "show":   "always"
      },
      {
        "name":      "&uarr; Go Pro &uarr;",
        "title":     "For just $1/month. Seriously.",
        "href":      "https://icwp.io/d8",
        "target":    "_blank",
        "highlight": true,
        "show":      "free"
      }
    ]
  }
}