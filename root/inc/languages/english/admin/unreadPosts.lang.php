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

$l['unreadPostsName'] = 'View Unread Posts';
$l['unreadPostsDesc'] = 'This plugin adds a "view unread posts" function for all registered users.';
$l['unreadPostsGroupDesc'] = 'Settings for plugin "View Unread Posts"';


$l['unreadPostsExceptions'] = 'Exception forums list';
$l['unreadPostsExceptionsDesc'] = 'Forums which will not be searched.';

$l['unreadPostsStatusActionUnread'] = 'Change lastpost to first unread post topic links';
$l['unreadPostsStatusActionUnreadDesc'] = 'This option replaces all the links on the forum lead to the last post (action = lastpost) for links to first unread post in the thread.';

$l['unreadPostsStatusPostbitMark'] = 'Unread posts marker in view topic';
$l['unreadPostsStatusPostbitMarkDesc'] = 'If enabled, unread posts in threads will marked with data from the template unreadPosts_postbit (default string)';

$l['unreadPostsStatusCounter'] = 'Unread posts counter';
$l['unreadPostsStatusCounterDesc'] = 'Add a unread posts counter near the link.';

$l['unreadPostsStatusCounterHide'] = 'Hide "view unread posts" link, when there are no unread';
$l['unreadPostsStatusCounterHideDesc'] = 'This option hides url for searching unread posts, when there are not unread posts for user. Works only then "Unread posts counter" is enabled.';

$l['unreadPostsLimit'] = 'Results limit for counter';
$l['unreadPostsLimitDesc'] = 'Limit query results looking for unreads posts for the counter.<br /> The recommended value is 500 or less – more than 10000 will be automatically reduced to 500.<br /> If the search finds more results, it will display information like "500 +".';

$l['unreadPostsStatusMoved'] = 'Enable search "moved posts"';
$l['unreadPostsStatusMovedDesc'] = 'If enabled, forum can slow down (not recommended on big boards).';

$l['unreadPostsCounterPages'] = 'Subpages with active unread posts counter posts';
$l['unreadPostsCounterPagesDesc'] = 'Pages-codes (THIS_SCRIPT constant), on which the unread posts counter will be active active. If not specified, the counter will be active on all pages.';

$l['unreadPostsMarkAllReadLink'] = 'Show "Mark all threads read" link in search results';
$l['unreadPostsMarkAllReadLinkDesc'] = 'If enabled, "mark all threads read" link will be displayed above the search results.';

$l['unreadPostsMarkerStyle'] = 'Unread posts marker style';
$l['unreadPostsMarkerStyleDesc'] = 'CSS style for unread posts marker in thread view.';

$l['unreadPostsThreadStartDate'] = 'Show thread start date in search results';
$l['unreadPostsThreadStartDateDesc'] = 'Display thread start date next to thread author in search results.';

$l['unreadPostsFidMode'] = 'Forum (FID) counter / search mode';
$l['unreadPostsFidModeDesc'] = 'After activation, counter and search will display informaction only about specific forum.';

$l['unreadPostsCounterRefresh'] = 'Refresh counter in background';
$l['unreadPostsCounterRefreshDesc'] = 'Refresh unread posts counter in background using AJAX.';

$l['unreadPostsCounterRefreshInterval'] = 'Refresh interval';
$l['unreadPostsCounterRefreshIntervalDesc'] = 'Interval to refresh counter in seconds.';

