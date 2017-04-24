{
  "properties": {
    "slug": "support",
    "name": "Premium Support",
    "show_feature_menu_item": true,
    "storage_key": "support",
    "tagline": "Premium Plugin Support Centre",
    "auto_enabled": true,
    "highlight_menu_item": true
  },
  "sections": [
    {
      "slug": "section_enable_plugin_feature_support",
      "primary": true,
      "title": "Enable Plugin Feature: Premium",
      "title_short": "Enable / Disable",
      "summary": [
        "Purpose - Contact Plugin Premium Support Centre.",
        "Recommendation - Keep the Premium Support feature turned on."
      ]
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_support",
      "section": "section_enable_plugin_feature_support",
      "default": "Y",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Enable Automatic Updates",
      "summary": "Enable (or Disable) The Premium Support Feature",
      "description": "Checking/Un-Checking this option will completely turn on/off the whole Premium Support feature"
    }
  ],
  "definitions": {
    "default_helpdesk_url": "http://icwp.io/shieldhelpdesk"
  }
}