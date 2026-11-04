<?php
/** Type-specific fields, safe 1.1 upgrades, and cross-site Movie behavior. */
use MSC\{Schema,Plugin,Queue,Acf};
$GLOBALS['msc_catalog_checks'] = 0;
function catalog_check( $condition, string $message ): void { ++$GLOBALS['msc_catalog_checks']; if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } WP_CLI::log( 'PASS: ' . $message ); }
function catalog_at( int $site, callable $fn ) { switch_to_blog( $site ); try { return $fn(); } finally { restore_current_blog(); } }
function catalog_drain(): void { for ( $i=0; $i<100 && Queue::status()['pending']; ++$i ) { $r=Queue::run(1000); if($r['errors']||!$r['processed']){throw new RuntimeException('Catalog queue failure.');} } }
function catalog_reject( callable $fn, string $message ): void { $caught=false;try{$fn();}catch(Throwable $e){$caught=true;}catalog_check($caught,$message); }
function catalog_form( int $id, string $scope, array $values, array $overrides=array() ): array { $r=Plugin::store()->resolved($id);return array('post_id'=>$r['mapping']['post_id'],'nonce'=>wp_create_nonce('msc_acf_'.$r['mapping']['post_id']),'scope'=>$scope,'version'=>$r['version'],'local_version'=>$r['mapping']['local_version'],'acf'=>$values,'override'=>$overrides); }
wp_set_current_user(1);
$sites=array();foreach(get_sites(array('number'=>0)) as $site){if(in_array($site->path,array('/site-a/','/site-b/','/site-c/'),true)){$sites[]=(int)$site->blog_id;}}
[$a,$b,$c]=$sites;
$defaults=Schema::defaults();
catalog_check(isset($defaults['msc_movie'],$defaults['msc_show'],$defaults['msc_episode']),'Three distinct built-in content types are available');
foreach(array('premiere_date','finale_date','status','network','number_of_seasons','number_of_episodes') as $field){catalog_check(isset($defaults['msc_show']['fields'][$field])&&!isset($defaults['msc_movie']['fields'][$field]),'TV-only field is excluded from Movies: '.$field);}
foreach(array('release_date','digital_release_date','streaming_release_date','physical_release_date','runtime','director','writers','cast','distributor','production_companies','content_rating') as $field){catalog_check(isset($defaults['msc_movie']['fields'][$field])&&!isset($defaults['msc_show']['fields'][$field]),'Movie-only field is excluded from TV Shows: '.$field);}
catalog_check('msc_show'===$defaults['msc_episode']['fields']['parent_show']['target'],'Episodes remain related only to TV Shows');

// Emulate an already configured 1.1 network with records and site overrides.
$saved=Schema::all();$version=get_network_option(get_current_network_id(),'msc_builtin_schema_version');
$old=$saved;unset($old['msc_movie']);$old['msc_show']['label']='My TV Catalog';$old['msc_show']['fields']['custom_note']=array('type'=>'text','label'=>'Existing custom note');$old['msc_show']['fields']['network']['override']=false;
$old['msc_show']['fields']['custom_description']=$old['msc_show']['fields']['content'];unset($old['msc_show']['fields']['content']);
$seed=catalog_at($a,static fn()=>Plugin::store()->create('msc_show',array('title'=>'Upgrade preservation fixture','content'=>'Keep this description','network'=>'Original network')));$sample=(int)$seed['id'];catalog_drain();
catalog_at($b,static function()use($sample){$r=Plugin::store()->resolved($sample);Plugin::store()->local($sample,array('seo_title'=>'Keep local SEO'),array(),(int)$r['mapping']['local_version']);});
$before=Plugin::store()->get($sample);$maps=array();$overrides=array();foreach($sites as $site){$maps[$site]=Plugin::store()->mapping($sample,$site);$overrides[$site]=Plugin::store()->overrides($sample,$site);}
$participation=get_blog_option($c,'msc_types',array());
try {
	update_blog_option($c,'msc_types',array_values(array_diff($participation,array('msc_movie'))));
	update_network_option(get_current_network_id(),'msc_schemas',$old);update_network_option(get_current_network_id(),'msc_builtin_schema_version',1);
	$block_write=static fn($next,$previous)=>$previous;add_filter('pre_update_site_option_msc_schemas',$block_write,10,2);
	try{catalog_reject(static fn()=>Schema::upgrade_defaults(),'A failed schema write cannot be marked as a successful upgrade');}finally{remove_filter('pre_update_site_option_msc_schemas',$block_write,10);}
	catalog_check(1===(int)get_network_option(get_current_network_id(),'msc_builtin_schema_version')&&$old===Schema::all(),'Failed upgrades retain the original schemas and remain retryable');
	Schema::upgrade_defaults();$upgraded=Schema::all();
	catalog_check(isset($upgraded['msc_movie']),'Existing 1.1 schemas gain Movies without resetting network configuration');
	catalog_check('My TV Catalog'===$upgraded['msc_show']['label']&&isset($upgraded['msc_show']['fields']['custom_note'])&&false===$upgraded['msc_show']['fields']['network']['override'],'Upgrade retains custom labels, fields and override policy');
	catalog_check(isset($upgraded['msc_show']['fields']['custom_description'])&&!isset($upgraded['msc_show']['fields']['content']),'Upgrade preserves native-property aliases without adding a duplicate body field');
	Schema::save($upgraded);catalog_check($upgraded===Schema::all(),'Upgraded custom schemas still pass complete schema validation');
	catalog_check(!Schema::enabled('msc_movie',$c),'Upgrade does not silently enroll a site in Movies');
	catalog_check($before===Plugin::store()->get($sample),'Upgrade preserves global UUID, values and revision');
	foreach($sites as $site){catalog_check($maps[$site]===Plugin::store()->mapping($sample,$site)&&$overrides[$site]===Plugin::store()->overrides($sample,$site),'Upgrade preserves local post IDs and overrides on site '.$site);}
	Schema::upgrade_defaults();catalog_check($upgraded===Schema::all(),'Built-in schema upgrade is repeatable');
} finally {update_network_option(get_current_network_id(),'msc_schemas',$saved);update_network_option(get_current_network_id(),'msc_builtin_schema_version',$version);update_blog_option($c,'msc_types',$participation);}
Acf::register();
$movie=catalog_at($a,static fn()=>Plugin::store()->create('msc_movie',array('title'=>'Movie Field Integration','release_date'=>'2099-06-01','digital_release_date'=>'2099-07-01','runtime'=>113,'director'=>'Fixture Director','distributor'=>'Fixture Pictures','cast'=>'<p>Actor as lead</p>')));$id=(int)$movie['id'];catalog_drain();
foreach($sites as $site){catalog_at($site,static function()use($id){$r=Plugin::store()->resolved($id);Plugin::store()->local($id,array(),array(),(int)$r['mapping']['local_version'],'publish');});}
catalog_check(post_type_exists('msc_movie'),'Movies register as native WordPress posts');
catalog_at($a,static function()use($id){catalog_check('2099-06-01'===get_field('msc_release_date',msc_local_post_id($id)),'Movie ACF date resolves independently from TV premiere dates');});
catalog_reject(static fn()=>catalog_at($a,static fn()=>Plugin::store()->update($id,array('number_of_seasons'=>3),(int)Plugin::store()->get($id)['version'])),'Movie records reject TV season fields');
catalog_reject(static fn()=>catalog_at($a,static fn()=>Plugin::store()->update($sample,array('director'=>'Wrong type'),(int)Plugin::store()->get($sample)['version'])),'TV records reject Movie-only fields');
catalog_reject(static fn()=>catalog_at($a,static fn()=>Plugin::store()->create('msc_episode',array('title'=>'Wrong parent','parent_show'=>$movie['uuid']))),'An Episode cannot use a Movie as its parent Show');
catalog_reject(static fn()=>catalog_at($a,static fn()=>Acf::save(catalog_form($id,'global',array(Acf::key('msc_show','premiere_date')=>'20990101')))),'ACF rejects fields submitted from another content type');
catalog_at($c,static fn()=>Acf::save(catalog_form($id,'local',array(Acf::key('msc_movie','release_date')=>'20990801'),array('release_date'=>1))));
catalog_at($b,static fn()=>Acf::save(catalog_form($id,'global',array(Acf::key('msc_movie','release_date')=>'20990901',Acf::key('msc_movie','director')=>'Updated Film Director'))));catalog_drain();
catalog_check('2099-09-01'===msc_get_field($id,'release_date',$a)&&'2099-09-01'===msc_get_field($id,'release_date',$b)&&'2099-08-01'===msc_get_field($id,'release_date',$c),'Movie updates propagate while preserving one site release-date override');
catalog_at($c,static fn()=>Acf::save(catalog_form($id,'local',array(Acf::key('msc_movie','release_date')=>''))));
catalog_check('2099-09-01'===msc_get_field($id,'release_date',$c)&&!msc_is_overridden($id,'release_date',$c),'Movie reset inherits the newest shared release date');
catalog_at($a,static function()use($id){catalog_check(in_array($id,msc_query('msc_movie',array('release_date'=>array('operator'=>'>=','value'=>'2099-09-01'))),true),'Movie dates use the indexed current-site query API');});
catalog_check(true===Schema::get('msc_movie')['noindex'],'Movies default to the network noindex policy');
// Run this file with --url=Site A so WordPress initializes its rewrite context.
catalog_at($a,static function()use($id){$url=get_permalink(msc_local_post_id($id));$base=getenv('WORDPRESS_URL')?:'http://localhost:8080';$host=wp_parse_url($base,PHP_URL_HOST);$port=wp_parse_url($base,PHP_URL_PORT)?:80;if(!in_array($host,array('localhost','127.0.0.1'),true)){throw new RuntimeException('HTTP test is local only.');}$ch=curl_init($url);curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_CONNECT_TO=>array($host.':'.$port.':wordpress:80'),CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>20));$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);catalog_check(200===$status&&str_contains(strtolower((string)$body),'x-robots-tag: noindex'),'Published Movie permalink renders with noindex over HTTP');});
WP_CLI::success($GLOBALS['msc_catalog_checks'].' TV/Movie catalog checks passed.');
