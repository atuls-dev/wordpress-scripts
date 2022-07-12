<?php

	if (!function_exists('url_get_contents')) 
	{
		function url_get_contents ($Url) {
		    if (!function_exists('curl_init')){ 
		        die('CURL is not installed!');
		    }
		    $ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $Url);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    $output = curl_exec($ch);
		    curl_close($ch);
		    return $output;
		}
	} 

	// Add a new interval of 180 seconds
	add_filter( 'cron_schedules', 'isa_add_every_three_minutes' );
	function isa_add_every_three_minutes( $schedules ) {
	    $schedules['every_five_minutes'] = array(
	            'interval'  => 300,
	            'display'   => __( 'Every 5 Minutes', 'wptextdomain' )
	    );
	    $schedules['every_three_minutes'] = array(
	            'interval'  => 180,
	            'display'   => __( 'Every 3 Minutes', 'wptextdomain' )
	    );
	    $schedules['every_two_minutes'] = array(
	            'interval'  => 120,
	            'display'   => __( 'Every 2 Minutes', 'wptextdomain' )
	    );
	    return $schedules;
	}

	add_action( 'init', 'init_function' );
	function init_function() {
		wp_clear_scheduled_hook('execute_import_xml_data');
	    if ( ! wp_next_scheduled( 'execute_import_xml_data' ) ) {
	        //wp_schedule_event( time(), 'every_two_minutes', 'execute_import_xml_data' );
	        //wp_schedule_event( time(), 'every_minute', 'execute_import_xml_data' );
	    }
	}

	add_action( 'execute_import_xml_data', 'perform_scheduled_action' );
	//add_action( 'init', 'perform_scheduled_action' );
	function perform_scheduled_action() {

		if ( ! is_admin() ) {
		    require_once( ABSPATH . 'wp-admin/includes/post.php' );
		}

		global $wpdb;

		$xmlFile = __DIR__ . '/' . "rightboat_data.xml";

		if(!file_exists($xmlFile)){
		    return;
	    }

		$lastrow = (int) get_option( 'last_imported_row_index', 0 );

		// read local xml file data.
		$xml = new XMLReader();
		$xml->open( $xmlFile );

		while( $xml->read() && $xml->name != 'advert' ){;}

		$index = 0;
		$reset = true;
		$upload_dir = wp_upload_dir();

		while( $xml->name == 'advert' ) 
		{

			// Ignore already imported until reset.
			if( $index < $lastrow  ) {
				$xml->next( 'advert' );
				$index++;
				continue;
			}

			// Import only 50 items at a time
			if( $index >= $lastrow + 50 ) {
				$reset = false;
				break;
			}

			$element = new SimpleXMLElement( $xml->readOuterXML(), LIBXML_NOCDATA );


			/***** Insert or Update Boat Start *****/
			$boatID = 0;
			$boat = get_page_by_title( trim( $element->advert_features->other->item->attributes()->label->__toString() ), OBJECT, 'boats-for-sale' );
			if( $boat == null ){
				$boatID = wp_insert_post( array(
					'post_title'    => trim( $element->advert_features->other->item->attributes()->label->__toString() ),
					'post_content'  => htmlspecialchars( trim( $element->advert_features->marketing_descs->marketing_desc->__toString() ) ),
					'post_type'     => 'boats-for-sale',
					'post_status'   => 'publish',
					'post_author'   => 1,
				) );
			}else{
				wp_update_post( array(
					'ID'            => $boat->ID,
					'post_title'    => trim( $element->advert_features->other->item->attributes()->label->__toString() ),
					'post_content'  => htmlspecialchars( trim( $element->advert_features->marketing_descs->marketing_desc->__toString() ) ),
					'post_type'     => 'boats-for-sale',
					'post_status'   => 'publish',
					'post_author'   => 1,
				) );
				$boatID = $boat->ID;
			}
			/***** Insert or Update Boat End *****/

			/***** Boat Category Start *****/
			$boat_category = $element->advert_features->boat_category->__toString();
			if( !empty($boat_category) ) {

				$term = term_exists( $boat_category, 'boat_category' );
				if( $term == null ){
					$term = wp_insert_term( $boat_category, 'boat_category' );
				}
				//$term = get_term($term['term_id']);
				wp_set_post_terms( $boatID, $term['term_id'], 'boat_category' );
			}
			/***** Boat Category End *****/

			/***** Manufaturer Start *****/
			$boat_manufacturer = $element->advert_features->manufacturer->__toString();

			$manufaturer = term_exists( $boat_manufacturer, 'manufacturer' );
			if( $manufaturer == null ){
				$manufaturer = wp_insert_term( $boat_manufacturer, 'manufacturer' );
			}

			//$manufaturer = get_term($manufaturer['term_id']);
			wp_set_post_terms( $boatID, $manufaturer['term_id'], 'manufacturer' );
			/***** Manufacturer End *****/

			/***** Primary Image as Attachment Start *****/
			$primaryImage = $element->advert_media->xpath( 'media[@primary="true"]' );
			if( $primaryImage[0] ){
				$primaryImage = $primaryImage[0]->__toString();
				//die('ccc');
				$image_data = url_get_contents( $primaryImage );
				$filename = basename( $primaryImage );
				$file = wp_mkdir_p( $upload_dir['path'] ) ? $upload_dir['path'] : $upload_dir['basedir'];
				$file .= '/' . $filename;
					
				if( !post_exists( sanitize_file_name( $filename ) ) ){
					
					file_put_contents( $file, $image_data );
					$wp_filetype = wp_check_filetype( $filename, null );

					$attachment = array(
						'post_mime_type' => $wp_filetype['type'],
						'post_title'     => sanitize_file_name( $filename ),
						'post_content'   => '',
						'post_status'    => 'inherit'
					);

					$imgid = wp_insert_attachment( $attachment, $file );

					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					$attach_data = wp_generate_attachment_metadata( $imgid, $file );
					wp_update_attachment_metadata( $imgid, $attach_data );

					//attach to post.
					set_post_thumbnail( $boatID, $imgid );
				}
			}
			unset($primaryImage);
			/***** Primary Image as Attachment End *****/

			/***** Other Images to ACF Start *****/
			$tmpotherImages = $element->advert_media->xpath( 'media[@primary="false"]' );
			$otherImages = array();
			foreach( $tmpotherImages as $oi ) {
				if ( $oi ) {
					$otherImages[] = $oi->__toString();
				} else {
					continue;
				}
			}
			update_post_meta( $boatID, 'images', json_encode( $otherImages ) );
			/***** Other Images to ACF End *****/

			$metas = array();

			$metas[] = '("' . $boatID . '", "category", "' . $boat_category . '")';
			$metas[] = '("' . $boatID . '", "reference_id", "' . trim( $element->attributes()->ref->__toString() ) . '")';
			$metas[] = '("' . $boatID . '", "poa", "' . $element->advert_features->asking_price->attributes()->poa->__toString() . '")';
			$metas[] = '("' . $boatID . '", "currency", "' . $element->advert_features->asking_price->attributes()->currency->__toString() . '")';
			$metas[] = '("' . $boatID . '", "vat_included", "' . (boolean) $element->advert_features->asking_price->attributes()->vat_included->__toString() . '")';
			$metas[] = '("' . $boatID . '", "country", "' . $element->advert_features->vessel_lying->attributes()->country->__toString() . '")';
			$metas[] = '("' . $boatID . '", "city", "' . $element->advert_features->vessel_lying->__toString() . '")';
			$metas[] = '("' . $boatID . '", "external_link", "' . $element->advert_features->other->item->__toString() . '")';
			$metas[] = '("' . $boatID . '", "external_label", "' . $element->advert_features->other->item->attributes()->label->__toString() . '")';
			$metas[] = '("' . $boatID . '", "type", "' . $element->advert_features->boat_type->__toString() . '")';
			$metas[] = '("' . $boatID . '", "model", "' . addslashes( $element->advert_features->model->__toString() ) . '")';


			$status = $element->attributes()->status->__toString();
			$metas[] = '("' . $boatID . '", "status", "' . $status . '")';

			$price = $element->advert_features->asking_price->__toString();
			$metas[] = '("' . $boatID . '", "price", "' . $price . '")';

			if(isset($element->boat_features->xpath('item[@name="owners_comment"]')[0])){
				$metas[] = '("' . $boatID . '", "owners_comment", "' . trim( str_replace( '"', '\"', $element->boat_features->xpath('item[@name="owners_comment"]')[0]->__toString() ) ) . '")';
			}

			$dimensions = array();
			foreach( $element->boat_features->dimensions->item as $dimension ){
				$dimensions[$dimension->attributes()->name->__toString()] = trim( $dimension->__toString() . ' ' . $dimension->attributes()->unit->__toString() );
			}
			$metas[] = '("' . $boatID . '", "dimensions", "' . addslashes( json_encode($dimensions) ) . '")';

			$builds = array();
			foreach( $element->boat_features->build->item as $build ){
				$builds[$build->attributes()->name->__toString()] = trim( $build->__toString() );
			}
			$metas[] = '("' . $boatID . '", "build", "' . addslashes( json_encode($builds) ) . '")';

			$galleys = array();
			foreach( $element->boat_features->galley->item as $galley ){
				$galleys[$galley->attributes()->name->__toString()] = trim( $galley->__toString() );
			}
			$metas[] = '("' . $boatID . '", "galley", "' . addslashes( json_encode($galleys) ) . '")';

			$engines = array();
			foreach( $element->boat_features->engine->item as $engine ){
				$engines[$engine->attributes()->name->__toString()] = trim( $engine->__toString() );
			}
			$metas[] = '("' . $boatID . '", "engine", "' . addslashes( json_encode($engines) ) . '")';

			$navigations = array();
			foreach( $element->boat_features->navigation->item as $navigation ){
				$navigations[$navigation->attributes()->name->__toString()] = trim( $navigation->__toString() );
			}
			$metas[] = '("' . $boatID . '", "navigation", "' . addslashes( json_encode($navigations) ) . '")';

			$accommodations = array();
			foreach( $element->boat_features->accommodation->item as $accommodation ){
				$accommodations[$accommodation->attributes()->name->__toString()] = trim( $accommodation->__toString() );
			}
			$metas[] = '("' . $boatID . '", "accomodation", "' . addslashes( json_encode($accommodations) ) . '")';

			$safetyequipments = array();
			foreach( $element->boat_features->safety_equipment->item as $safetyequipment ){
				$safetyequipments[$safetyequipment->attributes()->name->__toString()] = trim( $safetyequipment->__toString() );
			}
			$metas[] = '("' . $boatID . '", "safety_equipment", "' . addslashes( json_encode($safetyequipments) ) . '")';

			$rigsails = array();
			foreach( $element->boat_features->rig_sails->item as $rigsail ){
				$rigsails[$rigsail->attributes()->name->__toString()] = trim( $rigsail->__toString() );
			}
			$metas[] = '("' . $boatID . '", "rig_sails", "' . addslashes( json_encode($rigsails) ) . '")';

			$electronics = array();
			foreach( $element->boat_features->electronics->item as $electronic ){
				$electronics[$electronic->attributes()->name->__toString()] = trim( $electronic->__toString() );
			}
			$metas[] = '("' . $boatID . '", "electronics", "' . addslashes( json_encode($electronics) ) . '")';

			$generals = array();
			foreach( $element->boat_features->general->item as $general ){
				$generals[$general->attributes()->name->__toString()] = trim( $general->__toString() );
			}
			$metas[] = '("' . $boatID . '", "general", "' . addslashes( json_encode($generals) ) . '")';

			$equipments = array();
			foreach( $element->boat_features->equipment->item as $equipment ){
				$equipments[$equipment->attributes()->name->__toString()] = trim( $equipment->__toString() );
			}
			$metas[] = '("' . $boatID . '", "equipment", "' . addslashes( json_encode($equipments) ) . '")';

			$sql = 'INSERT INTO '.$wpdb->postmeta.' (post_id, meta_key, meta_value) VALUES ' . implode(",", $metas);
			$wpdb->query($sql);

			/***** Update Price History *****/
			$price_history = get_field('price_history', $boatID);
			$price_history = explode( ",", $price_history );
			$price_history[] = $price;

			if(count( $price_history ) > 90){
				unset( $price_history[0] );
			}
			$price_history = array_filter( $price_history );
			$price_history = implode( ",", $price_history );
			update_field( 'price_history', $price_history, $boatID );

			/***** Update Status History *****/
			$status_history = get_field( 'status_history', $boatID );
			$status_history = explode( ",", $status_history );
			$status_history[] = $status;

			if( count( $status_history ) > 90 ){
				unset( $status_history[0] );
			}
			$status_history = array_filter( $status_history );
			$status_history = implode( ",", $status_history );
			update_field( 'status_history', $status_history, $boatID );

			unset( $element );
			$xml->next( 'advert' );
			$index++;
			update_option('last_imported_row_index', $index);

			echo $boatID . "<br/>";

		}

		if( $reset ) {
			update_option('last_imported_row_index', 0);
			unlink($xmlFile);
		} else {
			update_option('last_imported_row_index', $index);
		}

	}

	

?>