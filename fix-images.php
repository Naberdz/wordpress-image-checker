<?php
/**
 * Plugin Name: Missing Image checker
 * Plugin URI: http://www.wemessage.nl/
 * Description: Finds and removes unused images from uploads map, checks database for non existent images
 * Author: Naberd @ Wemessage
 * Author URI: https://www.wemessage.nl/
 * Version: 1.0
 * Text Domain: wemessage_fix_images
 * Domain Path: /languages/
 *
 */
 
add_action( 'init', 'wemessage_fix_images_load_textdomain' ); 
function wemessage_fix_images_load_textdomain() {
    load_plugin_textdomain( 'wemessage_fix_images', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}

add_action('admin_menu',  'wemessage_fix_images_admin_menu', 9);
function wemessage_fix_images_admin_menu() {
    add_menu_page(__('Missing image checker','wemessage_fix_images'), __('Missing image checker','wemessage_fix_images'), 'administrator', 'wemessage-fix-images', 'wemessage_fix_images_page', plugins_url('/images/icon.png', __FILE__));
}

function wemessage_fix_images_page(){?>
    <div class="wrap">
        <h2><?=__('Check images', 'wemessage_fix_images');?></h2>
        <div class="clear"></div>
        <div id="poststuff">
            <div class="post-body">
                <div class="postbox">
                    <div class="inside">
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">
                                    <span class="button button-primary" onclick="checkDatabase()"><?=__('Check database', 'wemessage_fix_images'); ?></span>
                                </th>
                                <td>
                                    <p class="records"><?=sprintf(__('Found %s records','wemessage_fix_images'), '<span class="amount">0</span>');?></p>
                                    <span class="description"><?=__('This will search in database for non existing images', 'wemessage_fix_images');?></span>
                                </td>
                            </tr>
                        </table>
                        <div id="dashboard-widgets" class="metabox-holder">
                            <div id="databaseRecords" class="postbox" style="display:none;">
                                <div class="postbox-header"><h2 class="hndle ui-sortable-handle"><?=__('Database records', 'wemessage_fix_images');?></h2></div>
                                <div class="inside">
                                    <div class="progressbar" style="width:100%;height:25px;clear:both; background:#efefef;border:1px inset #efefef">
                                        <div class="progress" style="width:0; height:23px;margin:1px 0;background:#007cba;"></div>
                                    </div>
                                    <div id="deleteDatabase" style="display:none;">
                                        <span class="button button-primary" onclick="deleteRecords()"><?=__('Delete all records', 'wemessage_fix_images');?></span>
                                        <span class="button button-primary" onclick="deleteRecords('notfound')"><?=__('Delete not found records', 'wemessage_fix_images');?></span>
                                    </div>
                                    <div id="foundrecords" style="display:none;">
                                    
                                    </div>
                                </div>
                            </div>
                        </div>  
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function checkDatabase(){
            var data = {
                'action': 'check_database',
            };
            jQuery.post(ajaxurl, data, function(response) {
                var records = JSON.parse(response);
                jQuery('#databaseRecords').show();
                processDatabase(records.length, records);
            });
        }
        function processDatabase(length, records, limit=10){
            var ar = records.splice(0,limit);
            if(records.length){
                jQuery.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: {
                        'action': 'check_records',
                        'ids': ar
                    },
                    success: function(data){
                        jQuery('#foundrecords').append(data);
                        jQuery('.records .amount').text(jQuery('#foundrecords .notFound').length);
                    }
                }).done(function(data, textStatus, jqXHR){
                    processDatabase(length, records);
                    var x = ((length - records.length) * 100) / length;
                    jQuery('#databaseRecords .progress').width(x+'%');
                });
            } else {
                jQuery('#deleteDatabase').show();
                jQuery('#foundrecords').show();
            }
        }
        function deleteRecord(el){
            var data = {
                'action': 'delete_record',
                'id': jQuery(el).closest('.notFound').data(id)
            };
            jQuery.post(ajaxurl, data, function(response) {
                jQuery(el).closest('.notFound').remove();
            });
        }
        
        function deleteRecords(action='all'){
            var ids = Array();
            if(action == 'notfound'){
                jQuery('#foundrecords .notFound.file').each(function(){
                    ids.push(jQuery(this).data('id'));
                });
            } else {
                jQuery('#foundrecords .notFound').each(function(){
                    ids.push(jQuery(this).data('id'));
                });
            }
            var data = {
                'action': 'delete_records',
                'ids': ids
            };
            jQuery.post(ajaxurl, data, function(response) {
                jQuery(ids).each(function(i,v){
                    jQuery('#foundrecords .notFound').filter(function(){
                        return jQuery(this).data('id') === v
                    }).remove();
                });
            });
        }
    </script>
<?php 
}

add_action( 'wp_ajax_check_database', 'check_database' );
function check_database() {
    global $wpdb; 
    $table_name = $wpdb->prefix . 'posts';
    $results = $wpdb->get_results('select ID from '.$table_name.' where post_type="attachment"');
    echo json_encode($results);
    wp_die();
}

add_action( 'wp_ajax_check_records', 'check_records' );
function check_records() {
    global $wpdb;
    foreach($_POST['ids'] as $id){
        $results = $wpdb->get_results('select * from '.$wpdb->postmeta.' where meta_key="_wp_attached_file" and post_id='.$id['ID']);
        $res = $wpdb->get_results('select * from '.$wpdb->posts.' where ID='.$id['ID']);
        if(count($results)){
            if($results[0]->meta_key=='_wp_attached_file'){
                $found = false;
                $media = wp_upload_dir();
                $it = new RecursiveDirectoryIterator($media['basedir']);
                
                foreach(new RecursiveIteratorIterator($it) as $file){
                    if($file->getFilename() == $results[0]->meta_value){
                    	$found = true;
                    	if($file->getPathname() != $media['basedir'].'/'.$results[0]->meta_value){
                    		echo '<p class="notFound file" style="padding:5px; border:1px solid; background:#eee; width:calc(100% - 12px); display:inline-block;" data-id="'.$id['ID'].'">'.sprintf(__('File %s was misplaced should be as: %s and found as: %s', 'wemessage_fix_images'),'<b>'.$results[0]->meta_value.'</b>', $file->getPathname(), '<b>'.$media['basedir'].'/'.$results[0]->meta_value.'</b>').'</p>';
                    		//rename($file->getPathname(), $media['basedir'].'/'.$results[0]->meta_value);
                    	}
                    }
                }
                if(!$found){
                    if($res[0]->post_parent){
                        echo '<p class="notFound file" style="padding:5px; border:1px solid; background:#eee; width:calc(100% - 12px); display:inline-block;" data-id="'.$id['ID'].'">'.sprintf(__('File %s was not found on a server', 'wemessage_fix_images'),'<b>'.$results[0]->meta_value.'</b>').'<span class="button button-secondary pull-right" onclik="deleteRecord(this)">'.__('Delete', 'wemessage_fix_images').'</p>';
                    } else {
                        echo '<p class="notFound file record" style="padding:5px; border:1px solid; background:#fff;width:calc(100% - 12px); display:inline-block;" data-id="'.$id['ID'].'"><img src="'.$media['baseurl'].'/'.$results[0]->meta_value.'" width="40" style="margin-right:10px;" />'.sprintf(__('Image %s was not attached to a post and was not found on server', 'wemessage_fix_images'), '<b>'.$results[0]->meta_value.'</b>').'<span class="button button-secondary pull-right" onclik="deleteRecord(this)">'.__('Delete', 'wemessage_fix_images').'</span></p>';
                    }
                }
            }
        } else {
            echo '<p class="notFound record" style="padding:5px; border:1px solid; background:#fff;width:calc(100% - 12px); display:inline-block;" data-id="'.$id['ID'].'">'.sprintf(__('Image %s was not attached to a post', 'wemessage_fix_images'), '<b>'.$res[0]->post_name.'</b>').'<span class="button button-secondary pull-right" onclik="deleteRecord(this)">'.__('Delete', 'wemessage_fix_images').'</span></p>';
        }
    }
    wp_die();
}

add_action( 'wp_ajax_delete_records', 'delete_records');
function delete_records() {
    global $wpdb;
    $results = $wpdb->get_results('select * from '.$wpdb->postmeta.' where meta_key="_wp_attached_file" and post_id in ('.implode(',',$_POST['ids']).')');
    foreach($results as $result){
        $media = wp_upload_dir();
        $it = new RecursiveDirectoryIterator($media['basedir']);
        foreach(new RecursiveIteratorIterator($it) as $file){
            if($file->getFilename() == $result->meta_value) {
                unlink($file->getPathname());
            }
        }
    }
    $wpdb->query('delete from '.$wpdb->postmeta.' where post_id in ('.implode(',',$_POST['ids']).')');
    $wpdb->query('delete from '.$wpdb->posts.' where ID in ('.implode(',',$_POST['ids']).')');
    wp_die();
}

add_action( 'wp_ajax_delete_record', 'delete_record');
function delete_record() {
    global $wpdb;
    $results = $wpdb->get_results('select * from '.$wpdb->postmeta.' where meta_key="_wp_attached_file" and post_id='.$_POST['id']);
    foreach($results as $result){
        $media = wp_upload_dir();
        $it = new RecursiveDirectoryIterator($media['basedir']);
                
        foreach(new RecursiveIteratorIterator($it) as $file){
            if($file->getFilename() == $result->meta_value) {
                unlink($file->getPathname());
            }
        }
    }
    $wpdb->query('delete from '.$wpdb->postmeta.' where post_id='.$_POST['id']);
    $wpdb->query('delete from '.$wpdb->posts.' where ID='.$_POST['id']);
    wp_die();
}