# LifterLMS Discounted Resubscriptions
It provides the option for existing members to renew their LiterLMS memberships at discounted price.

### Notes
- This addon requires LifterLMS plugin to be installed and configured. 
- Since, it's a custom plugin made for a specific project therefore, use it on your own risk.

### Detail
- Once the plugin is installed, it will create a new LifterLMS settings page to offer a discounted price for the membership renewals.
- It also provides the option to draft a customized email with a unique link to be sent to user in order to resubscribe to avail the discounted price.
- The plugin uses cron job and sends the email with the discount link before two day (or whichever is configured from the settings page) of their expiration date.
- If the user clicks on the link, it will redirect the user to LifterLMS's checkout page having the same membership plan but with discounted price to checkout. 
- If the user clicks the link before the two days of expiry or after the expiry date, the discounted link will not work and the user will have the original price to renew.
