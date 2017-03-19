{
  "slug": "email",
  "properties": {
    "name": "Email",
    "show_feature_menu_item": false,
    "storage_key": "email"
  },
  "sections": [
    {
      "slug": "section_email_options",
      "title": "Email Options",
      "primary": true
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "send_email_throttle_limit",
      "section": "section_email_options",
      "default": 10,
      "type": "integer",
      "link_info": "",
      "link_blog": "",
      "name": "Email Throttle Limit",
      "summary": "Limit Emails Per Second",
      "description": "You throttle emails sent by this plugin by limiting the number of emails sent every second. This is useful in case you get hit by a bot attack. Zero (0) turns this off. Suggested: 10."
    },
    {
      "key": "current_plugin_version",
      "section": "section_non_ui"
    }
  ]
}