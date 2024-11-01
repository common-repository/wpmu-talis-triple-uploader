<?php
/***************************************************************************
Plugin Name: WPMU Talis Triple Uploader
Plugin URI: http://code.google.com/p/jiscpress
Description: Uploads RDF Triples to be uploaded to the Talis Platform
Version: 0.1
Author: Alex Bilbie
Author URI: http://www.alexbilbie.com/

****************************************************************************

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote
products derived from this software without specific prior written
permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING
IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

http://www.xfree86.org/3.3.6/COPYRIGHT2.html#5

****************************************************************************

This plugin was created for the JISC (www.jisc.ac.uk) funded JISCPress project (jiscpress.blogs.lincoln.ac.uk)

***************************************************************************/

//error_reporting(E_ALL);

add_action('talis_run_cron', array('Talis_uploader', 'run_cron'));
add_action('admin_menu', array('Talis_uploader', 'make_menus'));

register_activation_hook( __FILE__, array('Talis_uploader', 'activate'));
register_deactivation_hook( __FILE__, array('Talis_uploader', 'deactivate'));

class Talis_uploader {

	function activate(){
		global $current_site;
		
		$data = array( 'talis_username' => '', 'talis_password' => '', 'talis_store' => '', 'default_license' => 'http://creativecommons.org/publicdomain/zero/1.0/', 'cron' => 'daily', 'rdf_store' => '/var/www/example.com/triplify/cache/', 'registered' => array());
		if ( ! get_site_option($current_site->id, 'talis_global')){
			add_site_option($current_site->id, 'talis_global' , $data);
		} else {
			update_site_option($current_site->id, 'talis_global' , $data);
		}
		self::register_cron('daily');
	}
	
	function deactivate(){
		wp_clear_scheduled_hook('talis_run_cron');
	}
	
	function register_cron($freq){
		if (!wp_next_scheduled('talis_run_cron')) {
			wp_schedule_event(time(), $freq, 'talis_run_cron');
		}
	}
	
	function run_cron()
	{
		// Get talis global options
		$global_talis_options = get_site_option('talis_global');
		
		$path = $global_talis_options['rdf_store'];
		$registered = $global_talis_options['registered'];
		$up = $global_talis_options['talis_username'].$global_talis_options['talis_password'];
		
		echo '<div class="updated">';
		
		// If blogs have setup Talis
		if(count($registered) > 0){
		
			foreach($registered as $blog)
			{
				
				// Check file exists
				if(file_exists($path.$blog)){
				
					// Upload it
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_VERBOSE, 0);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_USERPWD, $up);
					curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
					curl_setopt($ch, CURLOPT_URL, "http://api.talis.com/stores/".$global_talis_options['talis_store']."/meta");
					curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/rdf+n3'));
					curl_setopt($ch, CURLOPT_POST, true);
					$post = "@".$path.$blog;
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post); 
					$response = curl_exec($ch);
					
				} else {
				
					echo "<p><b>Warning!</b> Can't find ".$path.$blog."</p>";
				
				}
			}
		
		} else {
			echo "<p>No files to upload.</p>";
		}
		
		echo '</div>';
	}
	
	
	function make_menus()
	{
		// Add options page to the WPMU admin menu
		add_submenu_page('wpmu-admin.php', 'Talis Global Options','Talis Global Options', '10', 'talis_display', array('Talis_uploader', 'talis_display_admin'));
		// Add options page to the blog options menu
		add_submenu_page('options-general.php', 'Talis Options','Talis Options', '6', 'talis_display_options', array('Talis_uploader', 'talis_display_options'));
	}
	
	function talis_display_admin()
	{
		global $wpdb;
		global $blog_id;
		global $domain;
		global $current_site;
		
		// Get talis global options
		$global_talis_options = get_site_option('talis_global');
		
		$updated = FALSE;
		
		if(isset($_POST['talis_save'])){
			$global_talis_options['talis_username'] = mysql_real_escape_string($_POST['talis_username']);
			$global_talis_options['talis_password'] = mysql_real_escape_string($_POST['talis_password']);
			$global_talis_options['talis_store'] = mysql_real_escape_string($_POST['talis_store']);
			$global_talis_options['cron'] = mysql_real_escape_string($_POST['talis_cron']);
			$global_talis_options['default_license'] = mysql_real_escape_string($_POST['talis_license']);
			$global_talis_options['rdf_store'] = mysql_real_escape_string($_POST['rdf_store']);
			
			update_site_option('talis_global', $global_talis_options);
			
			$updated = TRUE;
		}
		
		if(isset($_POST['force_cron'])){
			self::run_cron();
		}
	?>
		
		<div class="wrap">
			<h2>Talis Global Options</h2>
			
			<?php
			if($updated):
			?>
			<div class="updated fade">
				<p>Your changes were saved</p>
			</div>
			<?php
			endif;
			?>
			
			<form action="" method="post">
				<table class="form-table">
					<tbody>
					
						<tr class="form-field">
							<th scope="row">Talis username</th>
							<td>
								<input type="text" name="talis_username" value="<?php echo $global_talis_options['talis_username']; ?>" />
								<p>If this field is empty no content will be sent to Talis</p>
							</td>
						</tr>
						
						<tr class="form-field">
							<th scope="row">Talis password</th>
							<td>
								<input type="password" name="talis_password" value="<?php echo $global_talis_options['talis_password']; ?>" />
								<p>If this field is empty no content will be sent to Talis</p>
							</td>
						</tr>
						
						<tr class="form-field">
							<th scope="row">Talis store</th>
							<td>
								<input type="password" name="talis_store" value="<?php echo $global_talis_options['talis_store']; ?>" />
								<p>If this field is empty no content will be sent to Talis</p>
							</td>
						</tr>
						
						<tr class="form-field">
							<th scope="row">Cron executes</th>
							<td>
								<?php
								$cron = $global_talis_options['cron'];
								?>
								<input type="radio" style="width:auto" name="talis_cron" value="daily" <?php if($cron == 'daily'):?>checked="checked"<?php endif; ?> name="talis_executes" /> Daily<br/>
								<input type="radio" style="width:auto" name="talis_cron" value="twicedaily" <?php if($cron == 'twicedaily'):?>checked="checked"<?php endif; ?> name="talis_executes" /> Twice daily<br/>
								<input type="radio" style="width:auto" name="talis_cron" value="hourly" <?php if($cron == 'hourly '):?>checked="checked"<?php endif; ?> name="talis_executes" /> Hourly<br/>
							</td>
						</tr>
						
						<tr class="form-field">
							<th scope="row">Default license</th>
							<td>
								<input type="text" name="talis_license" value="<?php echo $global_talis_options['default_license']; ?>" />
								<p>This is the URL to the license that is applied to a Triple if a blog owner has not supplied their own license.</p>
								<p>Please consider an <a href="http://www.opendatacommons.org/licenses/pddl/1.0/">Open Data Commons compatible license</a>.</p>
							</td>
						</tr>
						
						<tr class="form-field">
							<th scope="row">RDF store path</th>
							<td>
								<p><input type="text" name="rdf_store" value="<?php echo $global_talis_options['rdf_store']; ?>" /></p>
								<p>This is the folder on the server in which your RDF triples are stored.</p>
								<p>This will generally be something like <u>/var/www/example.com/triplify/cache/</u>.</p>
								<p><b>You must include the trailing slash!</b></p>
							</td>
						</tr>
						
						<tr class="form-field">
							<th scope="row">&nbsp;</th>
							<td>
								<input type="submit" value="Update options" style="width:auto" name="talis_save" />
							</td>
						</tr>
						
						
					</tbody>
				</table>
			</form>
			
			<table class="form-table">
				<tbody>
					<tr class="form-field">
						<th scope="row">Force RDF upload</th>
						<td>
							<form method="post" action="">
								<input type="submit" value="Run" style="width:auto" name="force_cron" /> <b>Warning!</b> Hitting this process will start a process that can take a long time.
							</form>
						</td>
					</tr>
				</tbody>
			</table>
			
		</div>
	
	<?php
	}	
	
	function talis_display_options()
	{
		global $wpdb;
		global $blog_id;
		global $domain;
		global $current_site;
		
		$updated = FALSE;
		
		// Get talis global options
		$global_talis_options = get_site_option('talis_global');
		
		// Update blog public status
		if(isset($_POST['makepublic'])){
			$sql1 = "UPDATE `wp_{$blog_id}_options` SET `option_value` = '1' WHERE `option_name` = 'blog_public'";
			$sql2 = "UPDATE `wp_blogs` SET `public` = '1' WHERE `blog_id` = '{$blog_id}'";
			$wpdb->query($sql1);
			$wpdb->query($sql2);
			$updated = TRUE;
		}
		
		// Undo public status
		if(isset($_POST['undopublic'])){
			$sql1 = "UPDATE `wp_{$blog_id}_options` SET `option_value` = '0' WHERE `option_name` = 'blog_public'";
			$sql2 = "UPDATE `wp_blogs` SET `public` = '0' WHERE `blog_id` = '{$blog_id}'";
			$wpdb->query($sql1);
			$wpdb->query($sql2);
			$updated = TRUE;
		}
		
		// Get public status
		$is_public = get_blog_option($blog_id, 'blog_public');

		// Get blog talis options
		$talis_options = array( 'talis_agree' => false, 'blog_license' => 'http://creativecommons.org/publicdomain/zero/1.0/');
		if (!get_blog_option($blog_id, 'talis_blog')){
			add_blog_option($blog_id, 'talis_blog' , $talis_options);
		} else {
			$talis_options = get_blog_option($blog_id, 'talis_blog');
		}
		
		// Agree to T&C
		if(isset($_POST['talis_agree'])){
		
			$talis_options['talis_agree'] = TRUE;
			update_blog_option($blog_id, 'talis_blog', $talis_options);
			
			$md5 = md5($domain);
			
			$global_talis_options['registered'][$md5] = $md5;
			update_site_option('talis_global', $global_talis_options);	
			
			$updated = TRUE;					
			
		}
		
		// Disable Talis
		if(isset($_POST['disabletalis'])){
		
			$md5 = md5($domain);
			$talis_options['talis_agree'] = FALSE;
			update_blog_option($blog_id, 'talis_blog', $talis_options);	
			
			if(isset($global_talis_options['registered'][$md5])){
				unset($global_talis_options['registered'][$md5]);
				update_site_option('talis_global', $global_talis_options);
			}
						
		}
		
		// Update blog license
		if(isset($_POST['talis_blog_license'])){
		
			$license = $_POST['talis_blog_license'];
			$talis_options['blog_license'] = $license;
			
			if(!empty($license)){
				update_blog_option($blog_id, 'talis_blog', $talis_options);
				$updated = TRUE;						
			}
			
		}
		
	?>
		<div class="wrap">
			<?php
			if($updated):
			?>
			<div class="updated fade">
				<p>Your changes were saved</p>
			</div>
			<?php
			endif;
			?>
		
			<h2>Talis Options for "<?php echo $domain; ?>"</h2>
			
			<p>The Talis Platform is an environment for building next generation applications and services based on Semantic Web technologies. It is a hosted system which provides an efficient, robust storage infrastructure. Both arbitrary documents and RDF-based semantic content are supported, with sophisticated query, indexing and search features.</p>
			
			<p>RDF triples created from your blog will only be uploaded to Talis with your permission.</p>
			
			<?php if($talis_options['talis_agree'] == false): ?>
			
			<table class="form-table">
				<tbody>
					<tr class="form-field">
						<th scope="row">Agreement</th>
						<td>
							<p><form method="post"><input type="submit" name="talis_agree" value="I agree to the Talis Platform terms and conditions" style="width:auto" /></form></p>
							<p>In order for you your data to be pushed to Talis you must agree to the <a href="http://n2.talis.com/wiki/Terms">Talis Platform terms and conditions</a>.</p>
						</td>
					</tr>
				</tbody>
			</table>
			
			<?php else: ?>
			<form method="post" action="">
				<table class="form-table">
					<tbody>
						<tr class="form-field">
							<th scope="row">Public blog</th>
							<td>
								<?php if($is_public == FALSE): ?>
								<p>In order for Triplify to create RDF from your blog to be uploaded to Talis your blog must be set as publicly available.</p>
								<p>Your blog is <b>not currently</b> public.</p>
								<form method="post"><input type="submit" name="makepublic" value="Make my blog public" style="width:auto" /></form>
								<?php else: ?>
								<p>Your blog is public.</p>
								<form method="post"><input type="submit" name="undopublic" value="Undo" style="width:auto" /></form>
								<?php endif; ?>
							</td>
						</tr>
						
						<tr class="form-field">
							<th scope="row">License</th>
							<td>
								<p>The system administrator has set the default license to <u><?php echo $global_talis_options['default_license']; ?></u>, if you wish to use a different license please supply it below.</p>
								<p>Please consider using a compatible <a href="http://www.opendatacommons.org/licenses/pddl/1.0/">Open Data Commons license</a></p>
								<input type="text" name="talis_blog_license" value="<?php echo $talis_options['blog_license']; ?>" />
							</td>
						</tr>
	
					</tbody>
				</table>
			</form>
			
			<table class="form-table">
				<tbody>
						
					<tr class="form-field">
						<th scope="row">Delete my data</th>
						<td>
							<p>Data held on the Talis Platform is <b>your</b> property and you can remove it at any time. Click the button below to remove your data.</p>
							<form method="post"><input type="submit" name="deletetalis" value="I wish to delete my data" style="width:auto" /></form>
							<p>N.B. A request to delete your data will be made immediately however it made take a short time for Talis to complete your request.</p>
						</td>
					</tr>
					
					<tr class="form-field">
						<th scope="row">Disable Talis for my blog</th>
						<td>
							<form method="post"><input type="submit" name="disabletalis" value="I wish to disable Talis for my domain" style="width:auto" /></form>
							<p>N.B. This will not delete any data held by Talis, it will just stop data being updated on Talis.</p>
						</td>
					</tr>

				</tbody>
			</table>
			
			<?php endif; ?>
			
		</div>
	
	<?php
	}
	
}