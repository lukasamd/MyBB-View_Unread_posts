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
    $(".thread_unread").on('click', function() {
    
        var element = jQuery(this);
    
        var tid = $(this).attr("id");
        tid = tid.replace('thread', ''); 
        var classes = $(this).attr("class").replace("new", "");

        $.ajax({
            type: "POST",
            url: "xmlhttp.php",
            data: (
            {
                tid : tid,
                action : 'unreadPosts_markThread',
            }),
            success: function() {
                element.removeClass().toggleClass(classes);
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

    updateCounter: function() {
        if (!unreadPosts.enable) {
            return;
        }

        $.get( "xmlhttp.php?action=unreadPosts_getUnreads&fid" + unreadPosts.fid, function( data ) {
            $("#unreadCounter").replaceWith(data);
        });

        if (unreadPosts.timeout) clearTimeout(unreadPosts.timeout);
        unreadPosts.timeout = setTimeout('unreadPosts.updateCounter()', unreadPosts.interval * 1000);
    },
};
