<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f5f7f5">
  <title>Meu Financeiro</title>
  <link rel="stylesheet" href="assets/styles.css">
  <script defer src="assets/app.js"></script>
</head>
<body>
  <div id="toast" class="toast" role="status" aria-live="polite"></div>
  <main id="auth-screen" class="auth-screen hidden">
    <section class="auth-card">
      <div class="brand-mark">R$</div>
      <p class="eyebrow">CONTROLE FINANCEIRO PESSOAL</p>
      <h1 id="auth-title">Seu dinheiro, mais organizado.</h1>
      <p id="auth-description" class="muted">Entre para acompanhar suas finanças.</p>
      <form id="auth-form" class="stack">
        <label>Usuário<input id="auth-username" name="username" autocomplete="username" minlength="3" maxlength="40" required></label>
        <label>Senha<input id="auth-password" name="password" type="password" autocomplete="current-password" minlength="10" required></label>
        <p id="auth-hint" class="form-hint"></p>
        <button class="btn primary full" type="submit" id="auth-submit">Entrar</button>
      </form>
      <p class="tiny muted">Seus dados financeiros são privados. Crie uma senha exclusiva e guarde seus backups fora da hospedagem.</p>
    </section>
  </main>

  <div id="app-shell" class="app-shell hidden">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="#" data-page="dashboard"><span class="brand-mark small">R$</span><span><strong>Meu Financeiro</strong><small>CONTROLE PESSOAL</small></span></a>
      <nav class="nav-list" aria-label="Navegação principal">
        <button class="nav-item active" data-page="dashboard"><span>▦</span> Visão geral</button>
        <button class="nav-item" data-page="transactions"><span>⇄</span> Lançamentos</button>
        <button class="nav-item" data-page="budgets"><span>◷</span> Orçamentos</button>
        <button class="nav-item" data-page="goals"><span>◎</span> Metas de economia</button>
        <button class="nav-item" data-page="reports"><span>▤</span> Relatórios</button>
        <button class="nav-item" data-page="settings"><span>⚙</span> Configurações</button>
      </nav>
      <div class="sidebar-bottom">
        <div class="privacy-note"><span class="privacy-dot"></span><span><strong>Área pessoal</strong><small>Seus dados protegidos por login</small></span></div>
        <button id="logout-button" class="nav-item subtle"><span>↪</span> Sair da conta</button>
      </div>
    </aside>

    <div class="main-column">
      <header class="topbar">
        <button id="mobile-menu" class="icon-button mobile-only" aria-label="Abrir menu">☰</button>
        <div><p class="eyebrow" id="today-label">SEU RESUMO FINANCEIRO</p><h1 id="page-title">Visão geral</h1></div>
        <div class="topbar-actions">
          <label class="month-control"><span class="sr-only">Mês do resumo</span><input type="month" id="global-month"></label>
          <button class="icon-button" id="theme-toggle" title="Alternar tema" aria-label="Alternar tema">☾</button>
          <div class="user-chip"><span class="avatar">●</span><span id="current-user">Usuário</span></div>
        </div>
      </header>

      <div id="page-dashboard" class="page-content">
        <section class="welcome-row">
          <div><p class="eyebrow">ACOMPANHAMENTO MENSAL</p><h2>Cuide bem do seu dinheiro.</h2><p class="muted">Uma visão clara para tomar decisões com tranquilidade.</p></div>
          <button class="btn primary" data-action="new-transaction">＋ Novo lançamento</button>
        </section>
        <section class="summary-grid">
          <article class="summary-card income-card"><div class="card-top"><span>Entradas do mês</span><span class="metric-icon">↙</span></div><strong id="sum-income">R$ 0,00</strong><small>Dinheiro que entrou</small></article>
          <article class="summary-card expense-card"><div class="card-top"><span>Saídas do mês</span><span class="metric-icon">↗</span></div><strong id="sum-expense">R$ 0,00</strong><small>Dinheiro que saiu</small></article>
          <article class="summary-card balance-card"><div class="card-top"><span>Saldo do mês</span><span class="metric-icon">＝</span></div><strong id="sum-balance">R$ 0,00</strong><small>Entradas menos saídas</small></article>
          <article class="summary-card goal-card"><div class="card-top"><span>Metas de economia</span><span class="metric-icon">◎</span></div><strong id="sum-goals">0%</strong><small id="sum-goals-caption">Progresso geral</small></article>
        </section>

        <section class="dashboard-grid">
          <article class="panel chart-panel">
            <div class="panel-heading"><div><h3>Despesas por categoria</h3><p class="muted">Como suas saídas se distribuem neste mês</p></div><span class="panel-icon">◔</span></div>
            <div class="chart-layout"><div class="donut-wrap"><canvas id="category-chart" width="240" height="240" aria-label="Gráfico de despesas por categoria"></canvas><div class="donut-center"><strong id="chart-total">R$ 0</strong><small>em saídas</small></div></div><div id="category-legend" class="legend"></div></div>
          </article>
          <article class="panel">
            <div class="panel-heading"><div><h3>Metas em andamento</h3><p class="muted">Pequenos passos, grandes conquistas</p></div><button class="text-button" data-page="goals">Ver todas ↗</button></div>
            <div id="dashboard-goals" class="goal-list"><div class="empty-state">Suas metas aparecerão aqui.</div></div>
          </article>
        </section>

        <section class="panel">
          <div class="panel-heading"><div><h3>Lançamentos recentes</h3><p class="muted">As últimas movimentações do mês selecionado</p></div><button class="text-button" data-page="transactions">Ver histórico ↗</button></div>
          <div id="recent-transactions" class="transaction-list"></div>
        </section>
      </div>

      <div id="page-transactions" class="page-content hidden">
        <section class="page-intro"><div><h2>Seus lançamentos</h2><p class="muted">Registre e acompanhe cada entrada e saída.</p></div><button class="btn primary" data-action="new-transaction">＋ Adicionar lançamento</button></section>
        <section class="panel">
          <form id="transaction-form" class="transaction-form hidden">
            <div class="form-title"><div><h3 id="transaction-form-title">Novo lançamento</h3><p class="muted">Preencha os dados abaixo.</p></div><button type="button" class="icon-button" data-action="cancel-transaction" aria-label="Fechar formulário">×</button></div>
            <input type="hidden" id="transaction-id">
            <div class="form-grid">
              <label class="span-2">Descrição<input id="transaction-description" maxlength="180" placeholder="Ex.: Mercado da semana" required></label>
              <label>Valor (R$)<input id="transaction-amount" type="number" min="0.01" max="999999999.99" step="0.01" placeholder="0,00" required></label>
              <label>Tipo<select id="transaction-kind"><option value="expense">Saída</option><option value="income">Entrada</option></select></label>
              <label>Categoria<select id="transaction-category"></select></label>
              <label>Data<input id="transaction-date" type="date" required></label>
              <label>Forma de pagamento<select id="transaction-payment"><option value="">Não informado</option><option>Pix</option><option>Dinheiro</option><option>Débito</option><option>Crédito</option><option>Transferência</option><option>Boleto</option><option>Outro</option></select></label>
              <label class="span-2">Observações (opcional)<input id="transaction-notes" maxlength="1000" placeholder="Detalhes adicionais"></label>
            </div>
            <div class="form-actions"><button type="button" class="btn secondary" data-action="cancel-transaction">Cancelar</button><button class="btn primary" type="submit">Salvar lançamento</button></div>
          </form>
          <div class="filter-row">
            <label>Mês<input type="month" id="filter-month"></label>
            <label>Categoria<select id="filter-category"><option value="">Todas as categorias</option></select></label>
            <button class="btn secondary" id="clear-filters" type="button">Limpar filtros</button>
          </div>
          <div id="transactions-table" class="transaction-list"></div>
        </section>
      </div>

      <div id="page-budgets" class="page-content hidden">
        <section class="page-intro"><div><h2>Orçamentos mensais</h2><p class="muted">Defina limites para acompanhar suas despesas por categoria.</p></div></section>
        <section class="panel">
          <form id="budget-form" class="inline-form">
            <label>Categoria de despesa<select id="budget-category" required></select></label>
            <label>Mês<input id="budget-month" type="month" required></label>
            <label>Limite (R$)<input id="budget-limit" type="number" min="0.01" step="0.01" required placeholder="0,00"></label>
            <button class="btn primary" type="submit">Salvar limite</button>
          </form>
          <div id="budgets-list" class="budget-list"></div>
        </section>
      </div>

      <div id="page-goals" class="page-content hidden">
        <section class="page-intro"><div><h2>Metas de economia</h2><p class="muted">Planeje o que quer conquistar e acompanhe seu progresso.</p></div></section>
        <section class="panel">
          <form id="goal-form" class="goal-form">
            <h3>Nova meta</h3>
            <div class="form-grid">
              <label class="span-2">Nome da meta<input id="goal-name" maxlength="100" required placeholder="Ex.: Reserva de emergência"></label>
              <label>Valor desejado (R$)<input id="goal-target" type="number" min="0.01" step="0.01" required placeholder="10000,00"></label>
              <label>Já economizado (R$)<input id="goal-saved" type="number" min="0" step="0.01" value="0" required></label>
              <label>Data desejada (opcional)<input id="goal-date" type="date"></label>
              <label class="span-2">Observações<input id="goal-notes" maxlength="1000" placeholder="Como pretende alcançar essa meta?"></label>
            </div>
            <div class="form-actions"><button class="btn primary" type="submit">＋ Criar meta</button></div>
          </form>
          <div id="goals-list" class="goal-list large"></div>
        </section>
      </div>

      <div id="page-reports" class="page-content hidden">
        <section class="page-intro"><div><h2>Relatórios</h2><p class="muted">Veja os totais do período e exporte seus lançamentos.</p></div><button class="btn secondary" id="csv-export">↓ Exportar CSV</button></section>
        <section class="summary-grid compact">
          <article class="summary-card income-card"><span>Entradas no período</span><strong id="report-income">R$ 0,00</strong></article>
          <article class="summary-card expense-card"><span>Saídas no período</span><strong id="report-expense">R$ 0,00</strong></article>
          <article class="summary-card balance-card"><span>Resultado do período</span><strong id="report-balance">R$ 0,00</strong></article>
        </section>
        <section class="panel"><div class="panel-heading"><div><h3>Resumo por categoria</h3><p class="muted">Despesas do mês selecionado</p></div></div><div id="report-categories" class="report-category-list"></div></section>
      </div>

      <div id="page-settings" class="page-content hidden">
        <section class="page-intro"><div><h2>Configurações</h2><p class="muted">Personalize sua experiência e cuide dos seus dados.</p></div></section>
        <section class="settings-grid">
          <article class="panel settings-card"><div class="settings-heading"><span class="settings-icon">◐</span><div><h3>Aparência</h3><p class="muted">Escolha como o sistema aparece.</p></div></div><label>Tema<select id="theme-select"><option value="light">Claro</option><option value="dark">Escuro</option><option value="system">Seguir dispositivo</option></select></label></article>
          <article class="panel settings-card"><div class="settings-heading"><span class="settings-icon">◈</span><div><h3>Categorias</h3><p class="muted">Crie categorias para organizar os lançamentos.</p></div></div><form id="category-form" class="stack compact-stack"><label>Nome<input id="category-name" maxlength="60" required placeholder="Ex.: Pets"></label><div class="form-grid"><label>Tipo<select id="category-kind"><option value="expense">Saída</option><option value="income">Entrada</option><option value="both">Ambos</option></select></label><label>Cor<input id="category-color" type="color" value="#64748b"></label></div><button class="btn secondary" type="submit">Adicionar categoria</button></form><div id="categories-settings" class="category-manage-list"></div></article>
          <article class="panel settings-card"><div class="settings-heading"><span class="settings-icon">⬇</span><div><h3>Backup e restauração</h3><p class="muted">Mantenha uma cópia fora da hospedagem.</p></div></div><div class="stack"><button class="btn primary" id="backup-export">Baixar backup completo (JSON)</button><button class="btn secondary" id="csv-export-settings">Exportar lançamentos (CSV)</button><label class="file-picker">Restaurar de um backup JSON<input type="file" id="restore-file" accept="application/json,.json"></label><p class="form-hint">Restaurar substitui os dados atuais da conta. Faça um backup antes e só importe arquivos confiáveis.</p></div></article>
          <article class="panel settings-card"><div class="settings-heading"><span class="settings-icon">♙</span><div><h3>Sua conta</h3><p class="muted">Informações de acesso.</p></div></div><p class="settings-value">Usuário atual <strong id="settings-username">—</strong></p><p class="form-hint">A criação pública de contas fica desativada após a primeira configuração.</p><button class="btn secondary" id="settings-logout">Sair da conta</button></article>
        </section>
      </div>

      <footer class="app-footer"><span>Meu Financeiro</span><span>Seus dados merecem cuidado. Faça backups regularmente.</span></footer>
    </div>
  </div>
  <div id="confirm-modal" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="modal-card"><h3 id="confirm-title">Confirmar ação</h3><p id="confirm-message"></p><div class="form-actions"><button class="btn secondary" id="confirm-cancel">Cancelar</button><button class="btn danger" id="confirm-ok">Confirmar</button></div></div>
  </div>
</body>
</html>
