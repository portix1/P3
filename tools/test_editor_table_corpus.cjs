const {setup,assert,fs}=require('./editor_test_setup.cjs');
(async()=>{
 const rows=JSON.parse(fs.readFileSync(process.argv[2]||'runtime/table-corpus.json','utf8'));
 function shape(w,html){const t=w.document.createElement('template');t.innerHTML=html;return Array.from(t.content.querySelectorAll('table')).map(table=>Array.from(table.rows).map(row=>Array.from(row.cells).map(cell=>({tag:cell.tagName,span:cell.colSpan+'/'+cell.rowSpan,scope:cell.getAttribute('scope')||(cell.tagName==='TH'?'col':''),width:cell.style.width,text:cell.textContent.replace(/\s+/g,' ').trim(),images:Array.from(cell.querySelectorAll('img')).map(x=>x.getAttribute('src')),links:Array.from(cell.querySelectorAll('a')).map(x=>x.getAttribute('href'))}))));}
 for(const [id,html] of rows){const t=await setup(html);const saved=t.save();assert.deepEqual(shape(t.w,saved),shape(t.w,html),'table content and structure article '+id);const t2=await setup(saved);assert.deepEqual(shape(t.w,t2.save()),shape(t.w,saved),'stable second opening table article '+id);t2.dom.window.close();t.dom.window.close();}
 console.log('PASS corpus: '+rows.length+' existing articles, table texts/images/links/headings/spans preserved; stable second opening');
})().catch(e=>{console.error(e);process.exitCode=1;});
