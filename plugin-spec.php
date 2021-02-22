{
  "properties":       {
    "version":                 "10.2.5",
    "release_timestamp":       1613989600,
    "build":                   "202102.2201",
    "slug_parent":             "icwp",
    "slug_plugin":             "wpsf",
    "human_name":              "Shield Security",
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
    "php":       "7.0",
    "wordpress": "3.5.2"
  },
  "upgrade_reqs":     {
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
        "global-plugin"
      ]
    },
    "plugin_admin": {
      "css": [
        "bootstrap-select.min",
        "plugin",
        "featherlight"
      ],
      "js":  [
        "bootstrap-select.min",
        "plugin",
        "featherlight",
        "jquery.fileDownload"
      ]
    },
    "frontend":     {
      "css": [],
      "js":  []
    },
    "register":     {
      "css": {
        "bootstrap4.min":         {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css"
        },
        "bootstrap-select.min":   {
          "deps": [
            "bootstrap4.min"
          ]
        },
        "bootstrap-datepicker":   {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.min.css",
          "deps": [
            "bootstrap4.min"
          ]
        },
        "global-plugin":          {},
        "plugin":                 {
          "deps": [
            "bootstrap4.min",
            "global-plugin"
          ]
        },
        "wizard":                 {
          "deps": [
            "bootstrap4.min",
            "global-plugin"
          ]
        },
        "featherlight":           {},
        "chartist.min":           {},
        "chartist-plugin-legend": {
          "deps": [
            "chartist.min"
          ]
        },
        "introjs.min":            {}
      },
      "js":  {
        "bootstrap4.bundle.min":   {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js",
          "deps": [
            "wp-jquery"
          ]
        },
        "bootstrap-select.min":    {
          "deps": [
            "bootstrap4.bundle.min"
          ]
        },
        "bootstrap-datepicker":    {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js",
          "deps": [
            "bootstrap4.bundle.min"
          ]
        },
        "global-plugin":           {
          "deps": [
            "wp-jquery"
          ]
        },
        "plugin":                  {
          "deps": [
            "bootstrap4.bundle.min",
            "global-plugin",
            "base64.min",
            "lz-string.min"
          ]
        },
        "base64.min":              {
          "url": "https://cdn.jsdelivr.net/npm/js-base64@2.6.4/base64.min.js"
        },
        "lz-string.min":           {},
        "jquery.fileDownload":     {
          "deps": [
            "wp-jquery"
          ]
        },
        "featherlight":            {
          "deps": [
            "wp-jquery"
          ]
        },
        "chartist.min":            {},
        "chartist-plugin-legend":  {
          "deps": [
            "chartist.min"
          ]
        },
        "charts":                  {
          "deps": [
            "chartist-plugin-legend"
          ]
        },
        "shuffle":                 {},
        "shield-card-shuffle":     {
          "deps": [
            "shuffle"
          ]
        },
        "introjs.min":             {},
        "shield/tables":           {
          "deps": [
            "plugin"
          ]
        },
        "shield/antibot":            {
          "deps":    [],
          "dynamic": true,
          "footer":  true
        },
        "shield/scans":            {
          "deps": [
            "shield/tables"
          ]
        },
        "shield/import":           {
          "deps": [
            "plugin"
          ]
        },
        "shield/ipanalyse":        {
          "deps": [
            "plugin"
          ]
        },
        "shield/mainwp-extension": {
          "deps": [
            "jquery"
          ]
        },
        "shield/userprofile":      {
          "deps": [
            "global-plugin"
          ]
        },
        "u2f-bundle":              {},
        "shield/u2f-admin":        {
          "deps": [
            "u2f-bundle",
            "wp-jquery"
          ]
        }
      }
    }
  },
  "menu":             {
    "show":           true,
    "title":          "Shield Security",
    "top_level":      true,
    "do_submenu_fix": true,
    "icon_image":     "pluginlogo_16x16.png",
    "has_submenu":    true
  },
  "labels":           {
    "Name":             "Shield Security",
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
    "9.1.1",
    "9.2.0",
    "9.2.2",
    "10.1.0",
    "10.2.1"
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