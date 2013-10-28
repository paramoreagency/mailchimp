# MailChimp

A very simple ExpressionEngine plugin that adds a contact to MailChimp.

## Tag Pairs

`{exp:mailchimp:add_update_subscriber}{/exp:mailchimp:add_update_subscriber}`

`{exp:mailchimp:get_subscriber_info}{/exp:mailchimp:get_subscriber_info}`

### Parameters

	api_key				 - (string)		 - MailChimp API key.
	list_id				 - (string)		 - MailChimp list id.
	email				 - (string)		 - Email address for subscriber.
	first_name			 - (string)		 - First name of subscriber.
	last_name			 - (string)		 - Last name of subscriber.
	address:field_name	 - (string)		 - Address fields.
	custom:field_name	 - (string)		 - Custom fields.

### Variable Tags

	{success}			 - (bool)		 - Was the post a success.
	{error_string}		 - (string)		 - Error emssage from MailChimp.