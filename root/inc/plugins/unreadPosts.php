<?php
/**
 * This file is part of View Unread Posts plugin for MyBB.
 * Copyright (C) 2010-2013 Lukasz Tkacz <lukasamd@gmail.com>
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
        'website' => 'http://lukasztkacz.com',
        'author' => 'Lukasz Tkacz',
        'authorsite' => 'http://lukasztkacz.com',
        'version' => '2.9.1',
        'guid' => '2817698896addbff5ef705626b7e1a36',
        'compatibility' => '16*'
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

    rebuildsettings();
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

    rebuildsettings();
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

/**
 * Plugin Class 
 * 
 */
class unreadPosts
{

    // SQL Where Statement
    private $where = '';
    
    // Thread read time
    private $readTime = 0;
    
    // Thread last post time
    private $lastPostTime = 0;
    
    // Is post already marked as read
    private $already_marked = false;

    /**
     * Constructor - add plugin hooks
     */
    public function __construct()
    {
        global $plugins;

        $plugins->hooks["global_start"][10]["unreadPosts_addHooks"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->addHooks();'));
    }
    
    /**
     * Add all needed hooks
     */
    public function addHooks()
    {
        global $mybb, $plugins;

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
            $plugins->hooks["search_results_thread"][10]["unreadPosts_threadStartDate"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'unreadPosts\']->threadStartDate();'));
        }
    }

    /**
     * Redirect to first unread post in topic
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
        if(is_moderator($thread['fid']))
        {
        	$visibleonly = " AND (visible='1' OR visible='0')";
            $ismod = true;
        }        
        
        // Make sure we are looking at a real thread here.
        if(!$thread['tid'] || ($thread['visible'] == 0 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
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
        
        if($newpost['pid'])
        {
        	$highlight = '';
        	if($mybb->input['highlight'])
        	{
        		$string = "&";
        		if($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1))
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
        if (isset($pids) && !$this->already_marked)
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
     */
    public function doSearch()
    {
        global $db, $lang, $mybb, $plugins;

        if (!isset($mybb->input['action']) || $mybb->input['action'] != 'unreads')
        {
            return;
        }

        // Prepare sql statements
        $this->where = '';
        $this->getStandardWhere();
        $this->getExceptions();
        $this->getPermissions();
        $this->getUnsearchableForums();
        $this->getInactiveForums();

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
                LIMIT 1000";
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
     */
    public function threadStartDate()
    {
        global $mybb, $thread, $templates;
        
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
     */
    public function modifyOutput(&$content)
    {
        global $db, $lang, $mybb, $postcount, $templates, $threadcount;
        $lang->load("unreadPosts");
        
        // Post marker class
        if (THIS_SCRIPT == 'showthread.php')
        {
            $content = str_replace('<!-- UNREADPOSTS_CSS -->', $this->getCSSCode(), $content);
        }

        // Mark all threads read link in search results
        if ($this->getConfig('MarkAllReadLink') && THIS_SCRIPT == 'search.php'
                && ($postcount > 0 || $threadcount > 0))
        {
            $post_code_string = "&amp;my_post_key={$mybb->post_code}";
            eval("\$mark_link .= \"" . $templates->get("unreadPosts_markAllReadLink") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_MARKALL -->', $mark_link, $content);
        }

        // Counter is not enable, display standard link
        if (!$this->getConfig('StatusCounter') || !$this->isPageCounterAllowed())
        {
            eval("\$unreadPosts .= \"" . $templates->get("unreadPosts_link") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_LINK -->', $unreadPosts, $content);
            return;
        }

        // Prepare sql statements
        $this->where = '';
        $this->getStandardWhere();
        $this->getExceptions();
        $this->getPermissions();
        $this->getUnsearchableForums();
        $this->getInactiveForums();

        // Make a query to calculate unread posts
        $sql = "SELECT COUNT(p.pid) as num_unread
                FROM " . TABLE_PREFIX . "posts p
                INNER JOIN " . TABLE_PREFIX . "threads t ON (p.tid = t.tid)
                LEFT JOIN " . TABLE_PREFIX . "threadsread tr ON (tr.uid = {$mybb->user['uid']} AND t.tid = tr.tid) 
                LEFT JOIN " . TABLE_PREFIX . "forumsread fr ON (fr.uid = {$mybb->user['uid']} AND t.fid = fr.fid) 
                WHERE p.visible = 1 
                  AND {$this->where}
                  AND p.dateline > IFNULL(tr.dateline,{$mybb->user['lastmark']}) 
                  AND p.dateline > IFNULL(fr.dateline,{$mybb->user['lastmark']}) 
                  AND p.dateline > {$mybb->user['lastmark']}";
        $result = $db->query($sql);
        $numUnreads = (int) $db->fetch_field($result, "num_unread");

        // Check numer of unread and couter visible setting
        eval("\$unreadPostsCounter .= \"" . $templates->get("unreadPosts_counter") . "\";");
        if ($numUnreads > 0 || $this->getConfig('StatusCounterHide') == 0)
        {
            eval("\$unreadPosts .= \"" . $templates->get("unreadPosts_linkCounter") . "\";");
            $content = str_replace('<!-- UNREADPOSTS_LINK -->', $unreadPosts, $content);
        }
    }
    
    /**
     * Get actual thread read plugin data
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
     * Get CSS code for showthread
     */
    private function getCSSCode()
    {
        $css_code = '';
        if ($this->getConfig('MarkerStyle') != '')
        {
            $css_code = "<style>\n";
            $css_code .= ".post_unread_marker {\n";
            $css_code .= $this->getConfig('MarkerStyle') . "\n";
            $css_code .= "}\n";
            $css_code .= "</style>";
        } 
        
        return $css_code; 
    }

    /**
     * Get standard SQL WHERE statement - closed and moved threads are not allowed
     */
    private function getStandardWhere()
    {
        $this->where .= "t.visible = 1 AND t.closed NOT LIKE 'moved|%'";
    }

    /**
     * Get all forums exceptions to SQL WHERE statement
     */
    private function getExceptions()
    {
        if ($this->getConfig('Exceptions') == '')
        {
            return;
        }

        $exceptions_list = explode(',', $this->getConfig('Exceptions'));
        $exceptions_list = array_map('intval', $exceptions_list);

        if (sizeof($exceptions_list) > 0)
        {
            $this->where .= " AND t.fid NOT IN (" . implode(',', $exceptions_list) . ")";
        }
    }

    /**
     * Build a comma separated list of the forums this user cannot search
     *
     * @param int The parent ID to build from
     * @param int First rotation or not (leave at default)
     * @return return a CSV list of forums the user cannot search
     */
    private function getUnsearchableForums($pid="0", $first=1)
    {
        global $db, $forum_cache, $permissioncache, $mybb, $unsearchableforums, $unsearchable, $templates, $forumpass;

        $pid = intval($pid);

        if (!is_array($forum_cache))
        {
            // Get Forums
            $query = $db->simple_select("forums", "fid,parentlist,password,active", '', array('order_by' => 'pid, disporder'));
            while ($forum = $db->fetch_array($query))
            {
                $forum_cache[$forum['fid']] = $forum;
            }
        }


        if (THIS_SCRIPT == 'index.php')
        {
            $permissioncache = false;
        }

        if (!is_array($permissioncache))
        {
            $permissioncache = forum_permissions();
        }

        foreach ($forum_cache as $fid => $forum)
        {
            if ($permissioncache[$forum['fid']])
            {
                $perms = $permissioncache[$forum['fid']];
            }
            else
            {
                $perms = $mybb->usergroup;
            }

            $pwverified = 1;
            if ($forum['password'] != '')
            {
                if ($mybb->cookies['forumpass'][$forum['fid']] != md5($mybb->user['uid'] . $forum['password']))
                {
                    $pwverified = 0;
                }
            }

            $parents = explode(",", $forum['parentlist']);
            if (is_array($parents))
            {
                foreach ($parents as $parent)
                {
                    if ($forum_cache[$parent]['active'] == 0)
                    {
                        $forum['active'] = 0;
                    }
                }
            }

            if ($perms['canview'] != 1 || $perms['cansearch'] != 1 || $pwverified == 0 || $forum['active'] == 0)
            {
                if ($unsearchableforums)
                {
                    $unsearchableforums .= ",";
                }
                $unsearchableforums .= "'{$forum['fid']}'";
            }
        }
        $unsearchable = $unsearchableforums;

        // Get our unsearchable password protected forums
        $pass_protected_forums = $this->getPasswordProtectedForums();

        if ($unsearchable && $pass_protected_forums)
        {
            $unsearchable .= ",";
        }

        if ($pass_protected_forums)
        {
            $unsearchable .= implode(",", $pass_protected_forums);
        }

        if ($unsearchable)
        {
            $this->where .= " AND t.fid NOT IN ($unsearchable)";
        }
    }

    /**
     * Build a array list of the forums this user cannot search due to password protection
     *
     * @param int the fids to check (leave null to check all forums)
     * @return return a array list of password protected forums the user cannot search
     */
    private function getPasswordProtectedForums($fids=array())
    {
        global $forum_cache, $mybb;

        if (!is_array($fids))
        {
            return false;
        }

        if (!is_array($forum_cache))
        {
            $forum_cache = cache_forums();
            if (!$forum_cache)
            {
                return false;
            }
        }

        if (empty($fids))
        {
            $fids = array_keys($forum_cache);
        }

        $pass_fids = array();
        foreach ($fids as $fid)
        {
            if (empty($forum_cache[$fid]['password']))
            {
                continue;
            }

            if (md5($mybb->user['uid'] . $forum_cache[$fid]['password']) != $mybb->cookies['forumpass'][$fid])
            {
                $pass_fids[] = $fid;
                $child_list = get_child_list($fid);
            }

            if (is_array($child_list))
            {
                $pass_fids = array_merge($pass_fids, $child_list);
            }
        }
        return array_unique($pass_fids);
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
     * Get all forums premissions to SQL WHERE statement
     */
    private function getPermissions()
    {
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
    }

    /**
     * Get all inactive forums
     */
    private function getInactiveForums()
    {
        $inactiveforums = get_inactive_forums();
        if ($inactiveforums)
        {
            $this->where .= " AND t.fid NOT IN ($inactiveforums)";
        }
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
            $thx = '<div style="margin:auto; text-align:center;">This forum uses <a href="http://lukasztkacz.com">Lukasz Tkacz</a> MyBB addons.</div></body>';
            $content = str_replace('</body>', $thx, $content);
            $lukasamd_thanks = true;
        }
    }

}
