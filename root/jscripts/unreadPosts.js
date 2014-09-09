$(document).ready(function()
{   
    $(".thread_unread").on('click', function() {
    
        var element = $(this);
    
        var tid = $(this).attr("id");
        tid = tid.replace('thread', '');
        
        var src = $(this).attr("src");
        src = src.replace('new', ''); 

        $.ajax({
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
