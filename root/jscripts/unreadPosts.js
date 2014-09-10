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
