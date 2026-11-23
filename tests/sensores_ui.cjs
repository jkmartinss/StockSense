// Renderiza a página PHP usando um banco de testes e verifica a interface real.
const {chromium} = require('playwright');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
(async () => {
  const html = execFileSync(process.env.PHP_BINARY || 'php', ['-d', `session.save_path=${process.env.STOCKSENSE_TEST_SESSION_PATH || require('node:os').tmpdir()}`, '-r', 'require "includes/auth.php"; $_SESSION["usuario_id"]=1; $_SESSION["empresa_id"]=1; $_GET=["pagina"=>"sensores"]; include "index.php"; $_SESSION=[]; session_destroy();'], {cwd: process.env.STOCKSENSE_TEST_ROOT || root, encoding:'utf8'});
  const browser = await chromium.launch({channel: process.env.BROWSER_CHANNEL || 'msedge', headless:true, timeout:15000});
  try {
    const page = await browser.newPage();
    const errors=[];
    page.on('pageerror',e=>errors.push(e.message));
    await page.route('**/*',async route=>{
      const url=new URL(route.request().url());
      if(url.hostname!=='stocksense.test')return route.abort();
      if(url.pathname==='/')return route.fulfill({contentType:'text/html; charset=utf-8',body:html});
      if(url.pathname==='/actions/baixar_config_sensor.php')return route.fulfill({status:200,headers:{'Content-Type':'application/octet-stream','Content-Disposition':'attachment; filename="config.h"'},body:'#pragma once\n// teste\n'});
      const file=path.join(root,url.pathname.slice(1));
      if(file.startsWith(root+path.sep)&&fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
      return route.abort();
    });
    await page.goto('http://stocksense.test/');
    assert(await page.getByRole('heading',{name:'Sensores',exact:true}).isVisible());
    assert(await page.getByRole('link',{name:'Galpão Maquete / MAQ-01',exact:true}).isVisible());
    assert.equal(await page.getByText('Online',{exact:true}).count(),0,'Dados SQL simulados não devem indicar placa online.');
    assert.equal(await page.getByText('Sensor demonstrativo Nortec D1',{exact:true}).count(),0,'Não mostrar sensores da outra empresa.');
    const download=page.waitForEvent('download');
    await page.getByRole('button',{name:'Baixar config.h',exact:true}).first().click();
    assert.equal((await download).suggestedFilename(),'config.h');
    assert.equal(await page.getByText('Não foi possível confirmar o resultado.',{exact:false}).count(),0,'O download deve usar navegação normal.');
    for(const width of [360,768,1280]){
      await page.setViewportSize({width,height:900});
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Sem rolagem horizontal da página.');
    }
    assert.deepEqual(errors,[]);
    console.log('OK: Sensores, MAQ-01, isolamento, estado inicial offline, download e larguras 360/768/1280.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
