<?php
// funtion.php in theme folder
add_filter('gettext', 'rename_admin_menu_item');
add_filter('ngettext', 'rename_admin_menu_item');

function rename_admin_menu_item( $menu ) {
		$menu = str_ireplace( 'add product', 'Add Experience', $menu);
		$menu = str_ireplace( 'orders', 'Purchased', $menu);
		$menu = str_ireplace( 'Product', 'Experience', $menu);
		$menu = str_ireplace( 'Products', 'Experience', $menu); 
		$menu = str_ireplace( 'Add New Product', 'Add New Experience', $menu); 
		$menu = str_ireplace( 'Product Short Description', 'Experience Short Description', $menu);
		$menu = str_ireplace( 'Product Categories', 'Experience Categories', $menu);
		$menu = str_ireplace( 'All Product Categories', 'All Experience Categories', $menu);
		$menu = str_ireplace( 'Product Tags', 'Experience Tags', $menu);
		$menu = str_ireplace( 'Product Data', 'Experience Data', $menu);
		$menu = str_ireplace( 'Product Gallery', 'Experience Gallery', $menu);
		$menu = str_ireplace( 'Search Product Categories', 'Search Experience Categories', $menu);
		$menu = str_ireplace( 'Popular Product Tags', 'Popular Experience Tags', $menu);
		$menu = str_ireplace( 'Search Product Tags', 'Search Experience Tags', $menu);
		$menu = str_ireplace( 'Sales by product', 'Sales by Experience', $menu);

	
		return $menu;
}


add_action( 'admin_menu', 'rename_woocoomerce_wpse_100758', 999 );

function rename_woocoomerce_wpse_100758() 
{
    global $menu;
    global $submenu;
    // get keys or index by print_r $menu & $submenu
    // Pinpoint menu item

    $woo = recursive_array_search_php_91365( 'Products', $menu );
    // Validate
    if( !$woo )
        return;

    $menu[$woo][0] = 'Experience';


    $woo1 = recursive_array_search_php_91365( 'Products', $submenu );
    if( !$woo1 )
        return;
	$submenu[$woo1][5][0] = 'Experience'; 

	$woo2 = recursive_array_search_php_91365( 'Orders', $submenu );
    if( !$woo2 )
        return;
	$submenu[$woo2][1][0] = 'Purchased';	

	$woo3 = recursive_array_search_php_91365( 'Orders', $submenu );
    if( !$woo3 )
        return;
	$submenu[$woo3][5][0] = 'Purchased';

	$woo4 = recursive_array_search_php_91365( 'Orders', $menu );
    if( !$woo4 )
        return;
	$menu[$woo4][0] = 'Purchased';	

}

function recursive_array_search_php_91365( $needle, $haystack ) 
{
    foreach( $haystack as $key => $value ) 
    {
        $current_key = $key;
        if( 
            $needle === $value 
            OR ( 
                is_array( $value )
                && recursive_array_search_php_91365( $needle, $value ) !== false 
            )
        ) 
        {
            return $current_key;
        }
    }
    return false;
}
