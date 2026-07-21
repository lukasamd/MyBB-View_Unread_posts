# View Unread Posts for MyBB

This plugin adds a "view unread posts" function for all registered users - additional search engine option, like the same function in phpBB3.

Version 2.x targets current MyBB 1.8.x installations and PHP 8.x. It no longer requires replacing MyBB's core `inc/functions_indicators.php` file. If you used an older version of this plugin, restore the original MyBB core file before upgrading.

There is a lot of settings and options:

* Exception forums list: admin can disable every forums from search
* Unread post marker in thread view: small, red info "Unread post"
* Counter and link: there is new link to search unread posts with optional counter (how many unreads)
* Mark topic as read from search results: user can mark any thread as read from search results view, just click topic icon (AJAX)
* Forum search mode: there is option to search only on specific forum, where user is
* Ajax counter refresh
