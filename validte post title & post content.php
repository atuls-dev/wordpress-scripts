 <?php 

 /** ADD Validation for post title & post content */
    function force_post_title_init()
{
    wp_enqueue_script('jquery');
}
function force_post_title()
{
    echo "<script type='text/javascript'>\n";
    echo "
  jQuery('#publish').click(function(){
        var testervar = jQuery('[id^=\"titlediv\"]')
        .find('#title');
        if (testervar.val().length < 10)
        {
            jQuery('#title').css('border', '1px solid red');
            alert('Post title is required');
            return false;
        }
        var testervar1 = jQuery('#content');
        if (testervar1.val().length < 20)
        {
            alert('Post content is required');
            return false;
        }

    });
  ";
    echo "</script>\n";
}
add_action('admin_init', 'force_post_title_init');
add_action('edit_form_advanced', 'force_post_title');
// Add this row below to get the same functionality for page creations.
add_action('edit_page_form', 'force_post_title');

