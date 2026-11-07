"""Google Images links and URL imports in full/ACF editors, galleries and inheritance."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
import traceback
from urllib.parse import urlparse, parse_qs
from playwright.async_api import async_playwright, expect

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1': raise SystemExit('Disposable local installation required.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'): raise SystemExit('Local installation required.')
def wp(code):
    return json.loads(subprocess.check_output(['docker','compose','exec','-T','--user','33','wordpress','wp','--url=' + base + '/site-a/','eval',code], cwd=root, text=True))

data = wp(r'''wp_set_current_user(1);$tag=substr(wp_generate_uuid4(),0,8);$s=\MSC\Plugin::store();$r=$s->create('msc_movie',['title'=>'Image Search '.$tag,'content'=>'<p>Image import fixture.</p>']);$p=$s->create('msc_person',['title'=>'Image Person '.$tag]);$sites=[];foreach(get_sites(['number'=>0]) as $site){if(in_array($site->path,['/site-a/','/site-b/'],true)){$sites[$site->path]=(int)$site->blog_id;}}foreach($sites as $id){\MSC\Plugin::materializer()->sync((int)$r['id'],$id);switch_to_blog($id);try{$m=$s->mapping((int)$r['id']);$s->local($r['uuid'],[],[],(int)$m['local_version'],'publish');}finally{restore_current_blog();}}\MSC\Plugin::materializer()->sync((int)$p['id'],$sites['/site-a/']);echo wp_json_encode(['record'=>$r['uuid'],'person'=>$p['uuid'],'title'=>$r['data']['title'],'a'=>$sites['/site-a/'],'b'=>$sites['/site-b/'],'public'=>get_permalink(msc_local_post_id($r['uuid']))]);''')

async def main():
    async with async_playwright() as p:
        options = {'headless':True,'args':['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'): options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context(viewport={'width':1440,'height':1000}, reduced_motion='reduce')
        async def route(request):
            host = urlparse(request.request.url).hostname
            if host in ('localhost','127.0.0.1'): await request.continue_()
            elif host == 'www.google.com': await request.fulfill(status=200,content_type='text/html',body='<h1>Google Images link fixture</h1>')
            else: await request.abort()
        await context.route('**/*',route)
        page = await context.new_page(); errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
        await page.goto(base + '/wp-login.php'); await page.fill('#user_login',config['WORDPRESS_ADMIN_USER']); await page.fill('#user_pass',config['WORDPRESS_ADMIN_PASSWORD']); await page.click('#wp-submit'); await page.wait_for_url('**/wp-admin/**')
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc&record=' + data['record'])
        poster = page.locator('#msc-poster').locator('..'); panel = poster.locator('.msc-web-image')
        await panel.locator('summary').first.click()
        await expect(panel.locator('.msc-image-query')).to_have_value(data['title'] + ' poster')
        await panel.locator('.msc-image-query').fill(data['title'] + ' official poster')
        async with page.expect_popup() as popup_info: await panel.locator('.msc-search-google').click()
        popup = await popup_info.value; await popup.wait_for_load_state()
        assert parse_qs(urlparse(popup.url).query) == {'tbm':['isch'],'q':[data['title'] + ' official poster']}
        await popup.close()
        await panel.locator('.msc-image-url').fill('https://93.184.216.34/msc-url-fixture-image.png?tag=' + data['record'])
        await panel.locator('.msc-image-source summary').click()
        await panel.locator('.msc-image-source-url').fill('https://93.184.216.34/image-source')
        await panel.locator('.msc-image-credit-input').fill('Fixture photographer')
        await panel.locator('.msc-import-image-url').click()
        await expect(panel.locator('.msc-image-import-status')).to_contain_text('Image added')
        await expect(poster.locator('.msc-media-previews img')).to_be_visible()
        assert await poster.locator('.msc-media-previews img').evaluate('(el)=>el.complete && el.naturalWidth>0')
        inherited = json.loads(await poster.locator('.msc-media-value').input_value()); assert inherited['site_id'] == data['a']
        await page.get_by_role('button',name='Save global record',exact=True).click(); await page.wait_for_url('**mode=global')
        state = wp(r'$r=\MSC\Plugin::store()->resolved("' + data['record'] + '",' + str(data['b']) + ');echo wp_json_encode($r["data"]["poster"]);')
        assert state == inherited
        await page.goto(data['public']); assert 'Fixture photographer' in await page.locator('#msc-source-footer').inner_html()
        assert not await page.locator('main .msc-image-credit').count()
        print('PASS: Google Images title search, real local poster import/preview, global propagation and footer credit.',flush=True)

        await page.goto(base + '/site-b/wp-admin/admin.php?page=msc-acf&record=' + data['record'] + '&scope=local')
        poster = page.locator('.acf-field[data-name=msc_poster]'); panel = poster.locator('.msc-web-image')
        await panel.locator('summary').first.click()
        await panel.locator('.msc-image-url').fill('https://93.184.216.34/msc-url-fixture-image.png?local=' + data['record'])
        await panel.locator('.msc-import-image-url').click(); await expect(panel.locator('.msc-image-import-status')).to_contain_text('Image added')
        local = json.loads(await poster.locator('.msc-media-value').input_value()); assert local['site_id'] == data['b']
        assert await poster.locator('input[name="override[poster]"]').is_checked()
        gallery = page.locator('.acf-field[data-name=msc_promotional_images]'); gallery_panel = gallery.locator('.msc-web-image')
        await gallery_panel.locator('summary').first.click()
        for i in range(2):
            await gallery_panel.locator('.msc-image-url').fill('https://93.184.216.34/msc-url-fixture-image.png?gallery=' + str(i) + data['record'])
            await gallery_panel.locator('.msc-import-image-url').click()
            await expect(gallery_panel.locator('.msc-image-url')).to_have_value('')
        assert len(json.loads(await gallery.locator('.msc-media-value').input_value())) == 2
        assert await gallery.locator('.msc-media-previews img').count() == 2
        await page.get_by_role('button',name='Save local overrides',exact=True).click(); await page.wait_for_url('**updated=1')
        state = wp(r'$s=\MSC\Plugin::store();echo wp_json_encode(["global"=>$s->get("' + data['record'] + '")["data"]["poster"],"local"=>$s->resolved("' + data['record'] + '",' + str(data['b']) + ')["data"]]);')
        assert state['global'] == inherited and state['local']['poster'] == local and len(state['local']['promotional_images']) == 2
        await poster.locator('input[name="override[poster]"]').uncheck()
        await page.get_by_role('button',name='Save local overrides',exact=True).click(); await page.wait_for_url('**updated=1')
        assert json.loads(await poster.locator('.msc-media-value').input_value()) == inherited
        panel = poster.locator('.msc-web-image'); await panel.locator('summary').first.click()
        await panel.locator('.msc-image-url').fill('https://127.0.0.1/private.png')
        await panel.locator('.msc-import-image-url').click()
        await expect(panel.locator('.msc-image-import-status')).to_contain_text('public HTTPS')
        assert json.loads(await poster.locator('.msc-media-value').input_value()) == inherited
        response = await context.request.post(base + '/site-b/wp-admin/admin-ajax.php', form={'action':'msc_import_image_url','nonce':'invalid','url':'https://93.184.216.34/msc-url-fixture-image.png'})
        assert response.status == 403
        await page.screenshot(path='/tmp/msc-google-image-picker.png',full_page=True)
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-acf&record=' + data['person'])
        headshot = page.locator('.acf-field[data-name=msc_headshot] .msc-web-image')
        await headshot.locator('summary').first.click(); assert 'headshot' in await headshot.locator('.msc-image-query').input_value()
        assert not errors, errors
        await browser.close()
        print('PASS: ACF image import, local ownership/override/reset, appended galleries, headshot search, failed URL preservation and CSRF.',flush=True)

try: asyncio.run(main())
except Exception:
    report = traceback.format_exc()
    for key in ('WORDPRESS_ADMIN_PASSWORD','OMDB_API_KEY','IGDB_CLIENT_SECRET'):
        if config.get(key): report = report.replace(config[key],'[redacted]')
    raise SystemExit(report)
