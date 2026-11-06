// Run after tests/integration/learning.php against its disposable test database.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.TEST_BASE_URL?.replace(/\/+$/, '');

if (!base) {
    throw new Error(
        'Set TEST_BASE_URL to the local *_test database server.'
    );
}

async function status(response, expected) {
    assert.ok(response, 'No HTTP response received.');

    const actual = response.status();

    if (actual !== expected) {
        let body;

        try {
            body = await response.text();
        } catch {
            body = '[Could not read response body]';
        }

        assert.equal(
            actual,
            expected,
            `${response.url()}: ${body}`
        );
    }

    return response;
}

async function boot(page) {
    return JSON.parse(
        await page.locator('#lu-data').textContent()
    );
}

function noAnswerKeys(value) {
    const json = JSON.stringify(value);

    assert(!json.includes('correct_option_ids'));
    assert(!json.includes('accepted_answers'));
}

function silentWav() {
    const samples = 800;
    const buffer = Buffer.alloc(44 + samples * 2);

    buffer.write('RIFF', 0);
    buffer.writeUInt32LE(buffer.length - 8, 4);
    buffer.write('WAVE', 8);
    buffer.write('fmt ', 12);
    buffer.writeUInt32LE(16, 16);
    buffer.writeUInt16LE(1, 20);
    buffer.writeUInt16LE(1, 22);
    buffer.writeUInt32LE(8000, 24);
    buffer.writeUInt32LE(16000, 28);
    buffer.writeUInt16LE(2, 32);
    buffer.writeUInt16LE(16, 34);
    buffer.write('data', 36);
    buffer.writeUInt32LE(samples * 2, 40);

    return buffer;
}

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,
        args: ['--no-sandbox']
    });

    try {
        // Guest map is public; account session APIs remain protected.
        const guest = await browser.newContext();
        const guestPage = await guest.newPage();

        await status(
            await guestPage.goto(base + '/learning?completed=4'),
            200
        );

        assert.equal((await boot(guestPage)).guest, true);

        assert.equal(
            await guestPage.locator('.ld-lesson').count(),
            3
        );

        assert.match(
            await guestPage.locator('.ld-heading-badge').textContent(),
            /0\s*\/\s*3/
        );

        assert.equal(
            await guestPage.locator(
                '.ld-lesson [data-locked]'
            ).count(),
            2
        );

        const trialLinks = await guestPage
            .locator('.ld-lesson a')
            .evaluateAll(links =>
                links.map(link => new URL(link.href).pathname)
            );

        assert.deepEqual(
            trialLinks,
            ['/learning/lesson/1']
        );

        await status(
            await guest.request.get(
                base + '/learning/lesson/2?focus=words'
            ),
            403
        );

        await status(
            await guest.request.get(
                base + '/api/v1/learning/sessions/1'
            ),
            401
        );

        await status(
            await guest.request.post(
                base + '/api/v1/learning/lessons/1/sessions',
                {data: {}}
            ),
            401
        );

        await status(
            await guest.request.post(
                base + '/api/v1/learning/sessions/1/answers',
                {data: {}}
            ),
            401
        );

        await status(
            await guest.request.post(
                base + '/api/v1/learning/guest/claim',
                {data: {}}
            ),
            401
        );

        console.log(
            'PASS: public trial map, forged completion ignored, '
            + 'guest limit and protected account APIs'
        );

        const context = await browser.newContext();

        const login = await context.request.post(
            base + '/api/v1/auth/login',
            {
                data: {
                    email: 'a@example.test',
                    password: 'test-password-123'
                }
            }
        );

        await status(login, 200);

        assert.match(
            login.headers()['set-cookie'] || '',
            /doulingo_session=.*HttpOnly/i
        );

        await status(
            await guest.request.post(
                base + '/api/v1/auth/login',
                {
                    data: {
                        email: 'b@example.test',
                        password: 'test-password-123'
                    }
                }
            ),
            403
        );

        await context.route(
            '**/assets/images/learning-test.svg',
            route => route.fulfill({
                contentType: 'image/svg+xml',
                body:
                    '<svg xmlns="http://www.w3.org/2000/svg" '
                    + 'width="100" height="100">'
                    + '<rect width="100" height="100" fill="blue"/>'
                    + '</svg>'
            })
        );

        await context.route(
            '**/assets/audio/learning-test.wav',
            route => route.fulfill({
                contentType: 'audio/wav',
                body: silentWav()
            })
        );

        const page = await context.newPage();
        const jsErrors = [];

        page.on('pageerror', error => {
            jsErrors.push(error.message);
        });

        // Integration fixtures have three lessons;
        // lessons 1 and 3 are completed.
        await status(
            await page.goto(base + '/learning?completed=4'),
            200
        );

        assert.equal(
            await page.locator('.ld-lesson').count(),
            3
        );

        assert.match(
            await page.locator('.ld-heading-badge').textContent(),
            /2\s*\/\s*3/
        );

        const hrefs = await page
            .locator('.ld-lesson a')
            .evaluateAll(links =>
                links.map(link =>
                    new URL(link.href).pathname
                    + new URL(link.href).search
                )
            );

        assert.deepEqual(
            hrefs.sort(),
            [
                '/learning/lesson/1?focus=result',
                '/learning/lesson/2?focus=practice',
                '/learning/lesson/3?focus=result'
            ].sort()
        );

        await status(
            await page.goto(base + '/learning/explore'),
            200
        );

        assert.equal(
            (await page.locator('h1').textContent()).trim(),
            'Khám phá từ mới'
        );

        assert.equal(
            await page.locator('#lu-form').count(),
            0
        );

        assert.equal(
            new URL(page.url()).pathname,
            '/learning/explore'
        );

        await status(
            await page.goto(base + '/learning/practice'),
            200
        );

        assert.equal(
            (await page.locator('h1').textContent()).trim(),
            'Luyện tập'
        );

        assert.equal(
            new URL(page.url()).pathname,
            '/learning/practice'
        );

        console.log(
            'PASS: server progress, completed-result links '
            + 'and separate discovery/practice pages'
        );

        // Completed lessons default to results.
        // Explicit words focus opens their vocabulary.
        await status(
            await page.goto(
                base + '/learning/lesson/1?focus=words'
            ),
            200
        );

        const xss =
            '</script><img src=x onerror="window.__learningXss=1">';

        await page.waitForFunction(
            expected =>
                document.getElementById('lu-example-en')
                    ?.textContent === expected,
            xss
        );

        assert.equal(
            await page.locator('#lu-example-en').textContent(),
            xss
        );

        assert.equal(
            await page.locator('#lu-example-en img').count(),
            0
        );

        assert.equal(
            await page.evaluate(() => window.__learningXss),
            undefined
        );

        await page.locator('#lu-next-word').click();

        assert.equal(
            await page.locator('#lu-example-en').textContent(),
            ''
        );

        assert.equal(
            await page.locator('#lu-example').isVisible(),
            false
        );

        assert.equal(
            await page.locator('#lu-example-vi').textContent(),
            ''
        );

        const data = await boot(page);

        assert.equal(data.lessonId, 1);
        assert.equal(typeof data.csrf, 'string');
        assert(data.csrf.length > 0);

        await status(
            await context.request.post(
                base + '/api/v1/learning/lessons/1/sessions',
                {data: {}}
            ),
            403
        );

        const started = await status(
            await context.request.post(
                base + '/api/v1/learning/lessons/1/sessions',
                {
                    headers: {
                        'X-CSRF-Token': data.csrf
                    },
                    data: {}
                }
            ),
            200
        );

        const state = (await started.json()).data;

        assert.equal(state.lesson_id, 1);
        noAnswerKeys(state);

        await page.reload();

        assert.equal(
            (await boot(page)).sessionId,
            state.id
        );

        await page.locator('#lu-start').click();

        await page.locator('#lu-question').waitFor({
            state: 'visible'
        });

        await page.waitForFunction(() =>
            /\d\s*\/\s*3/.test(
                document.getElementById('lu-hearts')
                    ?.textContent || ''
            )
        );

        const resumed = await status(
            await context.request.get(
                base + '/api/v1/learning/sessions/' + state.id
            ),
            200
        );

        assert.equal(
            (await resumed.json()).data.id,
            state.id
        );

        console.log(
            'PASS: XSS escaping, stale example cleared, '
            + 'CSRF and session resume'
        );

        // Dedicated practice explicitly starts/resumes an attempt,
        // including a new attempt for a completed lesson.
        const startMedia = page.waitForResponse(response =>
            response.request().method() === 'POST'
            && new URL(response.url()).pathname
                === '/api/v1/learning/lessons/3/sessions'
        );

        await status(
            await page.goto(
                base + '/learning/practice?lesson=3'
            ),
            200
        );

        const mediaResponse = await status(
            await startMedia,
            200
        );

        let mediaState = (await mediaResponse.json()).data;

        assert.equal(mediaState.lesson_id, 3);
        noAnswerKeys(mediaState);

        const seen = new Set();

        for (let i = 0; i < 3; i++) {
            assert.equal(mediaState.status, 'in_progress');

            seen.add(mediaState.question.type);

            await page.locator('#lu-form').waitFor({
                state: 'visible'
            });

            if (mediaState.question.type === 'choose_image') {
                await page.waitForFunction(() => {
                    const images = [
                        ...document.querySelectorAll(
                            '#lu-controls img'
                        )
                    ];

                    return images.length > 0
                        && images.every(image =>
                            image.complete
                            && image.naturalWidth > 0
                        );
                });
            }

            if (mediaState.question.content.audio) {
                await page.waitForFunction(() =>
                    document.getElementById('lu-audio')
                        .readyState >= 2
                );
            }

            if (mediaState.question.content.options) {
                await page.locator(
                    '#lu-controls input[type=radio][value="a"]'
                ).check();
            } else {
                await page.locator(
                    '#lu-controls input[type=text]'
                ).fill('milk');
            }

            await page.waitForFunction(() =>
                !document.getElementById('lu-submit').disabled
            );

            const answerResponse = page.waitForResponse(response =>
                response.request().method() === 'POST'
                && new URL(response.url()).pathname
                    === '/api/v1/learning/sessions/'
                        + mediaState.id
                        + '/answers'
            );

            await page.locator('#lu-submit').click();

            const answered = await status(
                await answerResponse,
                200
            );

            const result = (await answered.json()).data;

            assert.equal(result.is_correct, true);

            mediaState = result.session;
            noAnswerKeys(mediaState);

            if (mediaState.status === 'in_progress') {
                await page.locator(
                    '#lu-next-question'
                ).waitFor({
                    state: 'visible'
                });

                assert.equal(
                    await page.locator(
                        '#lu-feedback'
                    ).textContent(),
                    'Chính xác!'
                );

                await page.locator('#lu-next-question').click();
            }
        }

        assert.deepEqual(
            [...seen].sort(),
            [
                'choose_image',
                'listen_select',
                'listen_type'
            ]
        );

        assert.equal(mediaState.status, 'completed');
        assert.equal(mediaState.correct_count, 3);
        assert.equal(mediaState.xp_earned, 5);

        await page.locator('#lu-result').waitFor({
            state: 'visible'
        });

        assert.equal(
            await page.locator('#lu-result-title').textContent(),
            'Bạn làm tốt lắm!'
        );

        assert.match(
            await page.locator('#lu-score').textContent(),
            /3\/3.*\+5 XP/
        );

        assert.equal(
            await page.locator('#lu-hearts').textContent(),
            ''
        );

        await page.setViewportSize({
            width: 390,
            height: 844
        });

        assert(
            await page.evaluate(() =>
                document.documentElement.scrollWidth
                    <= window.innerWidth
            )
        );

        await status(
            await page.goto(
                base + '/learning/lesson/3?focus=result'
            ),
            200
        );

        assert.equal(
            await page.locator('#lu-hearts').count(),
            0
        );

        assert.equal(
            await page.locator('#lu-form').count(),
            0
        );

        assert.equal(
            await page.getByRole('button', {
                name: 'Kiểm tra',
                exact: true
            }).count(),
            0
        );

        assert.match(
            await page.locator('body').textContent(),
            /Câu đúng/
        );

        assert.match(
            await page.locator('body').textContent(),
            /Câu sai/
        );

        // The same authenticated session opens Reviews and loads the shared word renderer.
        const guestReviews = await guest.request.get(base + '/reviews', { maxRedirects: 0 });
        assert.equal(guestReviews.status(), 303);
        assert.match(guestReviews.headers().location, /\/login$/);
        await page.goto(base + '/reviews');
        await page.waitForFunction(() => document.getElementById('review-profile').textContent.includes('Learner A'));
        assert.equal(await page.evaluate(() => typeof window.DoulingoReviewCard), 'function');
        assert.equal(await page.locator('a[href$="/learning"]').count(), 1);
        await page.waitForFunction(() => !document.getElementById('review-app').textContent.includes('Đang tải'));
        await page.locator('[data-tab="history"]').click();
        await page.waitForFunction(() => document.getElementById('review-app').textContent.includes('Chưa có lịch sử ôn'));
        await page.locator('[data-tab="notifications"]').click();
        await page.waitForFunction(() => document.getElementById('review-app').textContent.includes('Chưa có thông báo'));
        const cardSafe = await page.evaluate(() => {
            const node = document.createElement('div');
            const value = '<img src=x onerror="window.__reviewXss=1">';
            node.innerHTML = window.DoulingoReviewCard({
                word: { english: value, vietnamese: 'nghia', example: value, image: 'javascript:alert(1)' },
                direction: 'en_vi', revealed: true,
            });
            return node.querySelector('h3').textContent === value
                && node.querySelectorAll('img,script').length === 0 && !window.__reviewXss;
        });
        assert(cardSafe, 'Shared review card escapes text and rejects unsafe media');
        assert.equal(jsErrors.length, 0, jsErrors.join('\n'));
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
        console.log('PASS: Reviews auth redirect, shared session/word renderer, history, reminders, XSS and mobile layout');

        assert.equal(
            jsErrors.length,
            0,
            jsErrors.join('\n')
        );

        console.log(
            'PASS: image/listening answers, server result, '
            + 'read-only history, no result hearts and mobile layout'
        );
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exit(1);
});
