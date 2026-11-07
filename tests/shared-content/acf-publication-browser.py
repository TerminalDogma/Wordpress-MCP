"""Publish directly from ACF while retaining field scope and site participation."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
import traceback
from urllib.parse import urlparse
from playwright.async_api import async_playwright, expect

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1': raise SystemExit('Disposable local network required.')
config = dict(line.split('=',1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost','127.0.0.1'): raise SystemExit('Local installation required.')
def wp(code):
    return json.loads(subprocess.check_output(['docker','compose','exec','-T','--user','33','wordpress','wp','--url=' + base + '/site-a/','eval',code],cwd=root,text=True))
data = wp(r'''wp_set_current_user(1);$sites=[];foreach(get_sites(['number'=>0]) as $site){if(in_array($site->path,['/site-a/','/site-b/'],true)){$sites[]=(int)$site->blog_id;}}$result=['sites'=>$sites];foreach(['local','global'] as $kind){$r=\MSC\Plugin::store()->create('msc_movie',['title'=>'ACF Publish '.$kind.' '.wp_generate_uuid4(),'runtime'=>90]);foreach($sites as $site){\MSC\Plugin::materializer()->sync((int)$r['id'],$site);}$result[$kind]=$r['uuid'];}echo wp_json_encode($result);''')
def state(uuid):
    return wp(r'$r=\MSC\Plugin::store()->get("' + uuid + r'");$out=["global"=>$r["data"]["runtime"],"sites"=>[]];foreach([' + ','.join(map(str,data['sites'])) + r'] as $site){$r=\MSC\Plugin::store()->resolved("' + uuid + r'",$site);$out["sites"][]=["status"=>$r["mapping"]["status"],"runtime"=>$r["data"]["runtime"]];}echo wp_json_encode($out);')

async def main():
    async with async_playwright() as p:
        options={'headless':True,'args':['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'): options['executable_path']=os.environ['MSC_BROWSER_EXECUTABLE']
        browser=await p.chromium.launch(**options)
        context=await browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
        async def local_only(route):
            if urlparse(route.request.url).hostname in ('localhost','127.0.0.1'): await route.continue_()
            else: await route.abort()
        await context.route('**/*',local_only)
        page=await context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        await page.goto(base+'/wp-login.php');await page.fill('#user_login',config['WORDPRESS_ADMIN_USER']);await page.fill('#user_pass',config['WORDPRESS_ADMIN_PASSWORD']);await page.click('#wp-submit');await page.wait_for_url('**/wp-admin/**')
        await page.goto(base+'/site-a/wp-admin/admin.php?page=msc-acf&record='+data['local'])
        await expect(page.locator('.msc-acf-publication-status')).to_contain_text('Draft')
        await page.locator('.acf-field[data-name=msc_runtime] input[type=number]').fill('117')
        await page.get_by_role('button',name='Save and publish on this site',exact=True).click();await page.wait_for_url('**updated=1')
        await expect(page.locator('.msc-acf-publication-status')).to_contain_text('Published')
        assert await page.locator('.msc-acf-publication-status a').count()==1
        s=state(data['local']);assert s['global']==117 and [x['status'] for x in s['sites']]==['publish','draft']
        await page.goto(base+'/site-a/wp-admin/admin.php?page=msc-acf&record='+data['global'])
        await page.locator('.acf-field[data-name=msc_runtime] input[type=number]').fill('119')
        await page.get_by_role('button',name='Save and publish globally',exact=True).click();await page.wait_for_url('**updated=1')
        s=state(data['global']);assert s['global']==119 and all(x['status']=='publish' for x in s['sites'])
        await expect(page.locator('.msc-acf-publication-status')).to_contain_text('Published')
        await page.get_by_role('link',name='Switch to local overrides',exact=True).click()
        await page.locator('.acf-field[data-name=msc_runtime] input[type=number]').fill('142')
        await page.locator('input[name="override[runtime]"]').check()
        await page.get_by_role('button',name='Save and publish globally',exact=True).click();await page.wait_for_url('**updated=1')
        s=state(data['global']);assert s['global']==119 and [x['runtime'] for x in s['sites']]==[142,119]
        await page.screenshot(path='/tmp/msc-acf-publication-buttons.png',full_page=True)
        assert not errors,errors
        await browser.close()
        print('PASS: ACF save/publish buttons, current-site status, local-only publication, global publication and retained editing scope.')

try: asyncio.run(main())
except Exception:
    report=traceback.format_exc()
    for key in ('WORDPRESS_ADMIN_PASSWORD','OMDB_API_KEY','IGDB_CLIENT_SECRET'):
        if config.get(key):report=report.replace(config[key],'[redacted]')
    raise SystemExit(report)
