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
 * Plugin Installator Class
 * 
 */
class unreadPostsInstaller {

    public static function install() {
        global $db, $lang, $mybb;
        self::uninstall();
		
		$result = $db->simple_select("settinggroups", "COUNT(*) as rows");
		$rows = $db->fetch_field($result, "rows");	
		$setting_group = array(
			'name' => 'unreadPosts',
			'title' => $db->escape_string($lang->unreadPostsName),
            'description' => $db->escape_string($lang->unreadPostsGroupDesc),
            'disporder' => $rows+1,
            'isdefault' => 0
		);		
		$gid = $db->insert_query("settinggroups", $setting_group);

        $setting_array = array(
			'unreadPostsExceptions' => array(
				'title' => $db->escape_string($lang->unreadPostsExceptions),
				'description' => $db->escape_string($lang->unreadPostsExceptionsDesc),
				'optionscode' => 'forumselect',
				'value' => ''
			),
			'unreadPostsStatusActionUnread' => array(
				'title' => $db->escape_string($lang->unreadPostsStatusActionUnread),
				'description' => $db->escape_string($lang->unreadPostsStatusActionUnreadDesc),
				'optionscode' => 'onoff',
				'value' => 1
			),
			'unreadPostsStatusPostbitMark' => array(
				'title' => $db->escape_string($lang->unreadPostsStatusPostbitMark),
				'description' => $db->escape_string($lang->unreadPostsStatusPostbitMarkDesc),
				'optionscode' => 'onoff',
				'value' => 1
			),
			'unreadPostsStatusCounter' => array(
				'title' => $db->escape_string($lang->unreadPostsStatusCounter),
				'description' => $db->escape_string($lang->unreadPostsStatusCounterDesc),
				'optionscode' => 'onoff',
				'value' => 1
			),
			'unreadPostsCounterRefresh' => array(
				'title' => $db->escape_string($lang->unreadPostsCounterRefresh),
				'description' => $db->escape_string($lang->unreadPostsCounterRefreshDesc),
				'optionscode' => 'onoff',
				'value' => 1
			),
			'unreadPostsCounterRefreshInterval' => array(
				'title' => $db->escape_string($lang->unreadPostsCounterRefreshInterval),
				'description' => $db->escape_string($lang->unreadPostsCounterRefreshIntervalDesc),
				'optionscode' => 'numeric',
				'value' => 30
			),
			'unreadPostsLimit' => array(
				'title' => $db->escape_string($lang->unreadPostsLimit),
				'description' => $db->escape_string($lang->unreadPostsLimitDesc),
				'optionscode' => 'numeric',
				'value' => 500
			),
			'unreadPostsStatusMoved' => array(
				'title' => $db->escape_string($lang->unreadPostsStatusMoved),
				'description' => $db->escape_string($lang->unreadPostsStatusMovedDesc),
				'optionscode' => 'onoff',
				'value' => 0
			),
			'unreadPostsStatusCounterHide' => array(
				'title' => $db->escape_string($lang->unreadPostsStatusCounterHide),
				'description' => $db->escape_string($lang->unreadPostsStatusCounterHideDesc),
				'optionscode' => 'onoff',
				'value' => 0
			),
			'unreadPostsCounterPages' => array(
				'title' => $db->escape_string($lang->unreadPostsCounterPages),
				'description' => $db->escape_string($lang->unreadPostsCounterPagesDesc),
				'optionscode' => 'textarea',
				'value' => 'index.php'
			),
			'unreadPostsMarkAllReadLink' => array(
				'title' => $db->escape_string($lang->unreadPostsMarkAllReadLink),
				'description' => $db->escape_string($lang->unreadPostsMarkAllReadLinkDesc),
				'optionscode' => 'onoff',
				'value' => 1
			),
			'unreadPostsThreadStartDate' => array(
				'title' => $db->escape_string($lang->unreadPostsThreadStartDate),
				'description' => $db->escape_string($lang->unreadPostsThreadStartDateDesc),
				'optionscode' => 'onoff',
				'value' => 1
			),
			'unreadPostsFidMode' => array(
				'title' => $db->escape_string($lang->unreadPostsFidMode),
				'description' => $db->escape_string($lang->unreadPostsFidModeDesc),
				'optionscode' => 'onoff',
				'value' => 0
			)
        );
		
		$disporder = 1;
		
		foreach($setting_array as $name => $setting)
		{
			$setting['name'] = $name;
			$setting['gid'] = $gid;
			$setting['disporder'] = $disporder++;

			$db->insert_query('settings', $setting);
		}
       
        // Add last mark field - time when user mark all forums read
        if (!$db->field_exists("lastmark", "users")) {
            $db->add_column("users", "lastmark", "INT NOT NULL DEFAULT '0'");
        }

        $db->update_query("users", array("lastmark" => "regdate"), '', '', true);
        $db->update_query("settings", array("value" => "365"), "name = 'threadreadcut'");
        
        rebuild_settings();
    }

    public static function uninstall() {
        global $db;
        
        $result = $db->simple_select('settinggroups', 'gid', "name = 'unreadPosts'");
        $gid = (int) $db->fetch_field($result, "gid");
        
        if ($gid > 0) {
            $db->delete_query('settings', "gid = '{$gid}'");
        }
        $db->delete_query('settinggroups', "gid = '{$gid}'");
        
        rebuild_settings();
    }

}
