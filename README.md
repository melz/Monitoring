Loading Deck Monitoring
=======

This is the Loading Deck monitoring application that was tested by users on the LowEndTalk forum. It was found to be mostly stable and is now being released to the public free of charge.

It was developed by [James Hadley](http://www.sysadmin.co.uk/) under [Loading Deck Limited](http://www.loadingdeck.com). If you like the software, please consider hiring Loading Deck as your web consultants, or [make a small donation](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=F7PX6QH7S7TTJ) to my coffee fund.

Key features of this software include server resource monitoring, application monitoring, (very) good performance and highly flexible alerting policies.

### Basic Installation
1. Get a (virtual) server. This app is quite demanding and doesn't work well on shared hosting. 1GB RAM is probably enough.
2. Install a web server and PHP 5.5. We recommend Apache because it's easy to use and doesn't involve writing lots of additional rewrite rules.
3. Install the Phalcon PHP extension.
4. Clone the repository using Git
5. Point your web server to the "public" directory
6. Change the necessary parameters in app/config/config.php
7. Import the SQL file
8. Add `php /path/to/install/app/cli.php Main` to your crontab to run every 5 minutes
9. Add `php /path/to/install/app/cli.php Alert` to your crontab to run every 5 minutes
10. Log in with username "admin@nobody.zz" and password "123456" at URL "/admin/login"

### Additional Steps To Use App Monitoring
App monitoring uses distributed hosts to poll your application. We used a few DigitalOcean VMs during our testing. If you wish to use this, the steps are:
11. Get several small (virtual) servers. Shared hosting may be sufficient.
12. Install a PHP server on them. PHP 5.4 and Apache are currently recommended but anything should do for this.
13. Upload the fsockopen.php file to the web root and change the KEY value at the top to what's set in your config
14. Add the `host => IP` key pairs to your config.php file
15. Add `php /path/to/install/app/cli.php Worker &` to your startup scripts

If you need additional help, please consider our [forum](http://community.loadingdeck.com) that you can use to discuss the software with us. Paid support and development are also available by email and telephone (see above).

### Bugs and Contributions
Both issues and PR are welcome via the Github repository. If you know Phalcon then we'd appreciate you taking the time to send us a PR. Issues and PRs will be reviewed within our spare time. We are aware of a number of bugs already, from both before and during the transition away from SaaS. Use this software at your own risk!

### Third Party Credits
 * [Rickshaw](http://code.shutterstock.com/rickshaw/)
 * [Bootstrap](http://www.getbootstrap.com)
 * [JQuery](http://www.jquery.com)

Please let us know if we've forgotten anyone.
