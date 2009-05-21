<?php
/*
 * Plugin Name: MLS Core
 * Version: 0.1
 * Plugin URI: http://realtypress.org/
 * Author: Dustin Boling, Boling Research Labs
 * Author URI: http://bolingresearch.com
 * Description: Core functions for MLS.
 */

// Block direct access to this file.
if (preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) {
    die ("You are not allowed to access this file directly!");
}

global $wp_version;

if (version_compare($wp_version, "2.7", "<")) {
    $exit_msg = 'MLS requires WordPress 2.7 or newer.
             <a href="http://codex.wordpress.org/Upgrading_WordPress">
                 Please Update!</a>';
    exit($exit_msg);
}

// Avoid name collisions.
if ( !class_exists('MLS') ) :

class MLS {
    // holds the URL to this plugin
    var $plugin_url;
    // holds the PATH to this plugin
    var $plugin_path;
    // name for our options in the WordPress DB
    var $db_option = 'MLS_Options';
    // images url
    var $images_url = "http://realestateinredbluff.com/wp-content/mls-images/";
    // database table
    var $db_table = "wp_rplistings";

    // Initialize the plugin
    function __construct() {
        $this->plugin_url = trailingslashit( MUPLUGINDIR.'/'.dirname( plugin_basename(__FILE__) ));
        $this->plugin_path = MUPLUGINDIR.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));

        // add options page
        add_action('admin_menu', array(&$this, 'admin_menu'));
        add_action('admin_head', array(&$this, 'admin_head_action'));
        add_action('wp_head', array(&$this, 'wp_head_action'));
        // add shortcode handler
        add_shortcode('just-listed', array(&$this, 'print_latest_listings') );
        add_shortcode('mapsearch', array(&$this, 'map_search'));
    }


    // function to call after plugin activation
    function install() {
        // set default options
        $this->get_options();
    }

    // runs during WP Init
    function init_action() {

        wp_register_script('jquery-131', '/'.$this->plugin_url.'media/js/jquery.js', array(), '1.3.1' );
        wp_enqueue_script('jquery-datatables', '/'.$this->plugin_url.'media/js/jquery.dataTables.js', array('jquery-131'), '1.4.3' );

        // wp_register_script('jquery-tablesorter', '/'.$this->plugin_url.'js/jquery.tablesorter.min.js', array('jquery'), 'string version' );
        // wp_enqueue_script('jquery-tablesorter-pager', '/'.$this->plugin_url.'js/jquery.tablesorter.pager.js', array('jquery-tablesorter'), 'string version' );
    }

    // hook the options page
    function admin_menu() {
        add_menu_page('MLS', 'MLS', 8, basename(__FILE__), array(&$this, 'handle_agentlistings'));
        add_submenu_page(basename(__FILE__), 'My Listings', 'My Listings', 7, basename(__FILE__), array(&$this, 'handle_agentlistings'));
        add_submenu_page(basename(__FILE__), 'Office Listings', 'Office Listings', 7, 'options1', array(&$this, 'handle_officelistings'));
        add_submenu_page(basename(__FILE__), 'MLS Listings', 'Listings', 7, 'options2', array(&$this, 'handle_listings'));
        add_submenu_page(basename(__FILE__), 'Agent Credentials', 'Credentials', 7, 'options3', array(&$this, 'handle_options'));
    }

    function admin_head_action() {

        print '<script type="text/javascript" charset="utf-8">
var oTable;
var asInitVals = new Array();

window.onload = function() {
    oTable = $("#listings_table").dataTable( {
                "bStateSave": false,
        "oLanguage": {
            "sSearch": "Search all columns:"
        }
    } );

    $("tfoot input").keyup( function () {
        /* Filter on the column (the index) of this element */
        oTable.fnFilter( this.value, $("tfoot input").index(this) );
    } );



    /*
     * Support functions to provide a little bit of user friendlyness to the textboxes in
     * the footer
     */
    $("tfoot input").each( function (i) {
        asInitVals[i] = this.value;
    } );

    $("tfoot input").focus( function () {
        if ( this.className == "search_init" )
        {
            this.className = "";
            this.value = "";
        }
    } );

    $("tfoot input").blur( function (i) {
        if ( this.value == "" )
        {
            this.className = "search_init";
            this.value = asInitVals[$("tfoot input").index(this)];
        }
    } );
}
                        </script>';
    }

    function wp_head_action() {
        print '<script type="text/javascript" src="http://www.google.com/jsapi?key=ABQIAAAAKnebclDyr-G2mXX0DIziYBRdSeliLkSklP-sNMHfm3o-JT1OvhRzy2tboAJAKiIIzTqywV9P6cOQBg"></script>
<script type="text/javascript" charset="utf-8">
google.load("maps", "2.x");
window.onload = function(){
          var map = new google.maps.Map2(document.getElementById(\'map\'));
          var gsprop = new google.maps.LatLng(40.177136,-122.238244);
          map.setCenter(gsprop, 12);

// setup 10 random points
				var bounds = map.getBounds();
				var southWest = bounds.getSouthWest();
				var northEast = bounds.getNorthEast();
				var lngSpan = northEast.lng() - southWest.lng();
				var latSpan = northEast.lat() - southWest.lat();
				var markers = [];
				for (var i = 0; i < 75; i++) {
				    var point = new GLatLng(southWest.lat() + latSpan * Math.random(),
				        southWest.lng() + lngSpan * Math.random());
					marker = new GMarker(point);
					map.addOverlay(marker);
					markers[i] = marker;
				}
      };
</script>';
    }

    // handle the options page
    function handle_options() {
        $options = $this->get_options();

        // if server credentials updated
        if ( isset($_POST['submitted']) ) {
            // check security
            check_admin_referer('mls-options');

            $options = array();

            $options['office_id'] = htmlspecialchars($_POST['office_id']);
            $options['agent_id'] = htmlspecialchars($_POST['agent_id']);
            $options['agent_license'] = htmlspecialchars($_POST['agent_license']);

            update_option($this->db_option, $options);

            echo '<div class="updated fade"><p>Your settings have been saved.</p></div>';
        }

        $office_id = stripslashes($options['office_id']);
        $agent_id = stripslashes($options['agent_id']);
        $agent_license = stripslashes($options['agent_license']);

        // url for form submit, equals our current page
        $action_url = $_SERVER['REQUEST_URI'];

        include('mls-includes/mls-options.php');
    }

    // handle the agent's listings
    function handle_agentlistings() {

        // if creating post from an mls listing
        if ( isset($_POST['createpost']) ) {
            $fields = $_POST['listing_ids'];
            if (is_array($fields)) {
                // check security
                check_admin_referer('listings-nonce');
                for ($i=0;$i<count($fields);$i++) {
                    $post_id = $this->create_post($fields[$i]);
                    if ($post_id != NULL || $post_id != '') {
                        echo '<div class="updated fade"><p>The listing #'.$_POST['listing_id'].' has been imported. Review the listing information before publishing <a href="post.php?action=edit&post='.$post_id.'">here</a>.</p></div>';
                    } else {
                        echo '<div class="error fade"><p>There was a problem posting listing #'.$_POST['listing_id'].'. Please try again.</p></div>';
                    }
                }
            }
        }

        // url for form submit, equals our current page
        $action_url = $_SERVER['REQUEST_URI'];
        include('mls-includes/mls-agentlistings.php');

    }

    // handle the office's listings
    function handle_officelistings() {

        // if creating post from an mls listing
        if ( isset($_POST['createpost']) ) {
            $fields = $_POST['listing_ids'];
            if (is_array($fields)) {
                // check security
                check_admin_referer('listings-nonce');
                for ($i=0;$i<count($fields);$i++) {
                    $post_id = $this->create_post($fields[$i]);
                    if ($post_id != NULL || $post_id != '') {
                        echo '<div class="updated fade"><p>The listing #'.$_POST['listing_id'].' has been imported. Review the listing information before publishing <a href="post.php?action=edit&post='.$post_id.'">here</a>.</p></div>';
                    } else {
                        echo '<div class="error fade"><p>There was a problem posting listing #'.$_POST['listing_id'].'. Please try again.</p></div>';
                    }
                }
            }
        }

        // url for form submit, equals our current page
        $action_url = $_SERVER['REQUEST_URI'];
        include('mls-includes/mls-officelistings.php');

    }



    // handle the listings
    function handle_listings() {

        // if creating post from an mls listing
        if ( isset($_POST['createpost']) ) {
            $fields = $_POST['listing_ids'];
            if (is_array($fields)) {
                // check security
                check_admin_referer('listings-nonce');
                for ($i=0;$i<count($fields);$i++) {
                    $post_id = $this->create_post($fields[$i]);
                    if ($post_id != NULL || $post_id != '') {
                        echo '<div class="updated fade"><p>The listing #'.$_POST['listing_id'].' has been imported. Review the listing information before publishing <a href="post.php?action=edit&post='.$post_id.'">here</a>.</p></div>';
                    } else {
                        echo '<div class="error fade"><p>There was a problem posting listing #'.$_POST['listing_id'].'. Please try again.</p></div>';
                    }
                }
            }
        }

        // url for form submit, equals our current page
        $action_url = $_SERVER['REQUEST_URI'];
        include('mls-includes/mls-listings.php');
    }

    // prints column headers
    function print_listing_column_headers() {
        echo '<th NOWRAP>Images</th><th NOWRAP>MLS #</th><th NOWRAP>Street Address</th><th NOWRAP>City</th><th NOWRAP>Zip</th><th NOWRAP>Beds</th><th NOWRAP>Baths</th><th NOWRAP>Listing Price</th><th NOWRAP>Date Listed</th><th NOWRAP>Post It?</th>';
    }

    // prints rows of listings
    function print_listing_rows() {
        global $wpdb;
        $listings = $wpdb->get_results("SELECT * FROM $this->db_table ORDER BY list_date DESC");
        foreach ($listings as $listing) {
            echo '<tr valign="top">';
            echo '    <td NOWRAP>'. ($listing->images === NULL ? 'N' : 'Y') . '</td><td NOWRAP>'.$listing->listing_id.'</td><td NOWRAP>'.$listing->street_num.' '.$listing->street_name.'</td><td NOWRAP>'.$listing->city.'</td><td NOWRAP>'.$listing->zipcode.'</td><td align="center" NOWRAP>'.$listing->beds.'</td><td align="center" NOWRAP>'. $listing->baths_full.'</td><td align="right" NOWRAP>$'. number_format($listing->price).'</td><td NOWRAP>'.date("M jS, Y", strtotime($listing->list_date)).'</td><td align="center" NOWRAP><input type="checkbox" name="listing_ids[]" value="'.$listing->listing_id.'" /></td>';
            echo '</tr>';
        }
    }

    // prints rows of listings
    function print_agent_listing_rows() {
        $options = $this->get_options();
        global $wpdb;
        $listings = $wpdb->get_results("SELECT * FROM $this->db_table WHERE agent_id={$options['agent_id']} ORDER BY list_date DESC");
        foreach ($listings as $listing) {
            echo '<tr valign="top">';
            echo '    <td NOWRAP>'. ($listing->images === NULL ? 'N' : 'Y') . '</td><td NOWRAP>'.$listing->listing_id.'</td><td NOWRAP>'.$listing->street_num.' '.$listing->street_name.'</td><td NOWRAP>'.$listing->city.'</td><td NOWRAP>'.$listing->zipcode.'</td><td align="center" NOWRAP>'.$listing->beds.'</td><td align="center" NOWRAP>'. $listing->baths_full.'</td><td align="right" NOWRAP>$'. number_format($listing->price).'</td><td NOWRAP>'.date("M jS, Y", strtotime($listing->list_date)).'</td><td align="center" NOWRAP><input type="checkbox" name="listing_ids[]" value="'.$listing->listing_id.'" /></td>';
            echo '</tr>';
        }
    }

    // prints rows of listings
    function print_office_listing_rows() {
        $options = $this->get_options();
        global $wpdb;
        $listings = $wpdb->get_results("SELECT * FROM $this->db_table WHERE office_id={$options['office_id']} ORDER BY list_date DESC");
        foreach ($listings as $listing) {
            echo '<tr valign="top">';
            echo '    <td NOWRAP>'. ($listing->images === NULL ? 'N' : 'Y') . '</td><td NOWRAP>'.$listing->listing_id.'</td><td NOWRAP>'.$listing->street_num.' '.$listing->street_name.'</td><td NOWRAP>'.$listing->city.'</td><td NOWRAP>'.$listing->zipcode.'</td><td align="center" NOWRAP>'.$listing->beds.'</td><td align="center" NOWRAP>'. $listing->baths_full.'</td><td align="right" NOWRAP>$'. number_format($listing->price).'</td><td NOWRAP>'.date("M jS, Y", strtotime($listing->list_date)).'</td><td align="center" NOWRAP><input type="checkbox" name="listing_ids[]" value="'.$listing->listing_id.'" /></td>';
            echo '</tr>';
        }
    }

    function print_featured_listings() {

        $options = $this->get_options();
        global $wpdb;
        $image_dir = get_bloginfo("template_url")."/scripts/timthumb.php?w=150&h=100&zc=1&src=/wp-content/mls-images/";
        $listings = $wpdb->get_results("SELECT * FROM $this->db_table WHERE agent_id={$options['agent_id']} ORDER BY list_date DESC");
        if ( count( $listings ) > 0) {
            print '<div class="section">';
            print '<h3><em>My Listings</em></h3>';
            print '<div style="clear:both;"></div></div>';
            foreach ( $listings as $listing ) {
                print '<div class="section">';
                print '<h3><a href="sarah-chamberlain-listings/?listing_id='.$listing->listing_id.'" rel="bookmark">'. $listing->street_num.' '.$listing->street_name.', '.$listing->city.' $'.number_format($listing->price).'</a></h3>';
                $images = explode("\t", $listing->images);
                print '<a href="sarah-chamberlain-listings/?listing_id='.$listing->listing_id.'" rel="bookmark"><img src="'.$image_dir . $images[0].'" alt="'. $listing->street_num.' '.$listing->street_name.'" /></a>';
                print '<p>'.$listing->public_remarks.'</p>';
                print '<div style="clear:both;"></div></div>';
            }
        }
    }

    function print_office_listings() {
        print '<div class="section">';
        print '<h3><em>Office Listings</em></h3>';
        print '<div style="clear:both;"></div></div>';

        $options = $this->get_options();
        global $wpdb;
        $image_dir = get_bloginfo("template_url")."/scripts/timthumb.php?w=150&h=100&zc=1&src=/wp-content/mls-images/";
        $listings = $wpdb->get_results("SELECT * 
                                        FROM $this->db_table
                                        WHERE office_id={$options['office_id']} 
                                        AND agent_id!={$options['agent_id']} 
                                        ORDER BY list_date DESC");
        foreach ($listings as $listing) {
            print '<div class="section">';
            print '<h3><a href="sarah-chamberlain-listings/?listing_id='.$listing->listing_id
                                                                        .'" rel="bookmark">'. $listing->street_num
                                                                        .' '.$listing->street_name.', '.$listing->city
                                                                        .' $'.number_format($listing->price)
                                                                        .'</a></h3>';
            // if has images
            if ($listing->images != "") {
                $images = explode("\t", $listing->images);
                print '<a href="sarah-chamberlain-listings/?listing_id='.$listing->listing_id.'" rel="bookmark">'
                  .'<img src="'.$image_dir . $images[0].'" alt="'. $listing->street_num.' '.$listing->street_name.'" />'
                  .'</a>';
            }
            print '<p>'.$listing->public_remarks.'</p>';
            print '<div style="clear:both;"></div></div>';
        }
    }

    function print_latest_listings() {
        print '<div class="section">';
        print '<h3><em>Just Listed</em></h3>';
        print '<div style="clear:both;"></div></div>';

        global $wpdb;
        $image_dir = get_bloginfo("template_url")."/scripts/timthumb.php?w=150&h=100&zc=1&src=/wp-content/mls-images/";
        $listings = $wpdb->get_results("SELECT * FROM $this->db_table ORDER BY list_date DESC LIMIT 10");
        foreach ($listings as $listing) {
            print '<div class="section">';
            print '<h3><a href="sarah-chamberlain-listings/?listing_id='.$listing->listing_id.'" rel="bookmark">'
                                                                        .$listing->street_num
                                                                        .' '.$listing->street_name
                                                                        .', '.$listing->city
                                                                        .' '.$listing->state
                                                                        .' $'.number_format($listing->price)
                                                                        .'</a></h3>';
            
            if ( $listing->images != "" ) {
                $images = explode("\t", $listing->images);
                print '<a href="sarah-chamberlain-listings/?listing_id='.$listing->listing_id.'" rel="bookmark"><img src="'
                                                                        .$image_dir . $images[0].'" alt="'
                                                                        .$listing->street_num.' '
                                                                        .$listing->street_name.'" /></a>';
            }
            print '<p>'.$listing->public_remarks.'</p>';
            print '<div style="clear:both;"></div></div>';
        }
    }

    function map_search() {
        print '<div style="text-align:center; padding-bottom:10px;"><div id="map_search_form" style"margin: 0 auto; width:400px;"><form name="mapsearch">
<label for="beds">Beds: </label>
<select name="beds">
<option>1</option>
<option>2</option>
<option>3</option>
<option>4</option>
<option>5</option>
</select>
<label for="baths">Baths: </label>
<select name="baths">
<option>1</option>
<option>2</option>
<option>3</option>
<option>4</option>
<option>5</option>
</select>
<label for="price_min">Min $</label>
<select name="price_min">
<option>0</option>
<option>100000</option>
<option>200000</option>
<option>300000</option>
<option>400000</option>
</select>
<label for="price_max">Max $</label>
<select name="price_max">
<option>500000</option>
<option>400000</option>
<option>300000</option>
<option>200000</option>
<option>100000</option>
</select>
<input type="submit" value="Find Property" name="search" />
</form></div></div>';
        print '<div id="map" style="width:100%; height:500px; border: solid 1px #999"></div>';
    }

    // handle plugin options
    function get_options() {
        // default values
        $options = array
        (
            'agent_id' => '',
            'agent_license' => '',
            'office_id' => ''
        );
        // get saved options
        $saved = get_option($this->db_option);

        // assign them
        if (!empty($saved)) {
            foreach ($saved as $key => $option)
            $options[$key] = $option;
        }

        // update the options if necessary
        if ($saved != $options)
        update_option($this->db_option, $options);

        // return the options
        return $options;
    }

    // returns an array of listing images.
    function get_listing_images($listing_id = 0) {
        $array_of_images = array();
        if ( 0 != $listing_id) {
            global $wpdb;
            $filename_string = $wpdb->get_var("SELECT images FROM $this->db_table WHERE listing_id=$listing_id");
            if ( $filename_string != "" && $filename_string != NULL )
                $array_of_images = explode( "\t", $filename_string );
        }
        return $array_of_images;
    }

    // create a new post, returns NULL if unable
    function create_post($listing_id = 0) {
        $result = NULL;
        if ($listing_id != 0) {
            global $wpdb;
            $listing = $wpdb->get_row("SELECT * FROM $this->db_table WHERE listing_id = $listing_id");
            if ($listing != NULL) {
                // retrieve an array of listing images
                $images = $this->get_listing_images($listing->listing_id);
                // create post object
                $listingpost = array();
                // fill it with data
                $listingpost['post_title'] =  'Home for Sale: '.$listing->street_num.' '.$listing->street_name.', '.$listing->city.' $'.number_format($listing->price);
                $listingpost['post_content'] = "";
                //                if ( count($images) > 0 ) {
                //                    $listingpost['post_content'] = '<img src="'. get_bloginfo("template_url") .'/scripts/timthumb.php?src=/wp-content/mls-images/' . $images[0].'&w=600&zc=1" border="1" /><p>'
                //                    . $listingpost['post_content'] . '</p>';
                //                }
                //$listingpost['post_status'] = 'published';
                // array( category id's )
                $listingpost['post_category'] = array(12);

                // insert the post and return its ID
                $post_id = wp_insert_post($listingpost);
                // update metadata
                //add_post_meta($post_id, "permalink", $permalink);
                // We'll put it into an array to make it easier to loop though.
                $mydata['_wp_post_template'] = "listing.php";
                $mydata['_listing_price'] = "$" . number_format($listing->price);
                $mydata['_mls'] = $listing->listing_id;
                $mydata['_address'] = $listing->street_num . " " . $listing->street_name;
                $mydata['_city'] = $listing->city;
                $mydata['_state'] = $listing->state;
                $mydata['_zip_code'] = $listing->zipcode;

                $mydata['_square_feet'] = number_format($listing->sqft);
                $mydata['_bedrooms'] = $listing->beds;
                $mydata['_bathrooms'] = $listing->baths_full + $listing->baths_partial;
                $mydata['_additional_features'] = $listing->public_remarks;

                // text for the featured content gallery
                $mydata['featuredtext'] = $listing->public_remarks;

                $img_count = count($images);
                if ( $img_count > 0 ) {
                    $mydata['articleimg'] = get_bloginfo('wpurl') . "/wp-content/mls-images/" . $images[0];
                    $mydata['thumbnail'] = $images[0];
                    for($i = 0, $count = count($images); $i < $count; ++$i) {
                        $mydata["_photo_".($i+1)."_large"] = $images[$i];
                        $mydata["_photo_".($i+1)."_thumbnail"] = $images[$i];
                    }
                }
                // Add values of $mydata as custom fields
                foreach ($mydata as $key => $value) { //Let's cycle through the $mydata array!
                    $value = implode(',', (array)$value); //if $value is an array, make it a CSV (unlikely)
                    if(get_post_meta($post_id, $key, FALSE)) { //if the custom field already has a value
                        update_post_meta($post_id, $key, $value);
                    } else { //if the custom field doesn't have a value
                        add_post_meta($post_id, $key, $value);
                    }
                    if(!$value) delete_post_meta($post_id, $key); //delete if blank
                }
            }
        }
        return $post_id;
    }
}
else :
exit ("Class MLS already declared!");
endif;

// create an instance of MLS
$MLS = new MLS();

if (isset($MLS)) {
    // register the activation function by passing the reference to our instance
    register_activation_hook( __FILE__, array(&$MLS, 'install') );
}
?>