// Valida o botao na pagina PHP real, com respostas do sensor simuladas.
const {chromium} = require('playwright');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
(async () => {
  const html = execFileSync(process.env.PHP_BINARY || 'php', ['-d', `session.save_path=${process.env.STOCKSENSE_TEST_SESSION_PATH || require('node:os').tmpdir()}`, '-r', 'require "includes/auth.php"; $_SESSION["usuario_id"]=1; $_SESSION["empresa_id"]=1; $_GET=["pagina"=>"cadastrar_ocupacao","prateleira_id"=>12]; include "index.php"; $_SESSION=[]; session_destroy();'], {cwd: process.env.STOCKSENSE_TEST_ROOT || root, encoding: 'utf8'});
  assert(html.includes('data-medir-ocupacao'), 'O cadastro deve mostrar o botão do sensor');
  const browser = await chromium.launch({channel: process.env.BROWSER_CHANNEL || 'msedge', headless: true, timeout: 15000});
  try {
    const page = await browser.newPage();
    let estado = 'concluido', posts = 0;
    await page.route('http://stocksense.test/**', async route => {
      const url = new URL(route.request().url());
      if (url.pathname.endsWith('/api/medicao.php')) {
        const post = route.request().method() === 'POST';
        if (post) posts++;
        return route.fulfill({json: post ? {ok: true, pedido_id: 123} : {ok: true, estado, prateleira_id: 12, capacidade_m3: 0.0124752, volume_registrado_m3: 0, distancia_cm: 13.1, percentual_ocupado: 50, erro: 'Sensor sem retorno.'}});
      }
      if (url.pathname === '/') return route.fulfill({contentType: 'text/html', body: html});
      const file = path.join(root, url.pathname);
      if (file.startsWith(root + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) return route.fulfill({path: file});
      return route.abort();
    });
    await page.goto('http://stocksense.test/');
    const button = page.locator('[data-medir-ocupacao]');
    await button.click();
    assert(await button.isDisabled(), 'Bloquear cliques enquanto aguarda');
    await page.waitForFunction(() => document.querySelector('[data-medicao-ocupacao-status]').textContent.includes('Volume novo estimado'));
    assert.equal(await page.locator('#volume_m3').inputValue(), '0.0062376');
    assert(!(await button.isDisabled()));
    estado = 'erro';
    await button.click();
    await page.waitForFunction(() => document.querySelector('[data-medicao-ocupacao-status]').textContent.includes('Sensor sem retorno'));
    estado = 'expirado';
    await button.click();
    await page.waitForFunction(() => document.querySelector('[data-medicao-ocupacao-status]').textContent.includes('não respondeu'));
    assert.equal(posts, 3);
    for (const width of [360, 1280]) {
      await page.setViewportSize({width, height: 900});
      assert(await button.isVisible());
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Sem rolagem horizontal');
    }
    console.log('OK: botao, espera, resultado, falha, expiracao e larguras 360/1280.');
  } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
