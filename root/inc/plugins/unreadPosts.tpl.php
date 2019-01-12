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
 * Plugin Activator Class
 * 
 */
class unreadPostsActivator
{

    private static $tpl = array();


    private static function getTpl()
    {
        global $db;

        self::$tpl[] = array(
            "title" => 'unreadPosts_link',
            "template" => $db->escape_string('<li id="unreadCounter"><a href="{$mybb->settings[\'bburl\']}/search.php?action=unreads">{$lang->unreadPostsLink}</a></li>'),
            "sid" => "-1",
            "version" => "1.0",
            "dateline" => TIME_NOW,
        );

        self::$tpl[] = array(
            "title" => 'unreadPosts_linkCounter',
            "template" => $db->escape_string('<li id="unreadCounter"><a href="{$mybb->settings[\'bburl\']}/search.php?action=unreads">{$lang->unreadPostsLink} {$unreadPostsCounter}</a></li>'),
            "sid" => "-1",
            "version" => "1.0",
            "dateline" => TIME_NOW,
        );

        self::$tpl[] = array(
            "title" => 'unreadPosts_noData',
            "template" => $db->escape_string('<li id="unreadCounter" style="display:none;"></li>'),
            "sid" => "-1",
            "version" => "1.0",
            "dateline" => TIME_NOW,
        );

        self::$tpl[] = array(
            "title" => 'unreadPosts_counter',
            "template" => $db->escape_string('
({$numUnreads})'),
            "sid" => "-1",
            "version" => "1.0",
            "dateline" => TIME_NOW,
        );

        self::$tpl[] = array(
            "title" => 'unreadPosts_postbit',
            "template" => $db->escape_string('
<span class="post_unread_marker">{$lang->unreadPostsMarker}</span>'),
            "sid" => "-1",
            "version" => "1.0",
            "dateline" => TIME_NOW,
        );

        self::$tpl[] = array(
            "title" => 'unreadPosts_markAllReadLink',
            "template" => $db->escape_string('
<td align="left" valign="top"><a href="misc.php?action=markread{$post_code_string}" class="smalltext">{$lang->unreadPostsMarkAllRead}</a></td>'),
            "sid" => "-1",
            "version" => "1.0",
            "dateline" => TIME_NOW,
        );
        
        self::$tpl[] = array(
            "title" => 'unreadPosts_threadStartDate',
            "template" => $db->escape_string('&raquo; {$thread[\'startdate_date\']} {$thread[\'startdate_time\']}'),
            "sid" => "-1",
            "version" => "1.0",
            "dateline" => TIME_NOW,
        );

        self::$tpl[] = array(
            "title" => 'unreadPosts_threadCSSCode',
            "template" => $db->escape_string('<style>
.post_unread_marker { color:red; font-weight:bold; }
.thread_unread { cursor: pointer; }
</style>'),
            "sid" => "-1",
            "version" => "1.0",
            "dateline" => TIME_NOW,
        );
    }


    public static function activate()
    {
        global $db;
        self::deactivate();

        for ($i = 0; $i < sizeof(self::$tpl); $i++) {
            $db->insert_query('templates', self::$tpl[$i]);
        }
        find_replace_templatesets('header_welcomeblock_member_search', '#' . preg_quote('{$lang->welcome_todaysposts}</a></li>') . '#', '{$lang->welcome_todaysposts}</a></li><!-- UNREADPOSTS_LINK -->');
        find_replace_templatesets('postbit_posturl', '#' . preg_quote('<strong>') . '#', '<!-- IS_UNREAD --><strong>');

        find_replace_templatesets('search_results_posts', '#' . preg_quote('<td align="right" valign="top">{$multipage}') . '#', '<!-- UNREADPOSTS_MARKALL --><td align="right" valign="top">{$multipage}');
        find_replace_templatesets('search_results_threads', '#' . preg_quote('<td align="right" valign="top">{$multipage}') . '#', '<!-- UNREADPOSTS_MARKALL --><td align="right" valign="top">{$multipage}');
        find_replace_templatesets('search_results_threads_thread', '#' . preg_quote('{$thread[\'profilelink\']}') . '#', '{$thread[\'profilelink\']}{$thread[\'startdate\']}');
        find_replace_templatesets('search_results_threads_thread', '#' . preg_quote('{$folder}"') . '#', '{$folder}{$thread[\'unreadPosts_thread\']}"');

        find_replace_templatesets("footer", '#' . preg_quote('<!-- End task image code -->') . '#', "<!-- End task image code --><!-- UNREADPOSTS_CSS --><!-- UNREADPOSTS_JS -->");
    }


    public static function deactivate()
    {
        global $db;
        self::getTpl();

        for ($i = 0; $i < sizeof(self::$tpl); $i++) {
            $db->delete_query('templates', "title = '" . self::$tpl[$i]['title'] . "'");
        }

        require_once(MYBB_ROOT . '/inc/adminfunctions_templates.php');
        find_replace_templatesets('header_welcomeblock_member_search', '#' . preg_quote('<!-- UNREADPOSTS_LINK -->') . '#', '');
        find_replace_templatesets('postbit_posturl', '#' . preg_quote('<!-- IS_UNREAD -->') . '#', '');

        find_replace_templatesets('search_results_posts', '#' . preg_quote('<!-- UNREADPOSTS_MARKALL -->') . '#', '');
        find_replace_templatesets('search_results_threads', '#' . preg_quote('<!-- UNREADPOSTS_MARKALL -->') . '#', '');
        find_replace_templatesets('search_results_threads_thread', '#' . preg_quote('{$thread[\'startdate\']}') . '#', '');
        find_replace_templatesets('search_results_threads_thread', '#' . preg_quote('{$thread[\'unreadPosts_thread\']}') . '#', '');
        
        find_replace_templatesets("footer", '#' . preg_quote('<!-- UNREADPOSTS_CSS --><!-- UNREADPOSTS_JS -->') . '#', '');
    }
    
}
