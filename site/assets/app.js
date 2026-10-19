(() => {
  'use strict';
  const $ = (s, root=document) => root.querySelector(s);
  const $$ = (s, root=document) => [...root.querySelectorAll(s)];
  const state = { csrf:'', username:'', setup:false, page:'dashboard', month:(()=>{const d=new Date();return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`})(), categories:[], transactions:[], summary:null, goals:[], budgets:[], theme:'light' };
  const categoryColors = ['#f97316','#ef4444','#eab308','#8b5cf6','#06b6d4','#3b82f6','#ec4899','#64748b','#16a34a'];
  const money = cents => (Number(cents||0)/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
  const amount = n => Number(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const localDate = (date=new Date()) => {
    const y=date.getFullYear(), m=String(date.getMonth()+1).padStart(2,'0'), d=String(date.getDate()).padStart(2,'0');
    return `${y}-${m}-${d}`;
  };
  const today = () => localDate();
  const monthNow = () => localDate().slice(0,7);
  const fmtDate = s => s ? new Date(s+'T12:00:00').toLocaleDateString('pt-BR') : '—';
  const categoryName = id => state.categories.find(c=>Number(c.id)===Number(id))?.name || 'Sem categoria';
  const categoryColor = id => state.categories.find(c=>Number(c.id)===Number(id))?.color || '#64748b';

  async function api(action, options={}) {
    const method = options.method || 'GET';
    const url = 'api.php?action='+encodeURIComponent(action)+(options.query ? '&'+new URLSearchParams(options.query) : '');
    const headers = {};
    if (method !== 'GET') {
      headers['Content-Type']='application/json';
      headers['X-CSRF-Token']=state.csrf;
    }
    const response = await fetch(url,{method,headers,body:options.body ? JSON.stringify(options.body) : undefined,credentials:'same-origin'});
    const contentType=response.headers.get('content-type')||'';
    if (options.download) {
      if (!response.ok) { const d=contentType.includes('json')?await response.json():{}; throw new Error(d.error||'Não foi possível exportar o arquivo.'); }
      const blob=await response.blob(), a=document.createElement('a');
      a.href=URL.createObjectURL(blob); a.download=decodeURIComponent((response.headers.get('content-disposition')||'').split('filename=')[1]?.replaceAll('"','')||'backup.json');
      document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(a.href); return true;
    }
    const data=contentType.includes('json')?await response.json():{};
    if (!response.ok) throw new Error(data.error||'Não foi possível concluir a operação.');
    return data;
  }
  let toastTimer;
  function toast(message,type='success') {
    const el=$('#toast'); el.textContent=message; el.className='toast show'+(type==='error'?' error':'');
    clearTimeout(toastTimer); toastTimer=setTimeout(()=>el.className='toast',3600);
  }
  function confirmAction(message, title='Confirmar ação') {
    return new Promise(resolve=>{
      $('#confirm-title').textContent=title; $('#confirm-message').textContent=message; $('#confirm-modal').classList.remove('hidden');
      const done=value=>{ $('#confirm-modal').classList.add('hidden'); $('#confirm-ok').removeEventListener('click',yes); $('#confirm-cancel').removeEventListener('click',no); resolve(value); };
      const yes=()=>done(true), no=()=>done(false);
      $('#confirm-ok').addEventListener('click',yes); $('#confirm-cancel').addEventListener('click',no);
    });
  }
  function applyTheme(theme) {
    state.theme=theme;
    const resolved=theme==='system'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):theme;
    document.documentElement.dataset.theme=resolved;
    $('#theme-select').value=theme;
    $('#theme-toggle').textContent=resolved==='dark'?'☀':'☾';
    try{localStorage.setItem('mf-theme',theme)}catch(e){}
  }
  function authView(setup) {
    state.setup=setup;
    $('#app-shell').classList.add('hidden'); $('#auth-screen').classList.remove('hidden');
    $('#auth-title').textContent=setup?'Vamos configurar seu financeiro.':'Seu dinheiro, mais organizado.';
    $('#auth-description').textContent=setup?'Crie a conta privada de administrador. Esta tela será desativada depois.':'Entre para acompanhar suas finanças.';
    $('#auth-hint').textContent=setup?'Use um usuário de 3 a 40 caracteres e uma senha com pelo menos 10 caracteres. Não haverá cadastro público.':'';
    $('#auth-submit').textContent=setup?'Criar conta e continuar':'Entrar';
    $('#auth-password').autocomplete=setup?'new-password':'current-password';
  }
  async function boot() {
    applyTheme((()=>{try{return localStorage.getItem('mf-theme')||'light'}catch(e){return 'light'}})());
    try {
      const status=await api('status'); state.csrf=status.csrf;
      if(status.setup_required){authView(true);return}
      if(!status.authenticated){authView(false);return}
      state.username=status.username||'Usuário'; enterApp();
    } catch(e) {
      authView(false); toast('Não foi possível iniciar. Confira se o PHP tem PDO SQLite habilitado e se a pasta privada permite gravação.','error');
    }
  }
  function enterApp() {
    $('#auth-screen').classList.add('hidden'); $('#app-shell').classList.remove('hidden');
    $('#current-user').textContent=state.username; $('#settings-username').textContent=state.username;
    $('#global-month').value=state.month; $('#filter-month').value=state.month; $('#budget-month').value=state.month;
    $('#today-label').textContent=new Date().toLocaleDateString('pt-BR',{weekday:'long',day:'numeric',month:'long'}).toUpperCase();
    showPage(state.page); refreshAll();
  }
  async function refreshAll() {
    try {
      const [cats,summary,transactions,goals,budgets]=await Promise.all([
        api('categories'),api('summary',{query:{month:state.month}}),
        api('transactions',{query:{month:$('#filter-month').value||'',category:$('#filter-category').value||''}}),
        api('goals'),api('budgets')
      ]);
      state.categories=cats.categories||[]; state.summary=summary; state.transactions=transactions.transactions||[];
      state.goals=goals.goals||[]; state.budgets=budgets.budgets||[];
      populateCategories(); renderDashboard(); renderTransactions(); renderGoals(); renderBudgets(); renderReports(); renderCategorySettings();
    } catch(e) {
      if(/login|sessão/i.test(e.message)){authView(false);return}
      toast(e.message,'error');
    }
  }
  function showPage(page) {
    state.page=page;
    $$('.page-content').forEach(el=>el.classList.add('hidden'));
    $('#page-'+page)?.classList.remove('hidden');
    $$('.nav-item[data-page]').forEach(el=>el.classList.toggle('active',el.dataset.page===page));
    const titles={dashboard:'Visão geral',transactions:'Lançamentos',budgets:'Orçamentos',goals:'Metas de economia',reports:'Relatórios',settings:'Configurações'};
    $('#page-title').textContent=titles[page]||'Meu Financeiro';
    $('#sidebar').classList.remove('open');
    if(page==='transactions') renderTransactions();
  }
  function populateCategories() {
    const options=state.categories.map(c=>`<option value="${c.id}">${esc(c.name)}</option>`).join('');
    const expenseOptions=state.categories.filter(c=>['expense','both'].includes(c.kind)).map(c=>`<option value="${c.id}">${esc(c.name)}</option>`).join('');
    const oldCat=$('#transaction-category').value, oldFilter=$('#filter-category').value, oldBudget=$('#budget-category').value;
    $('#transaction-category').innerHTML='<option value="">Sem categoria</option>'+options;
    $('#filter-category').innerHTML='<option value="">Todas as categorias</option>'+options;
    $('#budget-category').innerHTML=expenseOptions||'<option value="">Cadastre uma categoria de saída</option>';
    if(oldCat)$('#transaction-category').value=oldCat;
    if(oldFilter)$('#filter-category').value=oldFilter;
    if(oldBudget)$('#budget-category').value=oldBudget;
  }
  function renderDashboard() {
    const s=state.summary||{income:0,expense:0,balance:0,categories:[],recent:[],goals:[]};
    $('#sum-income').textContent=money(s.income); $('#sum-expense').textContent=money(s.expense); $('#sum-balance').textContent=money(s.balance);
    const goalTarget=state.goals.reduce((n,g)=>n+Number(g.target_cents),0), goalSaved=state.goals.reduce((n,g)=>n+Number(g.saved_cents),0);
    const progress=goalTarget?Math.min(100,Math.round(goalSaved/goalTarget*100)):0;
    $('#sum-goals').textContent=progress+'%'; $('#sum-goals-caption').textContent=state.goals.length+' meta(s) cadastrada(s)';
    $('#chart-total').textContent=money(s.expense).replace(',00','');
    drawDonut(s.categories||[]);
    const goals=state.goals.slice(0,3);
    $('#dashboard-goals').innerHTML=goals.length?goals.map(goalHtml).join(''):'<div class="empty-state">Você ainda não criou uma meta. Adicione uma na seção Metas de economia.</div>';
    const recent=s.recent||[];
    $('#recent-transactions').innerHTML=recent.length?recent.map(transactionHtml).join(''):'<div class="empty-state">Nenhum lançamento neste mês. Adicione sua primeira entrada ou saída.</div>';
  }
  function drawDonut(cats) {
    const canvas=$('#category-chart'), ctx=canvas.getContext('2d');
    const ratio=window.devicePixelRatio||1, size=Math.min(canvas.parentElement.clientWidth||220,240);
    canvas.width=size*ratio;canvas.height=size*ratio;canvas.style.width=size+'px';canvas.style.height=size+'px';ctx.scale(ratio,ratio);
    const total=cats.reduce((sum,c)=>sum+Number(c.total),0), center=size/2, radius=size*.43, thickness=size*.12;
    ctx.clearRect(0,0,size,size);
    if(!total){ctx.beginPath();ctx.arc(center,center,radius,0,Math.PI*2);ctx.strokeStyle=getComputedStyle(document.documentElement).getPropertyValue('--line');ctx.lineWidth=thickness;ctx.stroke();}
    else {
      let start=-Math.PI/2;
      cats.filter(c=>Number(c.total)>0).forEach((c,i)=>{
        const sweep=Number(c.total)/total*Math.PI*2;
        ctx.beginPath();ctx.arc(center,center,radius,start,start+sweep);ctx.strokeStyle=c.color||categoryColors[i%categoryColors.length];ctx.lineWidth=thickness;ctx.lineCap='butt';ctx.stroke();start+=sweep;
      });
    }
    $('#category-legend').innerHTML=cats.filter(c=>Number(c.total)>0).slice(0,8).map(c=>`<div class="legend-item"><i class="legend-dot" style="background:${safeColor(c.color)}"></i><span>${esc(c.name)}</span><strong>${money(c.total)}</strong></div>`).join('')||'<div class="empty-state">Sem despesas registradas no mês.</div>';
  }
  function safeColor(c){return /^#[0-9a-fA-F]{6}$/.test(c||'')?c:'#64748b'}
  function transactionHtml(t, actions=true) {
    const income=t.kind==='income', sign=income?'+':'−', symbol=income?'↙':'↗';
    return `<div class="transaction-row"><div class="transaction-icon" style="color:${safeColor(t.category_color||categoryColor(t.category_id))}">${symbol}</div><div class="transaction-main"><strong>${esc(t.description)}</strong><small>${esc(t.category_name||'Sem categoria')} · ${fmtDate(t.transaction_date)}${t.payment_method?' · '+esc(t.payment_method):''}</small></div><div class="transaction-amount ${income?'income':'expense'}">${sign} ${money(t.amount_cents)}</div>${actions?`<div class="row-actions"><button class="mini-button" data-edit-transaction="${t.id}">Editar</button><button class="mini-button" data-delete-transaction="${t.id}">Excluir</button></div>`:''}</div>`;
  }
  function renderTransactions() {
    const rows=state.transactions||[];
    $('#transactions-table').innerHTML=rows.length?rows.map(t=>transactionHtml(t,true)).join(''):'<div class="empty-state">Nenhum lançamento encontrado com esses filtros.</div>';
  }
  function goalHtml(g, withActions=false) {
    const target=Number(g.target_cents), saved=Number(g.saved_cents), pct=Math.min(100,Math.round(saved/target*100));
    return `<div class="goal-card-row"><div class="goal-title-line"><strong>${esc(g.name)}</strong><span>${pct}%</span></div><div class="progress-track"><div class="progress-fill" style="width:${pct}%"></div></div><div class="goal-meta"><span>${money(saved)} de ${money(target)}</span><span>${g.target_date?'Até '+fmtDate(g.target_date):pct===100?'Concluída':'Em andamento'}</span></div>${g.notes?`<p class="form-hint" style="margin-top:8px">${esc(g.notes)}</p>`:''}${withActions?`<div class="goal-card-actions"><button class="mini-button" data-delete-goal="${g.id}">Excluir meta</button></div>`:''}</div>`;
  }
  function renderGoals() {
    $('#goals-list').innerHTML=state.goals.length?state.goals.map(g=>goalHtml(g,true)).join(''):'<div class="empty-state">Nenhuma meta cadastrada ainda.</div>';
  }
  function renderBudgets() {
    const month=state.month;
    const list=state.budgets.filter(b=>b.month===month);
    $('#budgets-list').innerHTML=list.length?list.map(b=>{
      const spent=(state.summary?.categories||[]).find(c=>Number(c.id)===Number(b.category_id));
      const used=Number(spent?.total||0), limit=Number(b.limit_cents), pct=Math.round(used/limit*100), over=used>limit;
      return `<div class="budget-row"><div class="budget-top"><strong><i class="legend-dot" style="display:inline-block;background:${safeColor(b.category_color)};margin-right:7px"></i>${esc(b.category_name)}</strong><span>${esc(b.month)}</span></div><div class="progress-track"><div class="progress-fill" style="width:${Math.min(100,pct)}%;background:${over?'var(--red)':'var(--green)'}"></div></div><div class="budget-foot"><span>${money(used)} usados de ${money(limit)} · ${pct}%</span><button class="mini-button" data-delete-budget="${b.id}">Excluir</button></div>${over?'<p class="budget-status over">Você ultrapassou o limite deste orçamento.</p>':''}</div>`;
    }).join(''):'<div class="empty-state">Nenhum orçamento para este mês. Cadastre um limite acima.</div>';
  }
  function renderReports() {
    const s=state.summary||{income:0,expense:0,balance:0,categories:[]};
    $('#report-income').textContent=money(s.income);$('#report-expense').textContent=money(s.expense);$('#report-balance').textContent=money(s.balance);
    const cats=(s.categories||[]).filter(c=>Number(c.total)>0), max=Math.max(1,...cats.map(c=>Number(c.total)));
    $('#report-categories').innerHTML=cats.length?cats.map(c=>`<div class="report-category"><span>${esc(c.name)}</span><div class="report-bar"><span style="width:${Number(c.total)/max*100}%;background:${safeColor(c.color)}"></span></div><strong>${money(c.total)}</strong></div>`).join(''):'<div class="empty-state">Sem despesas neste mês.</div>';
  }
  function renderCategorySettings() {
    $('#categories-settings').innerHTML=state.categories.map(c=>`<div class="category-manage-row"><span class="category-name-wrap"><i class="category-swatch" style="background:${safeColor(c.color)}"></i>${esc(c.name)} <small class="muted">(${c.kind==='income'?'entrada':c.kind==='expense'?'saída':'ambos'})</small></span><button class="mini-button" data-delete-category="${c.id}">Excluir</button></div>`).join('');
  }
  function showTransactionForm(t=null) {
    const form=$('#transaction-form');form.classList.remove('hidden');
    $('#transaction-form-title').textContent=t?'Editar lançamento':'Novo lançamento';
    $('#transaction-id').value=t?.id||'';
    $('#transaction-description').value=t?.description||'';
    $('#transaction-amount').value=t?(Number(t.amount_cents)/100).toFixed(2):'';
    $('#transaction-kind').value=t?.kind||'expense';
    $('#transaction-date').value=t?.transaction_date||today();
    $('#transaction-payment').value=t?.payment_method||'';
    $('#transaction-notes').value=t?.notes||'';
    $('#transaction-category').value=t?.category_id||'';
    form.scrollIntoView({behavior:'smooth',block:'start'});
    $('#transaction-description').focus({preventScroll:true});
  }
  async function deleteTransaction(id) {
    if(!await confirmAction('Este lançamento será removido permanentemente. Deseja continuar?','Excluir lançamento'))return;
    try{await api('delete_transaction',{method:'POST',body:{id:Number(id)}});toast('Lançamento excluído.');await refreshAll()}catch(e){toast(e.message,'error')}
  }
  async function exportFile(action) {
    try{await api(action,{download:true});toast(action==='backup'?'Backup baixado. Guarde-o fora da hospedagem.':'Arquivo CSV exportado.')}catch(e){toast(e.message,'error')}
  }

  $('#auth-form').addEventListener('submit',async e=>{
    e.preventDefault();
    const username=$('#auth-username').value.trim(),password=$('#auth-password').value;
    $('#auth-submit').disabled=true;
    try{
      await api(state.setup?'setup':'login',{method:'POST',body:{username,password}});
      const status=await api('status');state.csrf=status.csrf;state.username=status.username||username;
      toast(state.setup?'Conta criada com sucesso.':'Login realizado.');enterApp();
    }catch(err){toast(err.message,'error')}
    finally{$('#auth-submit').disabled=false}
  });
  $$('.nav-item[data-page], [data-page]').forEach(el=>el.addEventListener('click',e=>{e.preventDefault();if(el.dataset.page)showPage(el.dataset.page)}));
  $('#mobile-menu').addEventListener('click',()=>$('#sidebar').classList.toggle('open'));
  $('#global-month').addEventListener('change',async e=>{state.month=e.target.value||monthNow();$('#filter-month').value=state.month;$('#budget-month').value=state.month;await refreshAll()});
  $('#filter-month').addEventListener('change',async()=>{const r=await api('transactions',{query:{month:$('#filter-month').value||'',category:$('#filter-category').value||''}});state.transactions=r.transactions;renderTransactions()});
  $('#filter-category').addEventListener('change',async()=>{const r=await api('transactions',{query:{month:$('#filter-month').value||'',category:$('#filter-category').value||''}});state.transactions=r.transactions;renderTransactions()});
  $('#clear-filters').addEventListener('click',async()=>{$('#filter-month').value='';$('#filter-category').value='';const r=await api('transactions');state.transactions=r.transactions;renderTransactions()});
  $$('[data-action="new-transaction"]').forEach(b=>b.addEventListener('click',()=>{showPage('transactions');showTransactionForm()}));
  $$('[data-action="cancel-transaction"]').forEach(b=>b.addEventListener('click',()=>$('#transaction-form').classList.add('hidden')));
  $('#transaction-form').addEventListener('submit',async e=>{
    e.preventDefault();
    const body={id:Number($('#transaction-id').value)||undefined,description:$('#transaction-description').value.trim(),amount:$('#transaction-amount').value,kind:$('#transaction-kind').value,category_id:Number($('#transaction-category').value)||null,transaction_date:$('#transaction-date').value,payment_method:$('#transaction-payment').value,notes:$('#transaction-notes').value};
    try{await api('save_transaction',{method:'POST',body});$('#transaction-form').classList.add('hidden');toast('Lançamento salvo.');await refreshAll()}catch(err){toast(err.message,'error')}
  });
  document.addEventListener('click',async e=>{
    const edit=e.target.closest('[data-edit-transaction]');if(edit){const id=Number(edit.dataset.editTransaction);const t=state.transactions.find(x=>Number(x.id)===id)||state.summary?.recent?.find(x=>Number(x.id)===id);if(t){showPage('transactions');showTransactionForm(t)}else{const r=await api('transactions');const found=r.transactions.find(x=>Number(x.id)===id);if(found){state.transactions=r.transactions;showPage('transactions');showTransactionForm(found)}}}
    const del=e.target.closest('[data-delete-transaction]');if(del)deleteTransaction(del.dataset.deleteTransaction);
    const dg=e.target.closest('[data-delete-goal]');if(dg&&await confirmAction('Deseja excluir esta meta?','Excluir meta')){try{await api('delete_goal',{method:'POST',body:{id:Number(dg.dataset.deleteGoal)}});toast('Meta excluída.');await refreshAll()}catch(err){toast(err.message,'error')}}
    const db=e.target.closest('[data-delete-budget]');if(db&&await confirmAction('Deseja excluir este orçamento?','Excluir orçamento')){try{await api('delete_budget',{method:'POST',body:{id:Number(db.dataset.deleteBudget)}});toast('Orçamento excluído.');await refreshAll()}catch(err){toast(err.message,'error')}}
    const dc=e.target.closest('[data-delete-category]');if(dc&&await confirmAction('Deseja excluir esta categoria? Lançamentos existentes impedem a exclusão.','Excluir categoria')){try{await api('delete_category',{method:'POST',body:{id:Number(dc.dataset.deleteCategory)}});toast('Categoria excluída.');await refreshAll()}catch(err){toast(err.message,'error')}}
  });
  $('#goal-form').addEventListener('submit',async e=>{
    e.preventDefault();try{await api('save_goal',{method:'POST',body:{name:$('#goal-name').value.trim(),target:$('#goal-target').value,saved:$('#goal-saved').value,target_date:$('#goal-date').value,notes:$('#goal-notes').value}});e.target.reset();$('#goal-saved').value='0';toast('Meta criada.');await refreshAll()}catch(err){toast(err.message,'error')}
  });
  $('#budget-form').addEventListener('submit',async e=>{
    e.preventDefault();try{await api('save_budget',{method:'POST',body:{category_id:Number($('#budget-category').value),month:$('#budget-month').value,limit:$('#budget-limit').value}});$('#budget-limit').value='';toast('Orçamento salvo.');await refreshAll()}catch(err){toast(err.message,'error')}
  });
  $('#category-form').addEventListener('submit',async e=>{
    e.preventDefault();try{await api('save_category',{method:'POST',body:{name:$('#category-name').value.trim(),kind:$('#category-kind').value,color:$('#category-color').value}});e.target.reset();$('#category-color').value='#64748b';toast('Categoria adicionada.');await refreshAll()}catch(err){toast(err.message,'error')}
  });
  $('#theme-toggle').addEventListener('click',()=>applyTheme(state.theme==='dark'?'light':'dark'));
  $('#theme-select').addEventListener('change',e=>applyTheme(e.target.value));
  $('#backup-export').addEventListener('click',()=>exportFile('backup'));
  $('#csv-export').addEventListener('click',()=>exportFile('export_csv'));
  $('#csv-export-settings').addEventListener('click',()=>exportFile('export_csv'));
  $('#restore-file').addEventListener('change',async e=>{
    const file=e.target.files?.[0];if(!file)return;
    if(file.size>20*1024*1024){toast('O arquivo é muito grande. O limite de importação é 20 MB.','error');e.target.value='';return}
    try{
      const text=await file.text(),data=JSON.parse(text);
      if(!await confirmAction('A restauração substituirá categorias, lançamentos, metas e orçamentos atuais desta conta. Faça um backup antes. Continuar?','Restaurar backup')){e.target.value='';return}
      await api('restore',{method:'POST',body:data});toast('Backup restaurado com sucesso.');await refreshAll();
    }catch(err){toast(err.message==='Unexpected end of JSON input'?'O arquivo JSON está incompleto ou inválido.':err.message,'error')}
    finally{e.target.value=''}
  });
  async function logout(){
    if(!await confirmAction('Deseja sair da sua conta?','Sair'))return;
    try{await api('logout',{method:'POST'});state.username='';authView(false);toast('Você saiu da conta.')}catch(e){toast(e.message,'error')}
  }
  $('#logout-button').addEventListener('click',logout);$('#settings-logout').addEventListener('click',logout);
  window.addEventListener('resize',()=>{if(!$('#page-dashboard').classList.contains('hidden')&&state.summary)drawDonut(state.summary.categories||[])});
  boot();
})();