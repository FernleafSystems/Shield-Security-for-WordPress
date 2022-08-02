{
  "properties":       {
    "version":                 "15.1.8",
    "release_timestamp":       1659440300,
    "build":                   "202208.0201",
    "slug_parent":             "icwp",
    "slug_plugin":             "wpsf",
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
    "wordpress": "3.7",
    "mysql":     "5.6"
  },
  "reqs_rest":        {
    "php": "7.0",
    "wp":  "5.7"
  },
  "upgrade_reqs":     {
    "10.0": {
      "php":   "7.0",
      "wp":    "3.5.2",
      "mysql": "5.5"
    },
    "12.0": {
      "php":   "7.0",
      "wp":    "3.7",
      "mysql": "5.6"
    }
  },
  "paths":            {
    "config":           "config",
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
        "global-plugin",
        "shield/ip_detect"
      ]
    },
    "plugin_admin": {
      "css": [
        "select2",
        "plugin",
        "jquery/featherlight",
        "introjs",
        "shield/datatables",
        "shield/scanners"
      ],
      "js":  [
        "select2",
        "plugin",
        "jquery/featherlight",
        "jquery/fileDownload",
        "shield/ipanalyse",
        "shield/tours",
        "shield/datatables/audit_trail",
        "shield/datatables/scans",
        "shield/datatables/traffic",
        "tp/circular-progress"
      ]
    },
    "frontend":     {
      "css": [],
      "js":  []
    },
    "register":     {
      "css": {
        "bootstrap":              {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.1.3/css/bootstrap.min.css"
        },
        "bootstrap-datepicker":   {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.min.css",
          "deps": [
            "bootstrap"
          ]
        },
        "select2":                {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css",
          "deps": [
            "plugin"
          ]
        },
        "datatables-bootstrap":   {
          "url":  "https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css",
          "deps": [
            "bootstrap"
          ]
        },
        "datatables-searchpanes": {
          "url":  "https://cdn.datatables.net/searchpanes/2.0.0/css/searchPanes.dataTables.min.css",
          "deps": [
            "datatables-bootstrap"
          ]
        },
        "datatables-select":      {
          "url":  "https://cdn.datatables.net/select/1.3.4/css/select.dataTables.min.css",
          "deps": [
            "datatables-bootstrap"
          ]
        },
        "datatables-buttons":     {
          "url":  "https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css",
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
        "shield/userprofile":     {
          "deps":   [],
          "footer": true
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
        "shield/datatables":      {
          "deps": [
            "datatables-select",
            "datatables-buttons",
            "datatables-bootstrap",
            "datatables-searchpanes",
            "tp/highlightjs"
          ]
        },
        "shield/login2fa":        {
        },
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
        "bootstrap":                     {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.1.3/js/bootstrap.bundle.min.js",
          "deps": [
            "wp-jquery"
          ]
        },
        "tp/circular-progress":          {
          "url":  "https://cdn.jsdelivr.net/gh/tomik23/circular-progress-bar@1.1.9/dist/circularProgressBar.min.js",
          "deps": [
          ]
        },
        "select2":                       {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js",
          "deps": [
            "plugin"
          ]
        },
        "bootstrap-datepicker":          {
          "url":  "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js",
          "deps": [
            "bootstrap"
          ]
        },
        "datatables":                    {
          "url":  "https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js",
          "deps": [
            "bootstrap",
            "wp-jquery"
          ]
        },
        "datatables-bootstrap":          {
          "url":  "https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js",
          "deps": [
            "datatables"
          ]
        },
        "datatables-searchpanes":        {
          "url":  "https://cdn.datatables.net/searchpanes/2.0.0/js/dataTables.searchPanes.min.js",
          "deps": [
            "datatables-bootstrap"
          ]
        },
        "datatables-select":             {
          "url":  "https://cdn.datatables.net/select/1.3.4/js/dataTables.select.min.js",
          "deps": [
            "datatables-bootstrap"
          ]
        },
        "datatables-buttons":            {
          "url":  "https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js",
          "deps": [
            "datatables-bootstrap"
          ]
        },
        "global-plugin":                 {
          "deps": [
            "wp-jquery"
          ]
        },
        "plugin":                        {
          "deps": [
            "bootstrap",
            "datatables-bootstrap",
            "global-plugin",
            "shield/navigation",
            "shield/ipanalyse",
            "shield/tables",
            "base64.min",
            "lz-string.min"
          ]
        },
        "base64.min":                    {
          "url": "https://cdn.jsdelivr.net/npm/js-base64@2.6.4/base64.min.js"
        },
        "lz-string.min":                 {},
        "jquery/fileDownload":           {},
        "jquery/steps":                  {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/jquery-steps/1.1.0/jquery.steps.min.js"
        },
        "jquery/featherlight":           {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/featherlight/1.7.13/featherlight.min.js"
        },
        "chartist":                      {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.js"
        },
        "chartist-plugin-legend":        {
          "deps": [
            "chartist"
          ]
        },
        "introjs":                       {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/intro.js/3.3.1/intro.min.js"
        },
        "shield/charts":                 {
          "deps": [
            "chartist",
            "chartist-plugin-legend",
            "plugin"
          ]
        },
        "shield/dialog":                 {
          "deps": [
            "wp-jquery-ui-dialog"
          ]
        },
        "shield/loginbot":               {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/navigation":             {},
        "shield/secadmin":               {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/tables":                 {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/ip_detect":              {
          "deps": [
            "global-plugin",
            "wp-jquery"
          ]
        },
        "shield/datatables/audit_trail": {
          "deps": [
            "shield/datatables/common"
          ]
        },
        "shield/datatables/scans":       {
          "deps": [
            "shield/datatables/common"
          ]
        },
        "shield/datatables/traffic":     {
          "deps": [
            "shield/datatables/common"
          ]
        },
        "shield/datatables/common":      {
          "deps": [
            "datatables-select",
            "datatables-buttons",
            "datatables-bootstrap",
            "datatables-searchpanes",
            "tp/highlightjs"
          ]
        },
        "shield/tours":                  {
          "deps": [
            "plugin",
            "introjs"
          ]
        },
        "shield/notbot":                 {
        },
        "shield/scans":                  {
          "deps": [
            "shield/tables"
          ]
        },
        "shield/import":                 {
          "deps": [
            "plugin"
          ]
        },
        "shield/ipanalyse":              {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/mainwp-extension":       {
          "deps": [
            "wp-jquery"
          ]
        },
        "shield/userprofile":            {
          "deps":   [
            "u2f-bundle",
            "shield/dialog"
          ],
          "footer": true
        },
        "shield/wizard":                 {
          "deps": [
            "bootstrap",
            "global-plugin",
            "jquery/steps"
          ]
        },
        "shield/login2fa":               {
          "deps": [
            "u2f-bundle",
            "wp-jquery"
          ]
        },
        "u2f-bundle":                    {},
        "tp/grecaptcha":                 {
          "url":        "https://www.google.com/recaptcha/api.js",
          "attributes": {
            "async": "async",
            "defer": "defer"
          }
        },
        "tp/hcaptcha":                   {
          "url":        "https://hcaptcha.com/1/api.js",
          "attributes": {
            "async": "async",
            "defer": "defer"
          }
        },
        "tp/highlightjs":                {
          "url": "https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.1.0/highlight.min.js"
        }
      }
    }
  },
  "menu":             {
    "show":           true,
    "top_level":      true,
    "do_submenu_fix": true,
    "has_submenu":    true
  },
  "labels":           {
    "Name":               "Shield Security",
    "MenuTitle":          "Shield Security",
    "Description":        "Ultimate WP Security Protection - Scans, 2FA, Firewall, SPAM, Activity Log, Security Admin, and so much more.",
    "Title":              "Shield Security",
    "Author":             "Shield Security",
    "AuthorName":         "Shield Security",
    "PluginURI":          "https://shsec.io/2f",
    "AuthorURI":          "https://shsec.io/bv",
    "url_img_pagebanner": "pluginlogo_banner-1544x500.png",
    "icon_url_16x16":     "pluginlogo_16x16.png",
    "icon_url_32x32":     "pluginlogo_32x32.png",
    "icon_url_128x128":   "pluginlogo_128x128.png"
  },
  "meta":             {
    "url_repo_home":            "https://shsec.io/eh",
    "privacy_policy_href":      "https://shsec.io/shieldprivacypolicy"
  },
  "plugin_meta":      [
    {
      "name": "5&#10025; Rate This Plugin",
      "href": "https://shsec.io/wpsf29"
    }
  ],
  "version_upgrades": [
    "14.1.4",
    "14.1.1",
    "11.2.0",
    "12.0.0",
    "12.0.1",
    "13.0.0"
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
  },
  "modules":          [
    "plugin",
    "data",
    "admin_access_restriction",
    "audit_trail",
    "autoupdates",
    "comments_filter",
    "comms",
    "email",
    "events",
    "firewall",
    "hack_protect",
    "headers",
    "insights",
    "integrations",
    "ips",
    "license",
    "lockdown",
    "login_protect",
    "reporting",
    "sessions",
    "traffic",
    "user_management"
  ]
}