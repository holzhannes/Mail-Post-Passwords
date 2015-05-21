<?php
/*
Plugin Name: Mail Post Passwords
Description: Das Passwort welches für Beiträge oder Seiten erstellt wurde kann von bestimmten Nutzern (hinterlegte E-Mail-Adressen, oder Domainendung) angefordert werden. In der E-Mail erhalten die Personen dann das Passwort. Falls das Plugin Post Password Token installiert ist enhält die E-Mail auch einen direkten Link. Achtung dieses Plugin setzt für alle Beiträge und Seiten das gleiche Passwort. Das Plugin erneuert dieses Passwort in fest einstellbaren Abständen.  
Author: holzhannes
Version: 1.0
Min WP Version: 4.1
Max WP Version: 4.1.1
Text Domain: mailpp
Domain Path: /lang
*/

/**
 * Textdomain und Verzeichnis für die Sprachdateien festlegen
 */
define('MAILPP', 'mailpp');
define('MAILPP_DIR', dirname(plugin_basename( __FILE__ )) . '/lang');

// Plugin path
//$plugin_dir_path = plugin_dir_path( __FILE__ );

/**
 * Select language file
 */
function mailpp_translation() {
    if(function_exists('load_plugin_textdomain')) {
        load_plugin_textdomain( MAILPP, false, MAILPP_DIR );
    } // END if(function_exists('load_plugin_textdomain'))
} // END function my_plugin_translation()
add_action('init', 'mailpp_translation');

// Register style sheet.
add_action( 'wp_enqueue_scripts', 'mailpp_register_styles' );


/**
 * Register mailpp style sheet
 */
function mailpp_register_styles() {
	wp_register_style( 'mailpp_style', MAILPP_DIR . '/css/mail-post-passwords.css' );
	wp_enqueue_style( 'mailpp_style' );
}

/**
 * Scheduled Action Hook
 */ 
function mailpp_change_post_passwords(  ) {
	global $wpdb;
	$new_password = wp_generate_password( 10 ); // If User is not entering any fixed password or there is an error in code all posts will still be proteteced, even the input is validated
	$options = get_option( 'mailpp_settings' );
	if ($options['mailpp_pwmode'] == '1' ) {
		$new_password = wp_generate_password( 20 ); // Only 20 Characters possible
	}
	if ($options['mailpp_pwmode'] == '2' ) {
		if (strlen($options['mailpp_fixedpw']) >= 8 && strlen($options['mailpp_pfixedpw']) <= 20) {$new_password = $options['mailpp_fixedpw'];} //check if string is long enough
	}
	$wpdb->query("UPDATE ".$wpdb->posts." SET post_password = '$new_password' WHERE post_password != ''");
}
add_action('mailpp_cron_job','mailpp_change_post_passwords');


/**
 * Mail Password Cron with custom time values
 */
function mailpp_custom_cron_recurrence( $schedules) {
	$options = get_option( 'mailpp_settings' );
		switch ($options['mailpp_cron']) {
		case 1:
			$interval = 3600;
			break;
		case 2:
			$interval = 43200;
			break;
		case 3:
			$interval = 86400;
			break;
		case 4:
			$interval = 604800;
			break;
		case 5:
			$interval = 2592000;
			break;     
		case 6:
			 $interval = $options['mailpp_cron_interval'];
			if($interval > 5184000) {
				$interval = 5184000;// maximum 2 months
			}
			if($interval <= 60) {
			$interval = 60; // minimum 60 seconds
			}
			break;
		default:
			$interval = 60;
		}
	$schedules['mailppcron'] = array(
		'display' => __( 'Benutzerdefinierte Zeit', 'mailpp' ),
		'interval' => $interval,
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'mailpp_custom_cron_recurrence' );


/**
 * Schedule Cron Job Event
 */
function mailpp_schedule_cron() {
	if ( ! wp_next_scheduled( 'mailpp_cron_job' ) ) {
		wp_schedule_event( time(), 'mailppcron', 'mailpp_cron_job' );
	}
}
add_action( 'wp', 'mailpp_schedule_cron' );

/**
 * Unschedule Cron Job Event
 */
function mailpp_unschedule_cron() {
	$event_timestamp = wp_next_scheduled('mailpp_cron_job');
	wp_unschedule_event($event_timestamp, 'mailpp_cron_job');
}

/**
 * Get Password from Post
 */
function get_post_password($id){
	$post = get_post($id);
	return $post->post_password;
}

/**
 * Check if email is allowed to receive the mail with the password
 */
function mailpp_mail_check($email) {
	$email = sanitize_email( $email );
	$options = get_option( 'mailpp_settings' );
	// Domains if activated
	if ( $options['mailpp_checkbox_domains'] == '1' ){
		$domains_array = explode("\n", str_replace("\r", "", $options['mailpp_user_domains']));
		foreach ($domains_array as &$singledomain) {
    		if ( $singledomain == mailpp_get_domain_from_email( $email ) ){
				return TRUE;
			}
		}
	}
	// Emails if activated
	if ( $options['mailpp_checkbox_emails'] == '1'){
		$emails_array = explode("\n", str_replace("\r", "", $options['mailpp_user_emails']));
		foreach ($emails_array as &$singleemail) {
    		if ( $singleemail ==  $email ){
				return TRUE;
			}
		}
	}
	return FALSE;
}


/**
 * Get domain including tld from an email address
 */
function mailpp_get_domain_from_email( $email ) {
    $domain = substr(strrchr($email, "@"), 1);
    return $domain;
}

/**
 * Create the content of mail for the user
 */
function mailpp_create_mailcontent($postid) {
        $post = get_post( $id );
		$password = $post->post_password;
		$mailcontent = '';

		/**
		 * Check if The Post Password Token Plugin is installed. 
		 * If installed insert the link in the email.
 		 */
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		
			// check for plugin using plugin name
			if ( is_plugin_active( 'post-password-plugin/post-password-token.php' ) ) {
				$ppt_direkt_link = __('Die Internetseite kann mit folgenden Link direkt angesehen werden','mailpp') .': ' . "\n";
				$ppt_direkt_link .= ppt_make_permalink($post);
				$mailcontent  .= $ppt_direkt_link . "\n" . "\n";
			}
		
   		$mailcontent .= __('Das Passwort für die Seite lautet','mailpp') .': ' . "\n";
   		$mailcontent .= $password . "\n" . "\n";
   		$mailcontent .= __('Link zur Seite','mailpp') . ': ' . "\n" . get_permalink($post->ID) . "\n";
   		$mailcontent .= __('Der Link und das Passwort sind nur begrenzt gültig!','mailpp') . '. ' . $expire;
   		return $mailcontent;
}


/**
 * Managing the mail reqested by the user
 */
function mailpp_send_mail() {
    // if the submit button is clicked
    if ( isset( $_POST['requestpostpw'] ) ) {
        // sanitize form values
        $email = sanitize_email( $_POST['useremail'] );
        $postid = sanitize_key( $_POST['postid'] );
        $post = get_post( $id );
        
		if ( mailpp_mail_check($email) ){
			if ( wp_mail($email, __('Zugangsdaten','mailpp'), mailpp_create_mailcontent( $postid ) ) ) {
			echo '<div id="message" style="background-color: #e5ffe5;"><p>' . __('Eine E-Mail wurde versendet!','mailpp') .'</p></div>';
			} else {
			echo '<div id="message" style="background-color: #ffb2b2;"><p>' . __('Fehler beim senden der E-Mail! Bitte erneut versuchen oder eine_n Administrator_in kontaktieren.','mailpp') . '</p></div>'; 
			}
		} else {
		echo '<div id="message" style="background-color: #ffb2b2;"><p>' . __('Diese E-Mail-Adresse ist nicht hinterlegt!','mailpp') . '</p></div>';
		}
	}
}

/**
 * Replace the regular Wordpress password form
 */
function mailpp_password_form() {
	mailpp_send_mail();
    global $post;
    $label = 'pwbox-'.( empty( $post->ID ) ? rand() : $post->ID );
    $out = '<form action="' . esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" method="post"><p>' . __( 'Um den Inhalt zu sehen ist ein Passwort einzugeben:' ) . '</p><label for="' . $label . '">' . '<strong>'.__( "Password" ) . '</strong>'. ' </label> <br> <input name="post_password" id="' . $label . '" type="password" placeholder="' . __('Passwort','mailpp') . '"size="20" maxlength="20" /> <br /> <br /> <input type="submit" name="Submit" value="' . esc_attr__( "Absenden" ) . '" /></form>';
    $out .=  '<form id="mailppform" style="margin-top: 150px;" action="' . esc_url( $_SERVER['REQUEST_URI'] ) . '" method="post">';
    $out .=  '<p>' . __('Passwort per E-Mail anfordern. Nur möglich wenn die E-Mail-Adresse hinterlegt ist.','mailpp'). '<br /> <br />';
    $out .=  '<input type="email" name="useremail" placeholder="' . __('E-Mail-Adresse','mailpp') . '" value="' . ( isset( $_POST['useremail'] ) ? esc_attr( $_POST['useremail'] ) : '' ) . '" size="40" />';
    $out .=  '<input style="display:none;" type="number" name="postid" value="'. $post->ID .'" size="20" />';
    $out .=  '</p>';
    $out .=  '<p><input type="submit" name="requestpostpw" value="'.__('Passwort anfordern','mailpp').'"/></p>';
    $out .=  '</form>  <br /> <br />';
    return $out;
}

add_filter( 'the_password_form', 'mailpp_password_form' );


/**
 * Edit the cookie expiration Time 
 */

// apply_filters ( 'post_password_expires', 0 );



/***************************************************************************************/
/*** Admin 																			 ***/
/***************************************************************************************/


/**
 * Install, deactivation and unistall
 */
 
/* What to do when the plugin is activated? */
register_activation_hook(__FILE__,'mailpp_install');

/* What to do when the plugin is deactivated? */
register_deactivation_hook( __FILE__, 'mailpp_deactivation' );

/* What to do when the plugin is unistalled? */
register_uninstall_hook( __FILE__, 'mailpp_deactivation' );


/**
 * Install
 */
function mailpp_install() {

}

/**
 * Deactivation
 */
function mailpp_deactivation() {
	wp_clear_scheduled_hook( 'mailpp_hourly_update' );

}

/**
 * Unistall
 */
function mailpp_uninstall() {
	delete_option('mailpp_settings');
}



add_action( 'admin_menu', 'mailpp_add_admin_menu' );
add_action( 'admin_init', 'mailpp_settings_init' );



/**
 * Add menu item
 */
function mailpp_add_admin_menu(  ) { 

	add_menu_page( 'Mail Post Passwords', 'Sende Passwörter', 'manage_options', 'mail_posts_passwords', 'mail_posts_passwords_options_page' );

}


/**
 * Init adminpage
 */

function mailpp_settings_init(  ) { 

	register_setting( 'pluginPage', 'mailpp_settings' );
	
	add_settings_section	( 'mailpp_pluginPage_section', __( 'Grundeinstellungen', 'mailpp' ), 'mailpp_settings_section_callback', 'pluginPage' );
	add_settings_section	( 'mailpp_pluginPage_section2', __( 'Nutzer_innen', 'mailpp' ), 'mailpp_settings_section_callback2', 'pluginPage' );
	
	add_settings_field		( 'mailpp_pwmode', __( 'Password', 'mailpp' ), 'mailpp_pwmode_render', 'pluginPage', 'mailpp_pluginPage_section' );
	add_settings_field		( 'mailpp_fixedpw', __( 'Passwort (manuell)', 'mailpp' ), 'mailpp_fixedpw_render', 'pluginPage', 'mailpp_pluginPage_section' );
	add_settings_field		( 'mailpp_cron', __( 'Änderungsinterval', 'mailpp' ), 'mailpp_cron_render', 'pluginPage', 'mailpp_pluginPage_section' );
	add_settings_field		( 'mailpp_cron_interval', __( 'Interval (benutzerdef.)', 'mailpp' ), 'mailpp_cron_interval_render', 'pluginPage', 'mailpp_pluginPage_section' );
	add_settings_field		( 'mailpp_checkbox_domains', __( 'Aktiviere Domains', 'mailpp' ), 'mailpp_checkbox_domains_render', 'pluginPage', 'mailpp_pluginPage_section2' );
	add_settings_field		( 'mailpp_user_domains', __( 'Domains', 'mailpp' ), 'mailpp_user_domains_render', 'pluginPage', 'mailpp_pluginPage_section2' );
	add_settings_field		( 'mailpp_checkbox_emails', __( 'Aktiviere E-Mail-Adressen', 'mailpp' ), 'mailpp_checkbox_emails_render', 'pluginPage', 'mailpp_pluginPage_section2' );
	add_settings_field		( 'mailpp_user_emails', __( 'E-Mail-Adressen', 'mailpp' ), 'mailpp_user_emails_render', 'pluginPage', 'mailpp_pluginPage_section2' );
	
}


function mailpp_pwmode_render(  ) { 
	$options = get_option( 'mailpp_settings' );
	?>
	<select name='mailpp_settings[mailpp_pwmode]'>
		<option value='1' <?php selected( $options['mailpp_pwmode'], 1 ); ?>><?php _e( 'automatisch', 'mailpp' )?></option>
		<option value='2' <?php selected( $options['mailpp_pwmode'], 2 ); ?>><?php _e( 'manuell', 'mailpp' )?></option>
	</select>

<?php

}


function mailpp_cron_render(  ) { 

	$options = get_option( 'mailpp_settings' );
	?>
	<select id="mailppcronselect" name='mailpp_settings[mailpp_cron]'  >
		<option value='1' <?php selected( $options['mailpp_cron'], 1 ); ?>> <?php _e( 'stündlich', 'mailpp' )?></option>
		<option value='2' <?php selected( $options['mailpp_cron'], 2 ); ?>> <?php _e( 'zweimal täglich', 'mailpp' )?></option>
		<option value='3' <?php selected( $options['mailpp_cron'], 3 ); ?>> <?php _e( 'täglich', 'mailpp' )?></option>
		<option value='4' <?php selected( $options['mailpp_cron'], 4 ); ?>> <?php _e( 'wöchentlich', 'mailpp' )?></option>
		<option value='5' <?php selected( $options['mailpp_cron'], 5 ); ?>> <?php _e( 'monatlich', 'mailpp' )?></option>
		<option value='6' <?php selected( $options['mailpp_cron'], 6 ); ?>> <?php _e( 'benutzerdefniert', 'mailpp' )?></option>
	</select>

<?php

}

function mailpp_cron_interval_render(  ) { 

	$options = get_option( 'mailpp_settings' );
	?>
	<input id="mailppcustomcron" type='number' min='60' max='5184000' name='mailpp_settings[mailpp_cron_interval]' value='<?php echo $options['mailpp_cron_interval']; ?>'>
	<?php

}

function mailpp_fixedpw_render(  ) { 

	$options = get_option( 'mailpp_settings' );
	?>
	<input type='text' size='20' pattern='.{8,20}' title='<?php _e( '8 bis 20 Zeichen', 'mailpp' )?>' name='mailpp_settings[mailpp_fixedpw]' value='<?php echo $options['mailpp_fixedpw']; ?>'>
	<?php

}

function mailpp_settings_section_callback2(  ) { 
	echo __( 'Nutzer_innen durch hinterlegen von Domainendungen (teil rechts vom @-Zeichen einer E-Mail-Adresse) oder durch hinterlegen der E-Mail-Adresse(n) erlauben das aktuelle Passwort anzufordern. 
	<br><strong>Achtung:</strong> Ist z.B. im Feld Domainendungen gmx.de hinterlegt können alle Personen mit einer @gmx.de E-Mail-Adresse Passwörter per E-Mail anfordern!', 'mailpp' );
}


function mailpp_checkbox_domains_render(  ) { 
	$options = get_option( 'mailpp_settings' );
	?>
	<input type='checkbox' name='mailpp_settings[mailpp_checkbox_domains]' <?php checked( $options['mailpp_checkbox_domains'], 1 ); ?> value='1'>
	<?php
}

function mailpp_checkbox_emails_render(  ) { 
	$options = get_option( 'mailpp_settings' );
	?>
	<input type='checkbox' name='mailpp_settings[mailpp_checkbox_emails]' <?php checked( $options['mailpp_checkbox_emails'], 1 ); ?> value='1'>
	<?php
}

function mailpp_user_domains_render(  ) { 
	$options = get_option( 'mailpp_settings' );
	?>
	<textarea cols='40' rows='5' name='mailpp_settings[mailpp_user_domains]'><?php echo $options['mailpp_user_domains']; ?></textarea><br>
	<?php
	echo __( 'Jede Domain in einer neuen Zeile. Beispiel: yourdomain.com', 'mailpp' );
}

function mailpp_user_emails_render(  ) { 
	$options = get_option( 'mailpp_settings' );
	?>
	<textarea cols='40' rows='5' name='mailpp_settings[mailpp_user_emails]'><?php echo $options['mailpp_user_emails']; ?></textarea><br>
	<?php
	echo __( 'Jede E-Mail-Adresse in einer neuen Zeile. Beispiel: mail@yourdomain.com', 'mailpp' );
}

function mailpp_settings_section_callback(  ) { 
	echo __( 'Intervall in welchem das Passwort automtisch erzeugt oder durch das manuelle Passwort überschrieben wird. Ein kurzes Änderungsinterval in Verbindung mit einem automatischem Passwort bietet eine höhere Sicherheit.', 'mailpp' );

}

function mail_posts_passwords_options_page(  ) { 

	?>
	<form action='options.php' method='post'>
		<h2>Mail Posts Passwords</h2>
		<?php if( isset($_GET['settings-updated']) ) { ?>
    	<div id="message" class="updated">
        <p><strong><?php _e('Settings saved.') ?></strong></p>
    	</div>
		<?php } ?>
		
		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );
		mailpp_unschedule_cron();
		mailpp_schedule_cron();
		submit_button();
		?>
		
	</form>
	<?php

}

function replace_admin_menu_icons_css() {
    ?>
    <style>
    #adminmenu .toplevel_page_mail_posts_passwords div.wp-menu-image:before {
    content: '\f112';
	}
    </style>
});
    <?php
}

add_action( 'admin_head', 'replace_admin_menu_icons_css' );


/***************************************************************************************/
/*** Not in use 																	 ***/
/***************************************************************************************/


/**
 * Not in use at the moment
 */

function mailpp_clean_domains ($domainsraw ){
	$domains_array = explode("\n", str_replace("\r", "", $domainsraw));
	foreach ($domains_array as $key => $singledomain) {
    	if (!is_valid_domain_name($singledomain)){
				$domains_array[$key] = "";
		}
	}
	
	return implode("\n", $domains_array);
}

/**
 * Not in use at the moment
 */

function is_valid_domain_name($domain_name){
    return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) //valid chars check
            && preg_match("/^.{1,253}$/", $domain_name) //overall length check
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name) ); //length of each label
}


/**
 * Not in use at the moment
 */

function mailpp_clean_emails ($emailsraw ){
	$emails_array = explode("\n", str_replace("\r", "", $emailsraw));
	foreach ($emails_array as $key => $singleemail) {
    	$emails_array[$key] = filter_var( $singleemail, FILTER_VALIDATE_EMAIL );
	}
	
	return implode("\n", $domains_array);
}