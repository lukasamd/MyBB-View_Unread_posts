<?php
/**
 * This file is part of View Unread Posts plugin for MyBB.
 * Copyright (C) Lukasz Tkacz <lukasamd@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Disallow direct access to this file for security reasons
 *
 */
if (!defined("IN_MYBB")) {
    exit;
}

/**
 * Add plugin hooks
 *
 */
$plugins->add_hook("global_start", ['unreadPosts', 'addHooks']);
$plugins->add_hook('xmlhttp', ['unreadPosts', 'xmlhttpMarkThread']);
$plugins->add_hook('xmlhttp', ['unreadPosts', 'xmlhttpGetUnreads']);

/**
 * Standard MyBB info function
 *
 */
function unreadPosts_info()
{
    global $lang;

    $lang->load("unreadPosts");
    return Array(
        'name' => $lang->unreadPostsName,
        'description' => $lang->unreadPostsDesc,
        'website' => 'https://tkacz.pro',
        'author' => 'Lukasz Tkacz',
        'authorsite' => 'https://tkacz.pro',
        'version' => '1.13',
        'guid' => '',
        'compatibility' => '18*',
        'codename' => 'view_unread_posts',
    );
}

/**
 * Standard MyBB installation functions
 *
 */
function unreadPosts_install()
{
    require_once('unreadPosts.settings.php');
    unreadPostsInstaller::install();
}

function unreadPosts_is_installed()
{
    global $mybb;
    return (isset($mybb->settings['unreadPostsExceptions']));
}

function unreadPosts_uninstall()
{
    require_once('unreadPosts.settings.php');
    unreadPostsInstaller::uninstall();
}

/**
 * Standard MyBB activation functions
 *
 */
function unreadPosts_activate()
{
    require_once('unreadPosts.tpl.php');
    unreadPostsActivator::activate();
}

function unreadPosts_deactivate()
{
    require_once('unreadPosts.tpl.php');
    unreadPostsActivator::deactivate();
}

/*
* Template optimize
* 
*/
global $templatelist;
$templatelist .= ',unreadPosts_link,unreadPosts_counter,unreadPosts_linkCounter,unreadPosts_noData';
if (defined('THIS_SCRIPT') && THIS_SCRIPT === 'showthread.php') {
    $templatelist .= ',unreadPosts_postbit';
}
if (defined('THIS_SCRIPT') && THIS_SCRIPT === 'search.php') {
    $templatelist .= ',unreadPosts_markAllReadLink,unreadPosts_threadStartDate';
}

/**
 * Plugin Class
 *
 */
class unreadPosts
{
    // SQL Where Statement
    private static $where = '';

    // SQL Where Statement
    private static $fid = 0;

    // Thread read time
    private static $readTime = 0;

    // Thread last post time
    private static $lastPostTime = 0;

    // Is post already marked as read
    private static $already_marked = false;

    // SQL Query Limit
    private static $limit = 0;


    /**
     * Add all needed hooks
     *
     */
    public static function addHooks()
    {
        global $db, $mybb, $plugins;

        // Enable fid mode?
        if (self::getConfig('FidMode')) {
            self::$fid = (int)$mybb->input['fid'];
            if (!self::$fid && $mybb->input['tid']) {
                $tid = (int)$mybb->input['tid'];
                $result = $db->simple_select("threads", "fid", "tid='{$tid}'");
                self::$fid = (int)$db->fetch_field($result, "fid");
            }
        }

        $plugins->add_hook("member_do_register_end", ['unreadPosts', 'updateLastmark']);
        $plugins->add_hook('misc_markread_end', ['unreadPosts', 'updateLastmark']);

        if ($mybb->user['uid'] > 0) {
            $mybb->user['lastmark'] = (int)$mybb->user['lastmark'];

            $plugins->add_hook("postbit", ['unreadPosts', 'analyzePostbit']);
            $plugins->add_hook('showthread_start', ['unreadPosts', 'getReadTime']);
            $plugins->add_hook("showthread_linear", ['unreadPosts', 'markShowthreadLinear']);

            $plugins->add_hook('datahandler_post_insert_post_end', ['unreadPosts', 'newThreadOrReplyMark']);

            $plugins->add_hook('global_end', ['unreadPosts', 'actionNewpost']);
            $plugins->add_hook("pre_output_page", ['unreadPosts', 'modifyOutput']);

            $plugins->add_hook('search_start', ['unreadPosts', 'doSearch']);
            $plugins->add_hook("search_results_thread", ['unreadPosts', 'modifySearchResultThread']);
        }
    }


    /**
     * Redirect to first unread post in topic
     *
     */
    public static function actionNewpost()
    {
        global $db, $lang, $mybb, $thread;

        // Change action for guests or mode 0
        if (!self::getConfig('StatusActionUnread') || !isset($mybb->input['tid']) || THIS_SCRIPT !== 'showthread.php' || $mybb->input['action'] != 'lastpost') {
            return;
        }

        // Get thread data
        $thread = get_thread($mybb->input['tid']);

        // Visible for moderators
        $visibleonly = "AND visible='1'";
        $ismod = false;
        if (is_moderator($thread['fid'])) {
            $visibleonly = " AND (visible='1' OR visible='0')";
            $ismod = true;
        }

        // Make sure we are looking at a real thread here.
        if (!$thread['tid'] || ($thread['visible'] == 0 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true)) {
            error($lang->error_invalidthread);
        }

        // Get read data
        self::getReadTime();

        // Next, find the proper pid to link to.
        $options = array(
            "limit_start" => 0,
            "limit" => 1,
            "order_by" => "dateline",
            "order_dir" => "asc"
        );

        // Get newest post
        $query = $db->simple_select("posts", "pid",
            "tid='{$thread['tid']}' AND dateline > '" . self::$readTime . "' {$visibleonly}", $options);
        $newpost = $db->fetch_array($query);

        if ($newpost['pid']) {
            $highlight = '';
            if ($mybb->input['highlight']) {
                $string = "&";
                if ($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1)) {
                    $string = "?";
                }

                $highlight = $string . "highlight=" . $mybb->input['highlight'];
            }

            header("Location: " . htmlspecialchars_decode(get_post_link($newpost['pid'],
                    $thread['tid'])) . $highlight . "#pid{$newpost['pid']}");
            exit;
        }
    }


    /**
     * Action to mark thread read by xmlhttp
     *
     */
    public static function xmlhttpMarkThread()
    {
        global $mybb;

        if ($mybb->user['uid'] == 0 || $mybb->input['action'] != 'unreadPosts_markThread' || !isset($mybb->input['tid'])) {
            return;
        }

        $thread = get_thread($mybb->input['tid']);
        if ($thread) {
            require_once MYBB_ROOT . "inc/functions_indicators.php";
            mark_thread_read($thread['tid'], $thread['fid'], time());
        }
    }


    /**
     * Action for ajax request for upadate counter
     *
     */
    static function xmlhttpGetUnreads()
    {
        global $db, $mybb, $templates, $lang;

        if ($mybb->user['uid'] == 0 || $mybb->input['action'] != 'unreadPosts_getUnreads') {
            return;
        }

        // Enabled?
        if (!self::getConfig('StatusCounter')) {
            return;
        }

        $lang->load("unreadPosts");
        self::$fid = (int)$mybb->input['fid'];

        // Prepare sql statements
        self::buildSQLWhere();

        // Make a query to calculate unread posts
        $sql = "SELECT 1
                FROM " . TABLE_PREFIX . "posts p
                INNER JOIN " . TABLE_PREFIX . "threads t ON (p.tid = t.tid)
                LEFT JOIN " . TABLE_PREFIX . "threadsread tr ON (tr.uid = {$mybb->user['uid']} AND t.tid = tr.tid) 
                LEFT JOIN " . TABLE_PREFIX . "forumsread fr ON (fr.uid = {$mybb->user['uid']} AND t.fid = fr.fid) 
                WHERE p.visible = 1 
                  AND " . self::$where . "
                  AND p.dateline > IFNULL(tr.dateline,{$mybb->user['lastmark']}) 
                  AND p.dateline > IFNULL(fr.dateline,{$mybb->user['lastmark']}) 
                  AND p.dateline > {$mybb->user['lastmark']}
                LIMIT " . self::buildSQLLimit();
        $result = $db->query($sql);
        $numUnreads = (int)$db->num_rows($result);

        // Change counter
        if ($numUnreads > self::$limit) {
            $numUnreads = ($numUnreads - 1) . '+';
        }

        header("Content-type: text/html; charset=UTF-8");
        $content = '';

        // Hide link
        if (self::getConfig('StatusCounterHide') && $numUnreads == 0) {
            eval("\$content = \"" . $templates->get("unreadPosts_noData") . "\";");
            echo $content;
            return;
        }

        // Link without counter
        if (!self::getConfig('StatusCounter') || !self::isPageCounterAllowed()) {
            eval("\$content = \"" . $templates->get("unreadPosts_link") . "\";");
            echo $content;
            return;
        }

        // Link with counter
        eval("\$unreadPostsCounter .= \"" . $templates->get("unreadPosts_counter") . "\";");
        if ($numUnreads > 0 || self::getConfig('StatusCounterHide') == 0) {
            eval("\$content = \"" . $templates->get("unreadPosts_linkCounter") . "\";");
        }

        if (self::$fid) {
            $content = str_replace('?action=unreads', "?action=unreads&fid=" . self::$fid, $content);
        }

        echo $content;
    }


    /**
     * Get post dateline and show indicator if enabled
     *
     * @param array $post Actual post data
     * @return array Updated post data
     */
    public static function analyzePostbit(&$post)
    {
        global $lang, $mybb, $templates, $pids;
        static $tpl_indicator;

        // Compatibility with guys who can't use hooks
        if (THIS_SCRIPT !== 'showthread.php' && isset($pids) && !self::$already_marked) {
            $pids_clean = str_replace('pid IN(', '', $pids);
            $pids_clean = str_replace(')', '', $pids_clean);
            $pids_clean = str_replace("'", '', $pids_clean);
            $pids_clean = explode(',', $pids_clean);
            $pids_clean = array_map('intval', $pids_clean);
            $pid = max($pids_clean);

            if ($post_row = get_post($pid)) {
                mark_thread_read($post_row['tid'], $post_row['fid'], $post_row['dateline']);
                self::$already_marked = true;
            }
        }

        // Save last seen post id
        if ($post['dateline'] > self::$lastPostTime) {
            self::$lastPostTime = $post['dateline'];
        }

        // Is marker enabled?
        if (!self::getConfig('StatusPostbitMark')) {
            return;
        }

        // Generate indicator for template
        $post['is_unread'] = '';
        if (self::$readTime < $post['dateline']) {
            if (empty($tpl_indicator)) {
                eval("\$tpl_indicator .= \"" . $templates->get("unreadPosts_postbit") . "\";");
            }

            $post['posturl'] = str_replace('<!-- IS_UNREAD -->', $tpl_indicator, $post['posturl']);
        }
    }


    /**
     * Compare last post time with thread read time and update its.
     *
     */
    public static function markShowthreadLinear()
    {
        global $fid, $tid;

        if (self::$lastPostTime > self::$readTime) {
            mark_thread_read($tid, $fid, self::$lastPostTime);
            self::$already_marked = true;
        }
    }


    /**
     * Insert plugin read data for new reply / new thread action.
     *
     */
    public static function newThreadOrReplyMark($data)
    {
        if (empty($data->pid)) {
            return;
        }
        require_once MYBB_ROOT . "inc/functions_indicators.php";

        $post = get_post($data->pid);
        $newTime = time();
        self::$readTime = $newTime;
        mark_thread_read($post['tid'], $post['fid'], $newTime);
        self::$already_marked = true;
    }


    /**
     * Update user lastmark field after mark all forums read and registration.
     *
     */
    public static function updateLastmark()
    {
        global $db, $mybb, $user_info;

        $time = time();

        // For new members
        if (isset($user_info['uid'])) {
            $db->update_query("users", array('lastmark' => $time), "uid = '{$user_info['uid']}'");
        } // For already logged in
        else {
            $db->update_query("users", array('lastmark' => $time), "uid = '{$mybb->user['uid']}'");

            // Delete old read data
            $db->delete_query('threadsread', "uid = '{$mybb->user['uid']}'");
            $db->delete_query('forumsread', "uid = '{$mybb->user['uid']}'");
        }
    }


    /**
     * Search for threads ids with unreads posts
     *
     */
    public static function doSearch()
    {
        global $db, $lang, $mybb, $plugins, $session;

        if (!isset($mybb->input['action']) || $mybb->input['action'] != 'unreads') {
            return;
        }

        // Prepare sql statements
        self::buildSQLWhere();

        // Make a query to search topics with unread posts
        $sql = "SELECT t.tid
                FROM " . TABLE_PREFIX . "threads t
                LEFT JOIN " . TABLE_PREFIX . "threadsread tr ON (tr.uid = {$mybb->user['uid']} AND t.tid = tr.tid) 
                LEFT JOIN " . TABLE_PREFIX . "forumsread fr ON (fr.uid = {$mybb->user['uid']} AND t.fid = fr.fid) 
                WHERE " . self::$where . "
                    AND t.lastpost > IFNULL(tr.dateline,{$mybb->user['lastmark']}) 
                    AND t.lastpost > IFNULL(fr.dateline,{$mybb->user['lastmark']}) 
                    AND t.lastpost > {$mybb->user['lastmark']}
                ORDER BY t.dateline DESC
                LIMIT 500";
        $result = $db->query($sql);

        // Build a unread topics list
        $tids = [];
        while ($row = $db->fetch_array($result)) {
            $tids[] = $row['tid'];
        }

        // Decide and make a where statement
        if (sizeof($tids) > 0) {
            self::$where = 't.tid IN (' . implode(',', $tids) . ')';
        } else {
            self::$where = '1 < 0';
        }

        // Use mybb built-in search engine system
        $sid = md5(uniqid(microtime(), 1));
        $searcharray = array(
            "sid" => $db->escape_string($sid),
            "uid" => $mybb->user['uid'],
            "dateline" => TIME_NOW,
            "ipaddress" => $db->escape_binary(my_inet_pron($session->ipaddress)),
            "threads" => '',
            "posts" => '',
            "resulttype" => "threads",
            "querycache" => $db->escape_string(self::$where),
            "keywords" => ''
        );

        $plugins->run_hooks("search_do_search_process");
        $db->insert_query("searchlog", $searcharray);
        redirect("search.php?action=results&sid={$sid}", $lang->redirect_searchresults);
    }


    /**
     * Add thread start date to search results
     *
     */
    public static function modifySearchResultThread()
    {
        global $last_read, $mybb, $thread, $templates;

        // Change class for xmlhttp
        if ($thread['lastpost'] > $last_read && $last_read) {
            $thread['unreadPosts_thread'] = " thread_unread\" id=\"thread{$thread['tid']}";
        }

        // Modify start date
        $thread['startdate'] = '';
        if (self::getConfig('ThreadStartDate')) {
            $thread['startdate_date'] = my_date($mybb->settings['dateformat'], $thread['dateline']);
            $thread['startdate_time'] = my_date($mybb->settings['timeformat'], $thread['dateline']);

            eval("\$thread['startdate'] .= \"" . $templates->get("unreadPosts_threadStartDate") . "\";");
        }
    }


    /**
     * Change links action from lastpost to unread and display link to search unreads
     *
     * @param strong $content Page content
     */
    public static function modifyOutput(&$content)
    {
        global $db, $lang, $mybb, $postcount, $templates, $threadcount;
        $lang->load("unreadPosts");

        // Post marker class
        if (THIS_SCRIPT === 'showthread.php' || THIS_SCRIPT === 'search.php') {
            $css_code = '';
            eval("\$css_code .= \"" . $templates->get("unreadPosts_threadCSSCode") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_CSS -->', $css_code, $content);
        }

        // Add code for ajax refresh
        $enable_ajax = false;
        if (self::isPageCounterAllowed()
            && self::getConfig('CounterRefresh')
            && self::getConfig('StatusCounter')) {
            $enable_ajax = true;
        }

        // XMLHTTP actions
        $code = "\n\r<script src=\"jscripts/unreadPosts.js\"></script>\n\r";
        if ($enable_ajax) {
            $code .= "<script>\n\r";
            $code .= "unreadPosts.interval = " . self::getConfig("CounterRefreshInterval") . ";\n\r";
            $code .= "unreadPosts.enable = true;\n\r";
            $code .= "unreadPosts.fid = " . self::$fid . ";\n\r";
            $code .= "unreadPosts.updateCounter();\n\r";
            $code .= "</script>";
        }
        $content = str_replace('<!-- UNREADPOSTS_JS -->', $code, $content);

        // Mark all threads read link in search results
        $mark_link = '';
        if (self::getConfig('MarkAllReadLink') && THIS_SCRIPT === 'search.php'
            && ($postcount > 0 || $threadcount > 0)) {
            $post_code_string = "&amp;my_post_key={$mybb->post_code}";
            eval("\$mark_link .= \"" . $templates->get("unreadPosts_markAllReadLink") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_MARKALL -->', $mark_link, $content);
        }

        // Prepare sql statements
        self::buildSQLWhere();

        // Make a query to calculate unread posts
        $sql = "SELECT 1
                FROM " . TABLE_PREFIX . "posts p
                INNER JOIN " . TABLE_PREFIX . "threads t ON (p.tid = t.tid)
                LEFT JOIN " . TABLE_PREFIX . "threadsread tr ON (tr.uid = {$mybb->user['uid']} AND t.tid = tr.tid) 
                LEFT JOIN " . TABLE_PREFIX . "forumsread fr ON (fr.uid = {$mybb->user['uid']} AND t.fid = fr.fid) 
                WHERE p.visible = 1 
                  AND " . self::$where . "
                  AND p.dateline > IFNULL(tr.dateline,{$mybb->user['lastmark']}) 
                  AND p.dateline > IFNULL(fr.dateline,{$mybb->user['lastmark']}) 
                  AND p.dateline > {$mybb->user['lastmark']}
                LIMIT " . self::buildSQLLimit();
        $result = $db->query($sql);
        $numUnreads = (int)$db->num_rows($result);
        $unreadPosts = '';

        // Change counter
        if ($numUnreads > self::$limit) {
            $numUnreads = ($numUnreads - 1) . '+';
        }

        // Hide link
        if (self::getConfig('StatusCounterHide') && $numUnreads == 0) {
            eval("\$unreadPosts .= \"" . $templates->get("unreadPosts_noData") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_LINK -->', $unreadPosts, $content);
            return;
        }

        // Link without counter
        $unreadPosts = '';
        if (!self::getConfig('StatusCounter') || !self::isPageCounterAllowed()) {
            eval("\$unreadPosts .= \"" . $templates->get("unreadPosts_link") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_LINK -->', $unreadPosts, $content);
            return;
        }

        // Link with counter
        eval("\$unreadPostsCounter .= \"" . $templates->get("unreadPosts_counter") . "\";");
        if ($numUnreads > 0 || self::getConfig('StatusCounterHide') == 0) {
            eval("\$unreadPosts .= \"" . $templates->get("unreadPosts_linkCounter") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_LINK -->', $unreadPosts, $content);
        }

        if (self::$fid) {
            $content = str_replace('?action=unreads', "?action=unreads&fid=" . self::$fid, $content);
        }
    }


    /**
     * Get actual thread read plugin data
     *
     */
    public static function getReadTime()
    {
        global $db, $fid, $lang, $mybb, $thread;

        if (!empty(self::$readTime)) {
            return self::$readTime;
        }

        // Load lang file to showthread
        $lang->load("unreadPosts");

        $result = $db->simple_select('threadsread', 'dateline',
            "uid='{$mybb->user['uid']}' AND tid='{$thread['tid']}'");
        $time_thread = (int)$db->fetch_field($result, "dateline");

        $result = $db->simple_select('forumsread', 'dateline', "fid='{$fid}' AND uid='{$mybb->user['uid']}'");
        $time_forum = (int)$db->fetch_field($result, "dateline");

        self::$readTime = max($time_thread, $time_forum, $mybb->user['lastmark']);
        return self::$readTime;
    }


    /**
     * Helper function to decide if unread counter is allowed on current page
     *
     * @return bool Is allowed or not allowed
     */
    private static function isPageCounterAllowed()
    {
        if (THIS_SCRIPT === 'xmlhttp.php') {
            return true;
        }

        $allowedPages = explode("\n", self::getConfig('CounterPages'));
        $allowedPages = array_map("trim", $allowedPages);
        for ($i = 0; $i < sizeof($allowedPages); $i++) {
            if ($allowedPages[$i] == '') {
                unset($allowedPages[$i]);
            }
        }
        shuffle($allowedPages);

        if (empty($allowedPages) || in_array(THIS_SCRIPT, $allowedPages)) {
            return true;
        }
        return false;
    }


    /**
     * Prepare WHERE statement for unread posts search query
     *
     */
    private static function buildSQLWhere()
    {
        global $mybb;

        if (self::$where != '') {
            return;
        }

        // Standard where
        self::$where .= "t.visible = 1";

        // Search not moved
        if (self::getConfig('StatusMoved')) {
            self::$where .= " AND t.closed NOT LIKE 'moved|%'";
        }

        // Only one fid theme
        if (self::$fid) {
            self::$where .= " AND t.fid = '" . self::$fid . "'";
        }

        $exceptions = self::getConfig('Exceptions');
        if (!empty($exceptions)) {
            // All forums?
            if ($exceptions == '-1') {
                self::$where .= " AND 1 = 0";
                return;
            }

            self::$where .= " AND t.fid NOT IN (" . $exceptions . ")";
        }

        // Permissions
        $onlyusfids = array();

        // Check group permissions if we can't view threads not started by us
        $group_permissions = forum_permissions();
        foreach ($group_permissions as $fid => $forum_permissions) {
            if ($forum_permissions['canonlyviewownthreads'] == 1) {
                $onlyusfids[] = $fid;
            }
        }
        if (!empty($onlyusfids)) {
            self::$where .= " AND ((t.fid IN(" . implode(',',
                    $onlyusfids) . ") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(" . implode(',',
                    $onlyusfids) . "))";
        }

        // Unsearchable forums
        if (!function_exists('get_unsearchable_forums')) {
            require_once MYBB_ROOT . "inc/functions_search.php";
        }

        global $permissioncache, $unsearchableforums;
        $permissioncache = $unsearchableforums = false;

        $unsearchforums = get_unsearchable_forums();
        if ($unsearchforums) {
            self::$where .= " AND t.fid NOT IN ($unsearchforums)";
        }

        // Inactive forums
        $inactiveforums = get_inactive_forums();
        if ($inactiveforums) {
            self::$where .= " AND t.fid NOT IN ($inactiveforums)";
        }
    }


    /**
     * Prepare LIMIT for search query
     *
     */
    private static function buildSQLLimit()
    {
        if (!self::getConfig('StatusCounter')) {
            self::$limit = 1;
            return 1;
        }

        $limit = (int)self::getConfig('Limit');
        if (!$limit || $limit > 10000) {
            $limit = 500;
        }

        self::$limit = $limit;
        return $limit + 1;
    }


    /**
     * Helper function to get variable from config
     *
     * @param string $name Name of config to get
     * @return string Data config from MyBB Settings
     */
    public static function getConfig($name)
    {
        global $mybb;

        return $mybb->settings["unreadPosts{$name}"];
    }

}  
