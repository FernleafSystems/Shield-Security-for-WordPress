{
  "properties":       {
    "version":                 "11.1.1",
    "release_timestamp":       1618305000,
    "build":                   "202104.1301",
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
        "select2",
        "plugin",
        "featherlight",
        "introjs",
        "bootstrap-select"
      ],
      "js":  [
        "select2",
        "plugin",
        "featherlight",
        "jquery.fileDownload",
        "shield/tours",
        "bootstrap-select"
      ]
    },
    "frontend":     {
      "css": [],
      "js":  []
    },
    "register":     {
      "css": {
        "bootstrap":              {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css"
        },
        "select2":                {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css",
          "deps": [
            "plugin"
          ]
        },
        "bootstrap-datepicker":   {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.min.css",
          "deps": [
            "bootstrap"
          ]
        },
        "bootstrap-select":   {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.18/css/bootstrap-select.min.css",
          "deps": [
            "bootstrap"
          ]
        },
        "global-plugin":          {},
        "plugin":                 {
          "deps": [
            "bootstrap",
            "global-plugin"
          ]
        },
        "wizard":                 {
          "deps": [
            "bootstrap",
            "global-plugin"
          ]
        },
        "featherlight":           {},
        "chartist":               {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.css"
        },
        "chartist-plugin-legend": {
          "deps": [
            "chartist"
          ]
        },
        "introjs":                {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/intro.js/3.3.1/introjs.min.css"
        },
        "shield/charts":          {
          "deps": [
            "plugin"
          ]
        },
        "shield/mainwp": {}
      },
      "js":  {
        "bootstrap":               {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js",
          "deps": [
            "wp-jquery"
          ]
        },
        "select2":                 {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js",
          "deps": [
            "plugin"
          ]
        },
        "bootstrap-datepicker":    {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js",
          "deps": [
            "bootstrap"
          ]
        },
        "bootstrap-select":   {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.18/js/bootstrap-select.min.js",
          "deps": [
            "bootstrap"
          ]
        },
        "global-plugin":           {
          "deps": [
            "wp-jquery"
          ]
        },
        "plugin":                  {
          "deps": [
            "bootstrap",
            "global-plugin",
            "shield/navigation",
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
        "chartist":                {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.js"
        },
        "chartist-plugin-legend":  {
          "deps": [
            "chartist"
          ]
        },
        "introjs":                 {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/intro.js/3.3.1/intro.min.js"
        },
        "shield/charts":           {
          "deps": [
            "chartist",
            "chartist-plugin-legend",
            "plugin"
          ]
        },
        "shuffle":                 {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/Shuffle/5.3.0/shuffle.min.js"
        },
        "shield/shuffle":          {
          "deps": [
            "shuffle"
          ]
        },
        "shield/comments":         {
          "deps":   [
            "wp-jquery"
          ],
          "footer": true
        },
        "shield/loginbot":         {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/navigation":         {},
        "shield/secadmin":           {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/tables":           {
          "deps": [
            "plugin"
          ]
        },
        "shield/tours":            {
          "deps": [
            "plugin",
            "introjs"
          ]
        },
        "shield/antibot":          {
          "footer": true
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
        "shield/mainwp": {
          "deps": [
            "wp-jquery"
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
        },
        "tp/grecaptcha":           {
          "url": "https://www.google.com/recaptcha/api.js",
          "attributes": {
            "async": "async",
            "defer": "defer"
          }
        },
        "tp/hcaptcha":             {
          "url": "https://hcaptcha.com/1/api.js",
          "attributes": {
            "async": "async",
            "defer": "defer"
          }
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
        "href":   "getPluginUrl_DashboardHome",
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