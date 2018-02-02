{
  "properties": {
    "version": "6.2.2",
    "release_timestamp": 1517572800,
    "slug_parent": "icwp",
    "slug_plugin": "wpsf",
    "human_name": "Shield",
    "menu_title": "Shield",
    "text_domain": "wp-simple-firewall",
    "base_permissions": "manage_options",
    "wpms_network_admin_only": true,
    "logging_enabled": true,
    "show_dashboard_widget": true,
    "autoupdate": "confidence",
    "autoupdate_days": 3,
    "options_encoding": "json",
    "enable_premium": true
  },
  "requirements": {
    "php": "5.2.4",
    "wordpress": "3.5.0"
  },
  "paths": {
    "source": "src",
    "assets": "resources",
    "languages": "languages",
    "templates": "templates",
    "flags": "flags"
  },
  "includes": {
    "admin": {
      "css": [
        "global-plugin",
        "featherlight"
      ],
      "js": [
        "global-plugin",
        "featherlight"
      ]
    },
    "plugin_admin": {
      "css": [
        "bootstrap4",
        "plugin"
      ],
      "js": [
        "bootstrap4.min",
        "plugin"
      ]
    },
    "frontend": {
      "css": null
    }
  },
  "menu": {
    "show": true,
    "title": "Shield Security",
    "top_level": true,
    "do_submenu_fix": true,
    "callback": "onDisplayTopMenu",
    "icon_image": "pluginlogo_16x16.png",
    "has_submenu": true
  },
  "labels": {
    "Name": "Shield",
    "Description": "Secure Your Sites With The World's Most Powerful WordPress Security Protection System",
    "Title": "Shield",
    "Author": "iControlWP",
    "AuthorName": "iControlWP",
    "PluginURI": "http://icwp.io/home",
    "AuthorURI": "http://icwp.io/home",
    "icon_url_16x16": "pluginlogo_16x16.png",
    "icon_url_32x32": "pluginlogo_32x32.png"
  },
  "plugin_meta": [
    {
      "name": "5&#10025; Rate This Plugin",
      "href": "http://icwp.io/wpsf29"
    }
  ],
  "action_links": {
    "remove": null,
    "add": [
      {
        "name": "Dashboard",
        "url_method_name": "getPluginUrl_AdminMainPage"
      }
    ]
  }
}