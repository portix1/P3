const {setup,assert}=require('./editor_test_setup.cjs');
(async()=>{
const t=await setup('<table><caption>Comparaison</caption><thead><tr><th scope="colgroup" colspan="2">Groupe</th></tr><tr><th scope="col">A</th><th scope="col">B</th></tr></thead><tbody><tr><th scope="rowgroup" rowspan="2">Lignes</th><td style="width: 60%">Valeur</td></tr><tr><td>Autre</td></tr></tbody><tfoot><tr><td colspan="2">Fin</td></tr></tfoot></table><p>Suite</p>');
const html=t.save(),template=t.w.document.createElement('template');template.innerHTML=html;
assert.equal(template.content.querySelectorAll('thead tr').length,2);assert.equal(template.content.querySelectorAll('tfoot tr').length,1);
assert.equal(template.content.querySelector('thead th').textContent,'Groupe');assert.equal(template.content.querySelector('[scope="colgroup"]').colSpan,2);
assert.equal(template.content.querySelector('[scope="rowgroup"]').rowSpan,2);assert.equal(template.content.querySelector('td[style]').style.width,'60%');
assert(!/data-row|data-portix|contenteditable|column-resizer/.test(html));
const reopen=await setup(html);assert.equal(reopen.save(),html);
t.dom.window.close();reopen.dom.window.close();console.log('PASS table metadata: multi-row header, footer, caption, scopes, spans, widths and stable reopen');
})().catch(e=>{console.error(e);process.exitCode=1;});
