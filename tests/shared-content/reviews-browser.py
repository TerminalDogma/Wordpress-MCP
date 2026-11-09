"""Editorial precedence, branded verdicts, medals, score forms and mobile layouts."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
from urllib.parse import parse_qs, urlparse
from playwright.async_api import async_playwright, expect

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1': raise SystemExit('Disposable local installation required.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'): raise SystemExit('Local installation required.')
log = Path(os.environ['MSC_REVIEW_FIXTURE_LOG']).read_text() if os.environ.get('MSC_REVIEW_FIXTURE_LOG') else subprocess.check_output(['sh','bin/shared-content.sh','wp','--url='+base+'/site-a/','eval-file','/msc-tests/reviews.php'],cwd=root,text=True)
data = json.loads(next(line.split(':', 1)[1] for line in log.splitlines() if line.startswith('MSC_REVIEW_FIXTURE:')))
for line in log.splitlines():
    if line.startswith('Success:'): print(line, flush=True)

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, executable_path=os.environ.get('MSC_BROWSER_EXECUTABLE'), args=['--no-sandbox'])
        context = await browser.new_context(viewport={'width':1440,'height':1000}, reduced_motion='reduce')
        preview_score = 84
        async def route_request(route):
            if urlparse(route.request.url).hostname not in ('localhost','127.0.0.1'): await route.abort(); return
            body = parse_qs(route.request.post_data or '')
            if body.get('action') == ['msc_score_preview']:
                assert body.get('nonce') and body.get('record') == [data['movie']['uuid']]
                await route.fulfill(json={'success':True,'data':{'values':{'rotten_tomatoes_score':90,'metacritic_score':preview_score,'metacritic_url':'https://www.metacritic.com/movie/fixture/','score_updated_at':'2099-01-01T12:00:00Z'},'matches':['OMDb: Matched Movie Fixture'],'warnings':[]}}); return
            await route.continue_()
        await context.route('**/*', route_request)
        page = await context.new_page()
        for key, award, providers in [('movie','gold',['Rotten Tomatoes','Metacritic']),('site_b','copper',['Rotten Tomatoes','Metacritic']),('game','silver',['Metacritic','OpenCritic']),('site_d','gold',['Metacritic','OpenCritic']),('show','gold',['Rotten Tomatoes','Metacritic'])]:
            assert (await page.goto(data[key]['url'])).status == 200
            badge = page.locator('.hero-art > .item-score-badge')
            await expect(badge).to_have_class('item-score-badge award-'+award)
            cards = page.locator('.review-score-card')
            await expect(cards).to_have_count(3)
            await expect(cards.nth(0)).to_have_class('review-score-card editorial-score award-'+award)
            for index, name in enumerate(providers, 1): await expect(cards.nth(index).locator('h3')).to_contain_text(name)
            image_box, badge_box = await page.locator('.hero-art').bounding_box(), await badge.bounding_box()
            assert badge_box['x'] >= image_box['x'] and badge_box['y'] >= image_box['y']
            assert badge_box['x']+badge_box['width'] <= image_box['x']+image_box['width']+1
            await page.goto(data[key]['review'])
            await expect(page.locator('.review-verdict h2')).to_have_text('Final Verdict')
            await expect(page.locator('.review-pros')).to_contain_text('Strong performances')
            await expect(page.locator('.review-cons')).to_contain_text('Uneven pacing')
            await expect(page.locator('.review-featured-image > .item-score-badge')).to_have_class('item-score-badge award-'+award)
        print('PASS: Movie, TV and game pages on all four sites show local editorial scores first and correct medal colors; linked reviews include verdicts, positives and negatives.',flush=True)
        await page.goto(data['game']['review']); await page.screenshot(path='/tmp/msc-review-desktop.png',full_page=True)
        await page.set_viewport_size({'width':390,'height':844})
        assert await page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
        await page.screenshot(path='/tmp/msc-review-mobile.png',full_page=True)
        await page.goto(data['movie']['url'])
        assert await page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
        await expect(page.locator('.editorial-score')).to_be_visible()
        await page.set_viewport_size({'width':1440,'height':1000})
        await page.goto(data['movie']['archive'])
        await expect(page.locator('a[href="'+data['movie']['review']+'"]').first).to_be_visible()
        assert await page.locator('.review-archive-card .excerpt').count() == 0
        await page.goto(base+'/site-c/games/')
        await expect(page.locator('a[href="'+data['game']['url']+'"] .item-score-badge').first).to_be_visible()
        print('PASS: Review archives omit excerpts, catalog cards carry image scores, and review/item layouts fit mobile.',flush=True)
        await page.goto(base+'/wp-login.php'); await page.fill('#user_login',config['WORDPRESS_ADMIN_USER']); await page.fill('#user_pass',config['WORDPRESS_ADMIN_PASSWORD']); await page.click('#wp-submit'); await page.wait_for_url('**/wp-admin/**')
        bad = await context.request.post(base+'/site-a/wp-admin/admin-ajax.php',form={'action':'msc_score_preview','record':data['movie']['uuid']})
        assert bad.status == 403
        for editor in ('acf','full'):
            await page.goto(data['movie'][editor]); await page.get_by_role('button',name='Fetch aggregate scores',exact=True).click()
            await expect(page.locator('.msc-score-import [role="status"]')).to_contain_text('Matched Movie Fixture')
            await page.locator('[data-score="rotten_tomatoes_score"]').uncheck()
            await page.get_by_role('button',name='Use selected scores',exact=True).click()
            def field(key): return page.locator('.acf-field[data-name="msc_'+key+'"] input, [name="fields['+key+']"]')
            await expect(field('rotten_tomatoes_score')).to_have_value('98')
            await expect(field('metacritic_score')).to_have_value(str(preview_score))
            if editor == 'acf':
                async with page.expect_navigation(wait_until='domcontentloaded'): await page.get_by_role('button',name='Save shared fields globally',exact=True).click()
                await page.goto(data['movie']['url']); await expect(page.locator('.review-score-card').nth(2).locator('.review-score-value')).to_contain_text('84')
                await expect(page.locator('.hero-art .item-score-badge strong')).to_contain_text('9.5')
                preview_score = 83
        await page.goto(data['movie']['edit'])
        welcome = page.get_by_role('dialog',name='Welcome to the editor')
        if await welcome.count(): await welcome.get_by_role('button',name='Close',exact=True).click()
        score = page.locator('.acf-field[data-name="_msc_review_score"] input')
        await expect(score).to_have_value('9.5'); await expect(score).to_have_attribute('step','0.5')
        await expect(page.locator('.acf-field[data-name="_msc_review_item"]')).to_contain_text('Scored Movie')
        print('PASS: Native Review editor exposes linked items and half-point scores. Both shared editors preview/select aggregates; global ACF saves persist selected values while preserving editorial precedence. Missing nonces fail.',flush=True)
        await browser.close()

asyncio.run(main())
