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
 * Plugin Installator Class
 * 
 */
class unreadPostsInstaller
{

    public static function install()
    {
        global $db, $lang, $mybb;
        self::uninstall();

        $result = $db->simple_select('settinggroups', 'MAX(disporder) AS max_disporder');
        $max_disporder = $db->fetch_field($result, 'max_disporder');
        $disporder = 1;

        $settings_group = array(
            'gid' => 'NULL',
            'name' => 'unreadPosts',
            'title' => $db->escape_string($lang->unreadPostsName),
            'description' => $db->escape_string($lang->unreadPostsGroupDesc),
            'disporder' => $max_disporder + 1,
            'isdefault' => '0'
        );
        $db->insert_query('settinggroups', $settings_group);
        $gid = (int) $db->insert_id();

        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsExceptions',
            'title' => $db->escape_string($lang->unreadPostsExceptions),
            'description' => $db->escape_string($lang->unreadPostsExceptionsDesc),
            'optionscode' => 'text',
            'value' => '',
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);

        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsStatusActionUnread',
            'title' => $db->escape_string($lang->unreadPostsStatusActionUnread),
            'description' => $db->escape_string($lang->unreadPostsStatusActionUnreadDesc),
            'optionscode' => 'onoff',
            'value' => '1',
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);

        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsStatusPostbitMark',
            'title' => $db->escape_string($lang->unreadPostsStatusPostbitMark),
            'description' => $db->escape_string($lang->unreadPostsStatusPostbitMarkDesc),
            'optionscode' => 'onoff',
            'value' => '1',
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);

        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsStatusCounter',
            'title' => $db->escape_string($lang->unreadPostsStatusCounter),
            'description' => $db->escape_string($lang->unreadPostsStatusCounterDesc),
            'optionscode' => 'onoff',
            'value' => '1',
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);
        
        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsLimit',
            'title' => $db->escape_string($lang->unreadPostsLimit),
            'description' => $db->escape_string($lang->unreadPostsLimitDesc),
            'optionscode' => 'text',
            'value' => '500',
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);


        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsStatusCounterHide',
            'title' => $db->escape_string($lang->unreadPostsStatusCounterHide),
            'description' => $db->escape_string($lang->unreadPostsStatusCounterHideDesc),
            'optionscode' => 'onoff',
            'value' => '0',
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);

        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsCounterPages',
            'title' => $db->escape_string($lang->unreadPostsCounterPages),
            'description' => $db->escape_string($lang->unreadPostsCounterPagesDesc),
            'optionscode' => 'textarea',
            'value' => 'index.php',
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);

        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsMarkAllReadLink',
            'title' => $db->escape_string($lang->unreadPostsMarkAllReadLink),
            'description' => $db->escape_string($lang->unreadPostsMarkAllReadLinkDesc),
            'optionscode' => 'onoff',
            'value' => '1',
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);
        
        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsMarkerStyle',
            'title' => $db->escape_string($lang->unreadPostsMarkerStyle),
            'description' => $db->escape_string($lang->unreadPostsMarkerStyleDesc),
            'optionscode' => 'textarea',
            'value' => "color:red;\nfont-weight:bold;",
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);
        
        $setting = array(
            'sid' => 'NULL',
            'name' => 'unreadPostsThreadStartDate',
            'title' => $db->escape_string($lang->unreadPostsThreadStartDate),
            'description' => $db->escape_string($lang->unreadPostsThreadStartDateDesc),
            'optionscode' => 'onoff',
            'value' => "1",
            'disporder' => $disporder++,
            'gid' => $gid
        );
        $db->insert_query('settings', $setting);

        // Add last mark field - time when user mark all forums read
        if (!$db->field_exists("lastmark", "users"))
        {
            $db->add_column("users", "lastmark", "INT NOT NULL DEFAULT '0'");
        }

        $db->update_query("users", array("lastmark" => "regdate"), '', '', true);
        $db->update_query("settings", array("value" => "365"), "name = 'threadreadcut'");
    }

    public static function uninstall()
    {
        global $db;
        
        $result = $db->simple_select('settinggroups', 'gid', "name = 'unreadPosts'");
        $gid = (int) $db->fetch_field($result, "gid");
        
        if ($gid > 0)
        {
            $db->delete_query('settings', "gid = '{$gid}'");
        }
        $db->delete_query('settinggroups', "gid = '{$gid}'");
    }

}
