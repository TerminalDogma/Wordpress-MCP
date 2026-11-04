<?php
use MSC\{Plugin,Schema,Acf,Queue};
if ( ! function_exists( 'acf_form' ) ) { throw new RuntimeException( 'ACF must be active for adapter tests.' ); }
$GLOBALS['msc_acf_checks'] = 0;
function acf_check_msc( $condition, string $message ): void { ++$GLOBALS['msc_acf_checks']; if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } WP_CLI::log( 'PASS: ' . $message ); }
function acf_at_msc( int $site, callable $fn ) { switch_to_blog( $site ); try { return $fn(); } finally { restore_current_blog(); } }
function acf_drain_msc(): void { for ( $i=0; $i<100 && Queue::status()['pending']; ++$i ) { $r=Queue::run(1000); if($r['errors']||!$r['processed']){throw new RuntimeException('ACF test queue failure.');} } }
function acf_request_msc( array $r, string $scope, array $values, array $override=array() ): array { return array( 'post_id'=>$r['mapping']['post_id'], 'nonce'=>wp_create_nonce('msc_acf_'.$r['mapping']['post_id']), 'scope'=>$scope, 'version'=>$r['version'], 'local_version'=>$r['mapping']['local_version'], 'acf'=>$values, 'override'=>$override ); }
function acf_reject_msc( callable $fn, string $message ): void { $caught=false; try{$fn();}catch(Throwable $e){$caught=true;} acf_check_msc($caught,$message); }
wp_set_current_user(1);
$sites=array(); foreach(get_sites(array('number'=>0)) as $site){if(in_array($site->path,array('/site-a/','/site-b/','/site-c/'),true)){$sites[]=(int)$site->blog_id;}}
[$a,$b,$c]=$sites;
$schemas=Schema::all();
$schemas['msc_show']['fields']['acf_flag']=array('type'=>'boolean');
$schemas['msc_show']['fields']['acf_choices']=array('type'=>'multiselect','options'=>array('a','b'));
$schemas['msc_show']['fields']['acf_locked']=array('type'=>'text','override'=>false);
Schema::save($schemas); Acf::register();
$r=acf_at_msc($a,static fn()=>Plugin::store()->create('msc_show',array('title'=>'ACF Adapter Show','premiere_date'=>'2098-01-01','network'=>'Shared Network','number_of_episodes'=>10,'acf_flag'=>true,'acf_choices'=>array('a'),'acf_locked'=>'Locked')));
acf_drain_msc(); $id=(int)$r['id'];
acf_at_msc($a,static function()use($id){
	$post=msc_local_post_id($id);
	acf_check_msc('20980101'===get_field('msc_premiere_date',$post,false),'ACF date raw value uses Ymd and field names resolve without stored references');
	acf_check_msc('2098-01-01'===get_field('msc_premiere_date',$post),'ACF date formatting returns ISO date');
	acf_check_msc((bool)acf_get_field(Acf::key('msc_show','network')),'Supported scalar ACF field is registered');
	acf_check_msc('msc_media'===acf_get_field(Acf::key('msc_show','poster'))['type'],'Cross-site media uses the owner-aware ACF preview control');
});
acf_at_msc($b,static function()use($id){
	$post=msc_local_post_id($id);
	acf_check_msc(true===update_field('msc_network','Site B Network',$post),'Programmatic ACF write creates a local override');
	acf_check_msc(msc_is_overridden($id,'network')&&'Site B Network'===get_field('msc_network',$post),'ACF load returns current resolved local value');
	acf_check_msc(false===update_field('msc_acf_locked','Bypass',$post),'ACF write cannot bypass network field lock');
	$r=Plugin::store()->resolved($id); $request=acf_request_msc($r,'local',array(Acf::key('msc_show','network')=>'ignored'));
	Acf::save($request); acf_check_msc(!msc_is_overridden($id,'network'),'Unchecked ACF override resets inheritance');
});
acf_at_msc($c,static function()use($id){
	$r=Plugin::store()->resolved($id);
	$values=array(Acf::key('msc_show','network')=>'',Acf::key('msc_show','number_of_episodes')=>'0',Acf::key('msc_show','acf_flag')=>'0',Acf::key('msc_show','acf_choices')=>'');
	Acf::save(acf_request_msc($r,'local',$values,array('network'=>1,'number_of_episodes'=>1,'acf_flag'=>1,'acf_choices'=>1)));
	foreach(array('network'=>'','number_of_episodes'=>0,'acf_flag'=>false,'acf_choices'=>array()) as $key=>$value){acf_check_msc($value===msc_get_field($id,$key)&&msc_is_overridden($id,$key),'ACF explicit falsey override survives: '.$key);}
});
acf_at_msc($b,static function()use($id){
	$r=Plugin::store()->resolved($id); $request=acf_request_msc($r,'global',array(Acf::key('msc_show','network')=>'New Shared Network',Acf::key('msc_show','premiere_date')=>'20980201'));
	Acf::save($request);
	acf_reject_msc(static fn()=>Acf::save($request),'ACF stale global form cannot overwrite a newer revision');
	$r=Plugin::store()->resolved($id); $request=acf_request_msc($r,'local',array(Acf::key('msc_show','network')=>'Local'),array('network'=>1)); Acf::save($request);
	acf_reject_msc(static fn()=>Acf::save($request),'ACF stale local form is rejected');
	$request=acf_request_msc(Plugin::store()->resolved($id),'global',array());$request['nonce']='invalid';
	acf_reject_msc(static fn()=>Acf::save($request),'ACF submission requires its own action nonce');
});
acf_drain_msc();
acf_check_msc('New Shared Network'===msc_get_field($id,'network',$a)&&''===msc_get_field($id,'network',$c),'ACF global change reaches an inheriting site and retains another site override');
acf_check_msc('2098-02-01'===msc_get_field($id,'premiere_date',$c),'ACF date save converts raw Ymd to canonical ISO');
$episode=acf_at_msc($a,static fn()=>Plugin::store()->create('msc_episode',array('title'=>'ACF Episode','parent_show'=>$r['uuid'],'season_number'=>1,'episode_number'=>1)));
acf_drain_msc();
acf_at_msc($b,static function()use($episode,$id){
	$post=msc_local_post_id($episode['id']);$parent=msc_local_post_id($id);
	acf_check_msc($parent==get_field('msc_parent_show',$post),'ACF Post Object resolves a global relationship into the current site post ID');
	acf_check_msc(true===update_field('msc_parent_show',$parent,$post),'ACF Post Object write accepts the current site representation');
	acf_check_msc(msc_get_global($id,'title')==='ACF Adapter Show'&&msc_get_field($episode['id'],'parent_show')===Plugin::store()->get($id)['uuid'],'ACF relationship write stores the permanent network UUID');
});
$editor=wp_insert_user(array('user_login'=>'acf-local-'.wp_generate_password(8,false),'user_pass'=>wp_generate_password()));add_user_to_blog($a,$editor,'editor');wp_set_current_user($editor);
acf_at_msc($a,static function()use($id){$request=acf_request_msc(Plugin::store()->resolved($id),'global',array(Acf::key('msc_show','network')=>'Unauthorized'));acf_reject_msc(static fn()=>Acf::save($request),'Local editor cannot use the ACF global form');});
wp_set_current_user(1);
acf_at_msc($a,static function(){ $role=get_role('editor');$had=$role->has_cap('msc_override_local');$version=get_option('msc_roles_version');try{$role->remove_cap('msc_override_local');update_option('msc_roles_version','old-version');\MSC\Permissions::ensure_roles();acf_check_msc(!$role->has_cap('msc_override_local'),'Plugin upgrades preserve intentionally removed role capabilities');}finally{if($had){$role->add_cap('msc_override_local');}update_option('msc_roles_version',$version);} });
acf_at_msc($a,static function(){acf_add_local_field(array('key'=>'field_unrelated_msc_test','name'=>'unrelated_msc_test','type'=>'text'));$post=wp_insert_post(array('post_title'=>'Ordinary ACF test','post_type'=>'page','post_status'=>'draft'));update_field('field_unrelated_msc_test','Unrelated',$post);acf_check_msc('Unrelated'===get_field('field_unrelated_msc_test',$post),'Unrelated ACF fields keep standard load/save behavior');wp_delete_post($post,true);});
// Actual cookie-authenticated ACF form GET and action POST through Apache.
acf_at_msc($b,static function()use($id){
	$base=rtrim(getenv('WORDPRESS_URL')?:'http://localhost:8080','/');$host=wp_parse_url($base,PHP_URL_HOST);$port=wp_parse_url($base,PHP_URL_PORT)?:80;
	if(!in_array($host,array('localhost','127.0.0.1'),true)){throw new RuntimeException('HTTP tests are local only.');}
	$token=WP_Session_Tokens::get_instance(1)->create(time()+300);
	$cookie=LOGGED_IN_COOKIE.'='.wp_generate_auth_cookie(1,time()+300,'logged_in',$token).'; '.AUTH_COOKIE.'='.wp_generate_auth_cookie(1,time()+300,'auth',$token);
	$request=static function($path,$data=null)use($base,$host,$port,$cookie){$ch=curl_init($base.$path);curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECT_TO=>array($host.':'.$port.':wordpress:80'),CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>array('Cookie: '.$cookie)));if(null!==$data){curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));}$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if(false===$body){throw new RuntimeException(curl_error($ch));}curl_close($ch);return array($status,$body);};
	try{
		$r=Plugin::store()->resolved($id);[$status,$body]=$request('/site-b/wp-admin/admin.php?page=msc-acf&record='.$r['uuid'].'&scope=global');
		acf_check_msc(200===$status&&str_contains($body,'GLOBAL EDITING')&&str_contains($body,'data-type="date_picker"'),'Real ACF global form renders native ACF date controls');
		preg_match('/name="nonce" value="([^"]+)"/',$body,$matches);if(empty($matches[1])){throw new RuntimeException('ACF form nonce missing.');}
		$payload=array('action'=>'msc_acf_save','post_id'=>$r['mapping']['post_id'],'scope'=>'global','nonce'=>html_entity_decode($matches[1]),'version'=>$r['version'],'acf'=>array(Acf::key('msc_show','network')=>'HTTP ACF Shared'));
		[$status]=$request('/site-b/wp-admin/admin-post.php',$payload);acf_check_msc(302===$status&&'HTTP ACF Shared'===msc_get_global($id,'network'),'Authenticated ACF form saves shared values through its real action');
		[$status]=$request('/site-b/wp-admin/admin-post.php',$payload);acf_check_msc(409===$status,'Real ACF form rejects stale global submissions with HTTP 409');
		$payload['nonce']='bad';[$status]=$request('/site-b/wp-admin/admin-post.php',$payload);acf_check_msc(403===$status,'Real ACF form rejects invalid nonces with HTTP 403');
	}finally{WP_Session_Tokens::get_instance(1)->destroy($token);}
});
WP_CLI::success($GLOBALS['msc_acf_checks'].' real ACF adapter checks passed.');
