<?php
/*
Plugin Name: Batch Validator
Plugin URI: http://wordpress.designpraxis.at
Description: Checks the markup of your entire WordPress site. Go to Dashboard &raquo; <a href="index.php?page=batch-validator/batch-validator.php">Batch Validator</a>
Version: 1.2
Author: Roland Rust
Author URI: http://wordpress.designpraxis.at


Changelog:

Changes in 1.2

- /wp-includes/class-snoopy.php is included 
  and snoopy is used for dprx_validate() insetead of fopen();



Changes in 1.1

- bug fixed for script loading with add_action('deactivate_batch-validator/batch-validator.php', 'dprx_bval_deactivate');

*/
add_action('init', 'dprx_bval_init_locale');
add_action('init', 'dprx_bval_ajax_validate',99);

function dprx_bval_init_locale() {
	$locale = get_locale();
	$mofile = dirname(__FILE__) . "/locale/".$locale.".mo";
	load_textdomain('dprx_bval', $mofile);
}


/* options are deleted in case of plugin deactivation */
// this somehow trigger a fatal error
// add_action('deactivate_batch-validator/batch-validator.php', 'dprx_bval_deactivate');
// function dprx_bval_deactivate() {
// 	delete_option("dprx_bval_last_item");  
// 	delete_option("dprx_bval_last_check");  
// }

/* Admin page display function is called */
add_action('admin_menu', 'dprx_bval_add_admin_pages');
function dprx_bval_add_admin_pages() {
	add_submenu_page('index.php','Batch Validator', 'Batch Validator', 10, __FILE__, 'dprx_bval_options_page');
}

function dprx_bval_ajax_validate() {
	if (!empty($_REQUEST['dprx_bval_ajax'])) {
		$last = get_option("dprx_bval_last_item");
		if (empty($last)) { $last = "0"; }
		$sql = "SELECT ID, guid, post_title
			FROM " . $GLOBALS['wpdb']->posts . "
			WHERE (post_status = 'publish' OR post_status='static')
			AND ID > ".$last."
			ORDER BY ID ASC
			LIMIT 0,1";
		$posts = $GLOBALS['wpdb']->get_results($sql);
		 if(count($posts) > 0) {
			foreach($posts as $post) {
				update_option("dprx_bval_last_item",$post->ID);
				$checked = dprx_bval_get_meta_valid($post->ID);
				if ($checked) {
					echo $post->post_title." last check:".date(get_option('date_format'),$checked)." ".date("H:i:s")."<br />";
				} else {
					if(dprx_validate("http://validator.w3.org/check?uri=".urlencode($post->guid)."&output=soap12")) {
						echo "<a style=\"color:green\" href=\"http://validator.w3.org/check?uri=".$post->guid."\">".$post->post_title."</a> ".__('check','dprx_bval').": ".date(get_option('date_format'))." ".date("H:i:s")."<br />";
						dprx_bval_update_result($post->ID,time());
					} else {
						echo "<a style=\"color:red\" href=\"http://validator.w3.org/check?uri=".$post->guid."\">".$post->post_title."</a> ".__('check','dprx_bval').": ".date(get_option('date_format'))." ".date("H:i:s");
						if (empty($post->guid)) {
							echo " ".__('Permalink is missing','dprx_bval')."!";
						}
						echo "<br />";
						dprx_bval_update_result($post->ID,"");
					}
				}
			}
		 } else {
			 echo "<span style=\"font-size: 2em;\">".__('Batch validation finished','dprx_bval')."</span>";
			 echo "<br />";
			 update_option("dprx_bval_last_item", "0"); 
			 update_option("dprx_bval_last_check", date(get_option('date_format'))." ".date("H:i:s")); 
			 ?>
			 <script type="text/javascript">
			 	document.getElementById('dprx_loadingstatus').style.display = "none";
			 	if(dprxu!=null) dprxu.stop();
			 </script>
			 <?php
		 }
		exit;
	}
}

wp_enqueue_script('prototype');
/* This is a workaround for WordPress bug http://trac.wordpress.org/browser/trunk/wp-admin/admin-header.php?rev=5640 */
if (eregi("batch-validator",$_GET['page'])) {
add_action('admin_print_scripts', 'dprx_bval_loadjs');
}
function dprx_bval_loadjs() {
	?>
	<script type="text/javascript">
	function dprx_bval_js() {
		document.getElementById('dprx_loadingstatus').style.display = 'block';
		document.getElementById('dprx_batchvalidator').innerHTML = '<?php _e('Starting Validation. Please wait.','dprx_bval') ?>';
		dprxu = new Ajax.PeriodicalUpdater(
		'dprx_batchvalidator',
		'<?php bloginfo("wpurl"); ?>/wp-admin/index.php?page=<?php echo $_REQUEST['page']; ?>',
			{method: 'get', 
			 frequency: 0.3,
			 parameters:'dprx_bval_ajax=1',
			 insertion: Insertion.Top,
			 evalScripts: true}
		);
	}
	</script>
	<?php
}

/* The options page display */
function dprx_bval_options_page() {
	update_option("dprx_bval_last_item","0"); 
	$info = dprx_get_info();
	?>
	<div class=wrap>
		<h2><?php _e('Batch Validator','dprx_bval') ?></h2>
		<p><?php _e('Validating your Wordpress site','dprx_bval') ?>: <?php bloginfo("url") ?></p>
		<?php
		
			
		$last = get_option("dprx_bval_last_check");
		if (!empty($last)) {
			?>
			<p><?php _e('Last validation check','dprx_bval') ?>: <b><?php echo $last; ?></b></p>
			<?php
		} else {
			?>
			<p><?php _e('No check performed since plugin activation','dprx_bval') ?>.</p>
			<?php
		}
		
		// check the CSS
		$css_check_uri = "http://jigsaw.w3.org/css-validator/validator?uri=".get_bloginfo("url")."&warning=0&profile=css2";
		if(dprx_validate($css_check_uri)) {
			?>
			<p style="color:green; font-size: 1.5em"><?php _e('Your stylesheets are valid','dprx_bval') ?></p>
			<?php
		} else {
			?>
			<p style="color:red; font-size: 1.5em"><?php _e('Your stylesheets are invalid','dprx_bval') ?> <input onclick="javascript: window.location.href='<?php echo $css_check_uri; ?>'" type="button" class="button" value="<?php _e('check manually','dprx_bval') ?> &raquo;" /></p>
			<?php
		}
		?>
		<p>
		<?php _e('Validating service used by this validator','dprx_bval') ?>: <b><?php echo $info['checkedby']; ?></b><br />
		<?php _e('The document type of your WordPress site is','dprx_bval') ?>: <b><?php echo $info['doctype']; ?></b><br />
		<?php _e('The character set of your WordPress site is','dprx_bval') ?>: <b><?php echo $info['charset']; ?></b><br />
		<?php _e('Pages with invalid markup are marked','dprx_bval') ?> <span style="color:red;"><?php _e('red','dprx_bval') ?></span>, <?php _e('valid pages','dprx_bval') ?> <span style="color:green;"><?php _e('green','dprx_bval') ?></span>, <?php _e('previously validated pages are not colored','dprx_bval') ?>.<br />
		</p>
		<input onclick="javascript: 
		document.getElementById('dprx_loadingstatus').style.display = 'block';
		document.getElementById('dprx_batchvalidator').innerHTML = '<?php _e('Starting Validation. Please wait.','dprx_bval') ?>';
		dprx_bval_js();" type="button" class="button" value="<?php _e('Check all unvalidated','dprx_bval') ?> &raquo;" />
		
		<input onclick="javascript:window.location.href='<?php bloginfo("url"); ?>/wp-admin/index.php?page=batch-validator/batch-validator.php&dprx_recheck=1'" type="button" class="button" value="<?php _e('Recheck previously validated','dprx_bval') ?> &raquo;" />
		
		<div style="padding-top:20px; display:none;" id="dprx_loadingstatus">
		<img alt="loading..." src="<?php bloginfo("wpurl"); ?>/wp-content/plugins/batch-validator/images/loading.gif" />
		<input onclick="javascript:if(dprxu!=null) { dprxu.stop(); } document.getElementById('dprx_loadingstatus').style.display = 'none';" type="button" class="button" value="<?php _e('Stop validation','dprx_bval') ?> &raquo;" />
		</div>
		<div id="dprx_batchvalidator"></div>
	</div>
	<div class="wrap">
		<p>
		<?php _e("Running into Troubles? Features to suggest?","dprx_bval"); ?>
		<a href="http://wordpress.designpraxis.at/">
		<?php _e("Drop me a line","dprx_bval"); ?> &raquo;
		</a>
		</p>
		<div style="display: block; height:30px;">
			<div style="float:left; font-size: 16px; padding:5px 5px 5px 0;">
			<?php _e("Do you like this Plugin?","dprx_bval"); ?>
			<?php _e("Consider to","dprx_bval"); ?>
			</div>
			<div style="float:left;">
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_xclick">
			<input type="hidden" name="business" value="rol@rm-r.at">
			<input type="hidden" name="no_shipping" value="0">
			<input type="hidden" name="no_note" value="1">
			<input type="hidden" name="currency_code" value="EUR">
			<input type="hidden" name="tax" value="0">
			<input type="hidden" name="lc" value="AT">
			<input type="hidden" name="bn" value="PP-DonationsBF">
			<input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but21.gif" border="0" name="submit" alt="Please donate via PayPal!">
			<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
			</form>
			</div>
		</div>
	</div>
	<?php
	
	if (!empty($_REQUEST['dprx_recheck'])) {
		$sql = "DELETE
			FROM `wp_postmeta`
			WHERE `meta_key` = '_dprx_w3c_valid'";  
		$res = $GLOBALS['wpdb']->query($sql);
		?>
		<script type="text/javascript">
		dprx_bval_js();
		</script>
		<?php
	}
}

function dprx_get_info() {
	$info = array();
	$page = dprx_validate("http://validator.w3.org/check?uri=".get_bloginfo("url")."&output=soap12",1);
	$cs = explode("\n",$page);
	foreach($cs as $c) {
		if (eregi("m:checkedby",$c)) {
			$info['checkedby'] = trim(strip_tags($c));
		}
		if (eregi("m:doctype",$c)) {
			$info['doctype'] = trim(strip_tags($c));
		}
		if (eregi("m:charset",$c)) {
			$info['charset'] = trim(strip_tags($c));
		}
	}
	return $info;
}

function dprx_validate($uri,$return=""){
	require_once (ABSPATH . WPINC . '/class-snoopy.php');
	$client = new Snoopy();
	@$client->fetch($uri);
	$data = $client->results;
	$data = explode("\n",$data);
	foreach ($data as $buffer) {
	    if (!empty($return)) {
		    $ret .= $buffer."\n";
	    }
	    if (eregi("m:validity",$buffer)) {
		    $buffer = trim(strip_tags($buffer));
		    break;
	    }
	}
	if (!empty($return)) {
	    return $ret;
	}
	if($buffer == "true") {
	    return true;
	} else {
	    return false;
	}
}

function dprx_get_meta($id){
	$sql = "SELECT * FROM " . $GLOBALS['wpdb']->postmeta . " 
	WHERE post_ID = '".$id."' AND meta_key = '_dprx_w3c_valid'";
	$res = $GLOBALS['wpdb']->get_results($sql, ARRAY_A);
	return $res[0];
}

function dprx_bval_get_meta_valid($id) {
	$meta = dprx_get_meta($id);
	return $meta['meta_value'];
}

function dprx_bval_update_result($id,$value="") {
	$sql = "DELETE FROM " . $GLOBALS['wpdb']->postmeta . " 
	WHERE post_ID = '".$id."' AND meta_key = '_dprx_w3c_valid'";
	$res = $GLOBALS['wpdb']->query($sql);
	if (!empty($value)) {
		$sql = "INSERT INTO " . $GLOBALS['wpdb']->postmeta . " 
		(post_ID,meta_key,meta_value) VALUES('".$id."','_dprx_w3c_valid','".$value."')";
		$res = $GLOBALS['wpdb']->query($sql);
	}
}
?>