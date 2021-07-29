{
  "properties":       {
    "version":                 "11.5.1",
    "release_timestamp":       1627565600,
    "build":                   "202107.2901",
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
    "wordpress": "3.7"
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
        "jquery/featherlight",
        "introjs",
        "shield/scanners"
      ],
      "js":  [
        "select2",
        "plugin",
        "jquery/featherlight",
        "jquery/fileDownload",
        "shield/tours",
        "bootstrap-select",
        "shield/scanners"
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
        "bootstrap-datepicker":   {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.min.css",
          "deps": [
            "bootstrap"
          ]
        },
        "bootstrap-select":       {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.18/css/bootstrap-select.min.css",
          "deps": [
            "bootstrap"
          ]
        },
        "select2":                {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css",
          "deps": [
            "plugin"
          ]
        },
        "datatables-bootstrap":   {
          "url":  "https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css",
          "deps": [
            "bootstrap"
          ]
        },
        "datatables-select":      {
          "url":  "https://cdn.datatables.net/select/1.3.3/css/select.dataTables.min.css",
          "deps": [
            "datatables-bootstrap"
          ]
        },
        "datatables-buttons":     {
          "url":  "https://cdn.datatables.net/buttons/1.7.1/css/buttons.dataTables.min.css",
          "deps": [
            "datatables-bootstrap"
          ]
        },
        "global-plugin":          {},
        "plugin":                 {
          "deps": [
            "bootstrap",
            "global-plugin"
          ]
        },
        "shield/wizard":          {
          "deps": [
            "bootstrap",
            "global-plugin"
          ]
        },
        "jquery/featherlight":    {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/featherlight/1.7.13/featherlight.min.css"
        },
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
        "shield/dialog":          {
          "deps":   [
            "wp-wp-jquery-ui-dialog"
          ],
          "footer": true
        },
        "shield/mainwp":          {},
        "shield/scanners":        {
          "deps": [
            "datatables-select",
            "datatables-buttons",
            "datatables-bootstrap",
            "tp/highlightjs"
          ]
        },
        "tp/highlightjs":         {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.1.0/styles/default.min.css"
        }
      },
      "js":  {
        "bootstrap":              {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js",
          "deps": [
            "wp-jquery"
          ]
        },
        "select2":                {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js",
          "deps": [
            "plugin"
          ]
        },
        "bootstrap-datepicker":   {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js",
          "deps": [
            "bootstrap"
          ]
        },
        "bootstrap-select":       {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.18/js/bootstrap-select.min.js",
          "deps": [
            "bootstrap"
          ]
        },
        "datatables":             {
          "url":  "https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js",
          "deps": [
            "bootstrap",
            "wp-jquery"
          ]
        },
        "datatables-bootstrap":   {
          "url":  "https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js",
          "deps": [
            "datatables"
          ]
        },
        "datatables-select":      {
          "url":  "https://cdn.datatables.net/select/1.3.3/js/dataTables.select.min.js",
          "deps": [
            "datatables"
          ]
        },
        "datatables-buttons":     {
          "url":  "https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js",
          "deps": [
            "datatables-bootstrap"
          ]
        },
        "global-plugin":          {
          "deps": [
            "wp-jquery"
          ]
        },
        "plugin":                 {
          "deps": [
            "bootstrap",
            "datatables-bootstrap",
            "global-plugin",
            "shield/navigation",
            "base64.min",
            "lz-string.min"
          ]
        },
        "base64.min":             {
          "url": "https://cdn.jsdelivr.net/npm/js-base64@2.6.4/base64.min.js"
        },
        "lz-string.min":          {},
        "jquery/fileDownload":    {},
        "jquery/steps":           {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/jquery-steps/1.1.0/jquery.steps.min.js"
        },
        "jquery/featherlight":    {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/featherlight/1.7.13/featherlight.min.js"
        },
        "chartist":               {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.js"
        },
        "chartist-plugin-legend": {
          "deps": [
            "chartist"
          ]
        },
        "introjs":                {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/intro.js/3.3.1/intro.min.js"
        },
        "shield/charts":          {
          "deps": [
            "chartist",
            "chartist-plugin-legend",
            "plugin"
          ]
        },
        "shuffle":                {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/Shuffle/5.3.0/shuffle.min.js"
        },
        "shield/shuffle":         {
          "deps": [
            "shuffle"
          ]
        },
        "shield/dialog":          {
          "deps": [
            "wp-jquery-ui-dialog"
          ]
        },
        "shield/comments":        {
          "deps":   [
            "wp-jquery"
          ],
          "footer": true
        },
        "shield/loginbot":        {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/navigation":      {},
        "shield/secadmin":        {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/tables":          {
          "deps": [
            "plugin"
          ]
        },
        "shield/scanners":        {
          "deps": [
            "shield/scantables"
          ]
        },
        "shield/scantables":      {
          "deps": [
            "datatables-select",
            "datatables-buttons",
            "datatables-bootstrap",
            "tp/highlightjs"
          ]
        },
        "shield/tours":           {
          "deps": [
            "plugin",
            "introjs"
          ]
        },
        "shield/notbot":          {
        },
        "shield/scans":           {
          "deps": [
            "shield/tables"
          ]
        },
        "shield/import":          {
          "deps": [
            "plugin"
          ]
        },
        "shield/ipanalyse":       {
          "deps": [
            "plugin"
          ]
        },
        "shield/mainwp":          {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/userprofile":     {
          "deps":   [
            "u2f-bundle",
            "shield/dialog"
          ],
          "footer": true
        },
        "shield/wizard":          {
          "deps": [
            "bootstrap",
            "global-plugin",
            "jquery/steps"
          ]
        },
        "u2f-bundle":             {},
        "tp/grecaptcha":          {
          "url":        "https://www.google.com/recaptcha/api.js",
          "attributes": {
            "async": "async",
            "defer": "defer"
          }
        },
        "tp/hcaptcha":            {
          "url":        "https://hcaptcha.com/1/api.js",
          "attributes": {
            "async": "async",
            "defer": "defer"
          }
        },
        "tp/highlightjs":         {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.1.0/highlight.min.js"
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
    "10.2.1",
    "11.2.0"
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
        "title":     "Get All PRO Security Features",
        "href":      "https://shsec.io/d8",
        "target":    "_blank",
        "highlight": true,
        "show":      "free"
      }
    ]
  }
}