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
if (!defined("IN_MYBB")) exit;

/**
 * Create plugin object
 * 
 */
$plugins->objects['unreadPosts'] = new unreadPosts();

/**
 * Standard MyBB info function
 * 
 */
function unreadPosts_info()
{
    global $lang;


    $lang->load("unreadPosts");
    
    $lang->unreadPostsDesc = '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="float:right;">' .
        '<input type="hidden" name="cmd" value="_s-xclick">' . 
        '<input type="hidden" name="hosted_button_id" value="3BTVZBUG6TMFQ">' .
        '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">' .
        '<img alt="" border="0" src="https://www.paypalobjects.com/pl_PL/i/scr/pixel.gif" width="1" height="1">' .
        '</form>' . $lang->unreadPostsDesc;

    return Array(
        'name' => $lang->unreadPostsName,
        'description' => $lang->unreadPostsDesc,
        'website' => 'https://tkacz.it',
        'author' => 'Lukasz Tkacz',
        'authorsite' => 'https://tkacz.it',
        'version' => '1.2.0',
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
$templatelist .= ',unreadPosts_link,unreadPosts_counter,unreadPosts_linkCounter';
if (THIS_SCRIPT == 'showthread.php')
{
    $templatelist .= ',unreadPosts_postbit'; 
}
if (THIS_SCRIPT == 'search.php')
{
    $templatelist .= ',unreadPosts_markAllReadLink,unreadPosts_threadStartDate'; 
}

/**
 * Plugin Class 
 * 
 */
class unreadPosts
{

    // SQL Where Statement
    private $where = '';
    
    // SQL Where Statement
    private $fid = 0;
    
    // Thread read time
    private $readTime = 0;
    
    // Thread last post time
    private $lastPostTime = 0;
    
    // Is post already marked as read
    private $already_marked = false;
    
    // SQL Query Limit
    private $limit = 0;

    /**
     * Constructor - add plugin hooks
     *      
     */
    public function __construct()
    {
        global $plugins;

        $plugins->hooks["global_start"][10]["unreadPosts_addHooks"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->addHooks();'));
        $plugins->hooks["xmlhttp"][10]["unreadPosts_xmlhttpMarkThread"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->xmlhttpMarkThread();')); 
    }
    
    /**
     * Add all needed hooks
     *      
     */
    public function addHooks()
    {
        global $db, $mybb, $plugins;

        // Enable fid mode?
        if ($this->getConfig('FidMode'))
        {
            $this->fid = (int) $mybb->input['fid'];
            if (!$this->fid && $mybb->input['tid'])
            {
                $tid = (int) $mybb->input['tid'];
                $result = $db->simple_select("threads", "fid", "tid='{$tid}'");
                $this->fid = (int) $db->fetch_field($result, "fid");
            }
        }

        $plugins->hooks["member_do_register_end"][10]["unreadPosts_updateLastmark"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->updateLastmark();'));
        $plugins->hooks["misc_markread_end"][10]["unreadPostsupdateLastmark"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->updateLastmark();'));
        
        if ($mybb->user['uid'] > 0)
        {
            $mybb->user['lastmark'] = (int) $mybb->user['lastmark'];
            
            $plugins->hooks["postbit"][10]["unreadPosts_analyzePostbit"] = array("function" => create_function('&$arg', 'global $plugins; $plugins->objects[\'unreadPosts\']->analyzePostbit($arg);'));
            $plugins->hooks["showthread_start"][10]["unreadPosts_getReadTime"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->getReadTime();'));
            $plugins->hooks["showthread_linear"][10]["unreadPosts_markShowthreadLinear"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->markShowthreadLinear();'));
            
            $plugins->hooks["newreply_do_newreply_end"][10]["unreadPosts_newThreadOrReplyMark"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->newThreadOrReplyMark();'));
            $plugins->hooks["newthread_do_newthread_end"][10]["unreadPosts_newThreadOrReplyMark"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->newThreadOrReplyMark();'));
    
            $plugins->hooks["global_end"][10]["unreadPosts_actionNewpost"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->actionNewpost();'));
            $plugins->hooks["pre_output_page"][10]["unreadPosts_modifyOutput"] = array("function" => create_function('&$arg', 'global $plugins; $plugins->objects[\'unreadPosts\']->modifyOutput($arg);'));
            $plugins->hooks["search_start"][10]["unreadPosts_doSearch"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->doSearch();'));
            $plugins->hooks["search_results_thread"][10]["unreadPosts_modifySearchResultThread"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->modifySearchResultThread();'));
            $plugins->hooks["pre_output_page"][10]["unreadPosts_pluginThanks"] = array("function" => create_function('&$arg', 'global $plugins; $plugins->objects[\'unreadPosts\']->pluginThanks($arg);'));
        }
    }

    /**
     * Redirect to first unread post in topic
     *      
     */
    public function actionNewpost()
    {
        global $db, $lang, $mybb, $thread;

        // Change action for guests or mode 0
        if (!$this->getConfig('StatusActionUnread') || !isset($mybb->input['tid']) || THIS_SCRIPT != 'showthread.php' || $mybb->input['action'] != 'lastpost')
        {
            return;
        }

        // Get thread data
        $thread = get_thread($mybb->input['tid']);
        
        // Visible for moderators
        $visibleonly = "AND visible='1'";
        $ismod = false;
        if (is_moderator($thread['fid']))
        {
        	$visibleonly = " AND (visible='1' OR visible='0')";
            $ismod = true;
        }        
        
        // Make sure we are looking at a real thread here.
        if (!$thread['tid'] || ($thread['visible'] == 0 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
        {
        	error($lang->error_invalidthread);
        }        
        
        // Get read data
        $this->getReadTime();

        // Next, find the proper pid to link to.
        $options = array(
        	"limit_start" => 0,
        	"limit" => 1,
        	"order_by" => "dateline",
        	"order_dir" => "asc"
        );
        
        // Get newest post
        $query = $db->simple_select("posts", "pid", "tid='{$thread['tid']}' AND dateline > '{$this->readTime}' {$visibleonly}", $options);
        $newpost = $db->fetch_array($query);
        
        if ($newpost['pid'])
        {
        	$highlight = '';
        	if ($mybb->input['highlight'])
        	{
        		$string = "&";
        		if ($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1))
        		{
        			$string = "?";
        		}
        
        		$highlight = $string."highlight=".$mybb->input['highlight'];
        	}
        
        	header("Location: ".htmlspecialchars_decode(get_post_link($newpost['pid'], $thread['tid'])).$highlight."#pid{$newpost['pid']}");
            exit;
        }       
    }
    
    /**
     * Action to mark thread read by xmlhttp
     * 
     */
    public function xmlhttpMarkThread()
    {
        global $db, $mybb, $lang;

        if (!$mybb->user['uid'] > 0 || $mybb->input['action'] != 'unreadPosts_markThread' || !isset($mybb->input['tid']))
        {
            return;
        }
        
        $thread = get_thread($mybb->input['tid']);
        if ($thread)
        {
            require_once MYBB_ROOT."inc/functions_indicators.php";
            mark_thread_read($thread['tid'], $thread['fid'], TIME_NOW);
        }
    }  

    /**
     * Get post dateline and show indicator if enabled
     * 
     * @param array $post Actual post data
     * @return array Updated post data
     */
    public function analyzePostbit(&$post)
    {
        global $db, $lang, $mybb, $templates, $pids;
        static $tpl_indicator;
        
        // Compatibility with guys who can't use hooks
        if (THIS_SCRIPT != 'showthread.php' && isset($pids) && !$this->already_marked)
        {
            $pids_clean = str_replace('pid IN(', '', $pids);
            $pids_clean = str_replace(')', '', $pids_clean);
            $pids_clean = str_replace("'", '', $pids_clean);
            $pids_clean = explode(',', $pids_clean);
            $pids_clean = array_map('intval', $pids_clean);
            $pid = max($pids_clean);
            
    		if ($post_row = get_post($pid))
            {
                mark_thread_read($post_row['tid'], $post_row['fid'], $post_row['dateline']);
                $this->already_marked = true;
            }
        }

        // Save last seen post id
        if ($post['dateline'] > $this->lastPostTime)
        {
            $this->lastPostTime = $post['dateline'];
        }
        
        // Is marker enabled?
        if (!$this->getConfig('StatusPostbitMark'))
        {
            return;
        }

        // Generate indicator for template
        $post['is_unread'] = '';
        if ($this->readTime < $post['dateline'])
        {
            if (empty($tpl_indicator))
            {
                eval("\$tpl_indicator .= \"" . $templates->get("unreadPosts_postbit") . "\";");
            }

            $post['posturl'] = str_replace('<!-- IS_UNREAD -->', $tpl_indicator, $post['posturl']);
        }
    }

    /**
     * Compare last post time with thread read time and update its.
     *      
     */
    public function markShowthreadLinear()
    {
        global $fid, $mybb, $tid;

        if ($this->lastPostTime > $this->readTime)
        {
            mark_thread_read($tid, $fid, $this->lastPostTime);
            $this->already_marked = true;
        }
    }

    /**
     * Insert plugin read data for new reply / new thread action.
     *      
     */
    public function newThreadOrReplyMark()
    {
        global $fid, $tid;
        
        if (isset($fid) && isset($tid))
        {
            $this->readTime = TIME_NOW;
            mark_thread_read($tid, $fid, TIME_NOW);
            $this->already_marked = true;
        }
    }

    /**
     * Update user lastmark field after mark all forums read and registration.
     *      
     */
    public function updateLastmark()
    {
        global $db, $mybb, $user_info;

        // For new members
        if (isset($user_info['uid']))
        {
            $db->update_query("users", array('lastmark' => TIME_NOW), "uid = '{$user_info['uid']}'");
        }
        // For already logged in
        else
        {
            $db->update_query("users", array('lastmark' => TIME_NOW), "uid = '{$mybb->user['uid']}'");
            
            // Delete old read data
            $db->delete_query('threadsread', "uid = '{$mybb->user['uid']}'");
            $db->delete_query('forumsread', "uid = '{$mybb->user['uid']}'");
        }
    }

    /**
     * Search for threads ids with unreads posts
     *      
     */
    public function doSearch()
    {
        global $db, $lang, $mybb, $plugins;

        if (!isset($mybb->input['action']) || $mybb->input['action'] != 'unreads')
        {
            return;
        }

        // Prepare sql statements
        $this->buildSQLWhere();

        // Make a query to search topics with unread posts
        $sql = "SELECT t.tid
                FROM " . TABLE_PREFIX . "threads t
                LEFT JOIN " . TABLE_PREFIX . "threadsread tr ON (tr.uid = {$mybb->user['uid']} AND t.tid = tr.tid) 
                LEFT JOIN " . TABLE_PREFIX . "forumsread fr ON (fr.uid = {$mybb->user['uid']} AND t.fid = fr.fid) 
                WHERE {$this->where}
                    AND t.lastpost > IFNULL(tr.dateline,{$mybb->user['lastmark']}) 
                    AND t.lastpost > IFNULL(fr.dateline,{$mybb->user['lastmark']}) 
                    AND t.lastpost > {$mybb->user['lastmark']}
                ORDER BY t.dateline DESC
                LIMIT 500";
        $result = $db->query($sql);

        // Build a unread topics list 
        while ($row = $db->fetch_array($result))
        {
            $tids[] = $row['tid'];
        }

        // Decide and make a where statement
        if (sizeof($tids) > 0)
        {
            $this->where = 't.tid IN (' . implode(',', $tids) . ')';
        }
        else
        {
            $this->where = '1 < 0';
        }

        // Use mybb built-in search engine system
        $sid = md5(uniqid(microtime(), 1));
        $searcharray = array(
            "sid" => $db->escape_string($sid),
            "uid" => $mybb->user['uid'],
            "dateline" => TIME_NOW,
            "ipaddress" => $db->escape_string($session->ipaddress),
            "threads" => '',
            "posts" => '',
            "resulttype" => "threads",
            "querycache" => $db->escape_string($this->where),
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
    public function modifySearchResultThread()
    {
        global $folder, $last_read, $mybb, $thread, $templates;

        // Change class for xmlhttp
        if ($thread['lastpost'] > $last_read && $last_read)
        {
            $thread['unreadPosts_thread'] = " thread_unread\" id=\"thread{$thread['tid']}\"";
        }

        // Modify start date
        $thread['startdate'] = '';
        if ($this->getConfig('ThreadStartDate'))
        {
            $thread['startdate_date'] = my_date($mybb->settings['dateformat'], $thread['dateline']);
            $thread['startdate_time'] = my_date($mybb->settings['timeformat'], $thread['dateline']);
            
            eval("\$thread['startdate'] .= \"" . $templates->get("unreadPosts_threadStartDate") . "\";");
        }    
    }

    /**
     * Change links action from lastpost to unread and display link to search unreads
     *      
     */
    public function modifyOutput(&$content)
    {
        global $db, $lang, $mybb, $postcount, $templates, $threadcount;
        $lang->load("unreadPosts");
        
        // Post marker class
        if (THIS_SCRIPT == 'showthread.php' || THIS_SCRIPT == 'search.php')
        {
            $css_code = '';
            eval("\$css_code .= \"" . $templates->get("unreadPosts_threadCSSCode") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_CSS -->', $css_code, $content);
        }
        
        // Search XMLHTTP
        if (THIS_SCRIPT == 'search.php')
        {
            $code = '<script type="text/javascript" src="jscripts/unreadPosts.js"></script>';
            $content = str_replace('<!-- UNREADPOSTS_JS -->', $code, $content);
        }

        // Mark all threads read link in search results
        if ($this->getConfig('MarkAllReadLink') && THIS_SCRIPT == 'search.php'
                && ($postcount > 0 || $threadcount > 0))
        {
            $post_code_string = "&amp;my_post_key={$mybb->post_code}";
            eval("\$mark_link .= \"" . $templates->get("unreadPosts_markAllReadLink") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_MARKALL -->', $mark_link, $content);
        }

        // Prepare sql statements
        $this->buildSQLWhere();

        // Make a query to calculate unread posts
        $sql = "SELECT 1
                FROM " . TABLE_PREFIX . "posts p
                INNER JOIN " . TABLE_PREFIX . "threads t ON (p.tid = t.tid)
                LEFT JOIN " . TABLE_PREFIX . "threadsread tr ON (tr.uid = {$mybb->user['uid']} AND t.tid = tr.tid) 
                LEFT JOIN " . TABLE_PREFIX . "forumsread fr ON (fr.uid = {$mybb->user['uid']} AND t.fid = fr.fid) 
                WHERE p.visible = 1 
                  AND {$this->where}
                  AND p.dateline > IFNULL(tr.dateline,{$mybb->user['lastmark']}) 
                  AND p.dateline > IFNULL(fr.dateline,{$mybb->user['lastmark']}) 
                  AND p.dateline > {$mybb->user['lastmark']}
                LIMIT " . $this->buildSQLLimit();          
        $result = $db->query($sql);     
        $numUnreads = (int) $db->num_rows($result);

        // Change counter
        if ($numUnreads > $this->limit)
        {                        
            $numUnreads = ($numUnreads - 1) . '+';
        }
        
        // Hide link
        if ($this->getConfig('StatusCounterHide') && $numUnreads == 0)
        {
            return;
        }

        // Link without counter
        if (!$this->getConfig('StatusCounter') || !$this->isPageCounterAllowed())
        {
            
            eval("\$unreadPosts .= \"" . $templates->get("unreadPosts_link") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_LINK -->', $unreadPosts, $content);
            return;
        }

        // Link with counter
        eval("\$unreadPostsCounter .= \"" . $templates->get("unreadPosts_counter") . "\";");
        if ($numUnreads > 0 || $this->getConfig('StatusCounterHide') == 0)
        {
            eval("\$unreadPosts .= \"" . $templates->get("unreadPosts_linkCounter") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_LINK -->', $unreadPosts, $content);
        }
        
        if ($this->fid)
        {
            $content = str_replace('?action=unreads', "?action=unreads&fid={$this->fid}", $content); 
        }
    }
    
    /**
     * Get actual thread read plugin data
     *      
     */
    public function getReadTime()
    {
        global $db, $fid, $lang, $mybb, $thread;

        // Load lang file to showthread
        $lang->load("unreadPosts");

        $result = $db->simple_select('threadsread', 'dateline', "uid='{$mybb->user['uid']}' AND tid='{$thread['tid']}'");
        $time_thread = (int) $db->fetch_field($result, "dateline");

        $result = $db->simple_select('forumsread', 'dateline', "fid='{$fid}' AND uid='{$mybb->user['uid']}'");
        $time_forum = (int) $db->fetch_field($result, "dateline");

        $this->readTime = max($time_thread, $time_forum, $mybb->user['lastmark']);
    }
    
    /**
     * Helper function to decide if unread counter is allowed on current page
     * 
     * @return bool Is allowed or not allowed
     */
    private function isPageCounterAllowed()
    {
        $allowedPages = explode("\n", $this->getConfig('CounterPages'));
        $allowedPages = array_map("trim", $allowedPages);
        for ($i = 0; $i < sizeof($allowedPages); $i++)
        {
            if ($allowedPages[$i] == '')
            {
                unset($allowedPages[$i]);
            }
        }
        shuffle($allowedPages);

        if (empty($allowedPages) || in_array(THIS_SCRIPT, $allowedPages))
        {
            return true;
        }

        return false;
    }
    
    /**
     * Prepare WHERE statement for unread posts search query
     *      
     */
    private function buildSQLWhere()
    {
        if ($this->where != '')
        {
            return;
        }        
    
        // Standard where
        $this->where .= "t.visible = 1";
        
        // Search not moved
        if (!$this->getConfig('StatusMoved'))
        {
            $this->where .= " AND t.closed NOT LIKE 'moved|%'"; 
        }
        
        // Only one fid theme
        if ($this->fid)
        {
            $this->where .= " AND t.fid = '{$this->fid}'";
        }

        $exceptions = $this->getConfig('Exceptions');
        if (!empty($exceptions))
        {
            // All forums?
            if ($exceptions == '-1')
            {
                $this->where .= " AND 1 = 0";
                return;    
            }

            $this->where .= " AND t.fid NOT IN (" . $exceptions . ")";
        }

        // Permissions
        $onlyusfids = array();

        // Check group permissions if we can't view threads not started by us
        $group_permissions = forum_permissions();
        foreach ($group_permissions as $fid => $forum_permissions)
        {
            if ($forum_permissions['canonlyviewownthreads'] == 1)
            {
                $onlyusfids[] = $fid;
            }
        }
        if (!empty($onlyusfids))
        {
            $this->where .= " AND ((t.fid IN(" . implode(',', $onlyusfids) . ") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(" . implode(',', $onlyusfids) . "))";
        }
        
        // Unsearchable forums
        if (!function_exists('get_unsearchable_forums'))
        {   
            require_once MYBB_ROOT."inc/functions_search.php";  
        }    
             
        global $permissioncache, $unsearchableforums;
        $permissioncache = $unsearchableforums = false;

        $unsearchforums = get_unsearchable_forums();
        if ($unsearchforums)
        {                              
            $this->where .= " AND t.fid NOT IN ($unsearchforums)";
        } 
        
        // Inactive forums
        $inactiveforums = get_inactive_forums();
        if ($inactiveforums)
        {
            $this->where .= " AND t.fid NOT IN ($inactiveforums)";
        }
    }     
    
    /**
     * Prepare LIMIT for search query
     *      
     */
    private function buildSQLLimit()
    {
        if (!$this->getConfig('StatusCounter'))
        {
            $this->limit = 1;
            return 1;        
        }
    
        $limit = (int) $this->getConfig('Limit');
        if (!$limit || $limit > 10000)
        {
            $limit = 500;
        }
        
        $this->limit = $limit;
        return $limit + 1;
    }

    /**
     * Helper function to get variable from config
     * 
     * @param string $name Name of config to get
     * @return string Data config from MyBB Settings
     */
    private function getConfig($name)
    {
        global $mybb;

        return $mybb->settings["unreadPosts{$name}"];
    }
    
    /**
     * Say thanks to plugin author - paste link to author website.
     * Please don't remove this code if you didn't make donate
     * It's the only way to say thanks without donate :)     
     */
    public function pluginThanks(&$content)
    {
        global $session, $lukasamd_thanks;
        
        if (!isset($lukasamd_thanks) && $session->is_spider)
        {
            $thx = '<div style="margin:auto; text-align:center;">This forum uses <a href="https://tkacz.it">Lukasz Tkacz</a> MyBB addons.</div></body>';
            $content = str_replace('</body>', $thx, $content);
            $lukasamd_thanks = true;
        }
    }

}  