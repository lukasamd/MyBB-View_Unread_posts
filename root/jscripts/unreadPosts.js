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

$(document).ready(function()
{
    $(document).on('click', '.thread_unread', function() {
        var element = jQuery(this);
        var tid = element.attr("id");

        if (!tid) {
            return;
        }

        tid = tid.replace('thread', '');

        $.ajax({
            type: "POST",
            url: "xmlhttp.php",
            data: {
                tid: tid,
                action: 'unreadPosts_markThread',
                my_post_key: unreadPosts.postKey
            },
            success: function() {
                element.removeClass('thread_unread new');
            }
        });
    });
});


var unreadPosts = {

    timeout:    false,
    interval:   10,
    enable:     false,
    fid:        0,
    hide:       false,
    postKey:    '',

    updateCounter: function() {
        if (!unreadPosts.enable) {
            return;
        }

        $.get("xmlhttp.php?action=unreadPosts_getUnreads&fid=" + unreadPosts.fid, function(data) {
            $("#unreadCounter").replaceWith(data);
        });

        if (unreadPosts.timeout) {
            clearTimeout(unreadPosts.timeout);
        }
        unreadPosts.timeout = setTimeout(function() { unreadPosts.updateCounter(); }, unreadPosts.interval * 1000);
    }
};
