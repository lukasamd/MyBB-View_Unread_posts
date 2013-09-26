jQuery(document).ready(function()
{   
    jQuery(".thread_unread").on('click', function() {
    
        var element = jQuery(this);
    
        var tid = jQuery(this).attr("id");
        tid = tid.replace('thread', '');
        
        var src = jQuery(this).attr("src");
        src = src.replace('new', ''); 

        jQuery.ajax({
            type: "POST",
            url: "xmlhttp.php",
            data: (
            {
                tid : tid,
                action : 'unreadPosts_markThread',
            }),
            success: function() {
                element.attr("src", src).removeClass('thread_unread');
            }
        });
    }); 
});
