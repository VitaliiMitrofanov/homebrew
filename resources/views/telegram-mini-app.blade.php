<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Финансовая аналитика</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #000000;
            --hint-color: #999999;
            --link-color: #2481cc;
            --button-color: #2481cc;
            --button-text-color: #ffffff;
            --secondary-bg-color: #f1f1f1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding: 16px;
            padding-bottom: 100px;
            min-height: 100vh;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .header .subtitle {
            font-size: 14px;
            color: var(--hint-color);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .summary-card {
            padding: 16px;
            border-radius: 12px;
            text-align: center;
        }

        .summary-card.income {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .summary-card.expense {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
        }

        .summary-card.balance {
            background: linear-gradient(135deg, #007bff, #6f42c1);
            color: white;
        }

        .summary-card.total {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }

        .summary-card .value {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .summary-card .label {
            font-size: 12px;
            opacity: 0.9;
        }

        .section {
            background: var(--secondary-bg-color);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-container {
            position: relative;
            height: 200px;
            width: 100%;
        }

        .category-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-name {
            font-size: 14px;
            flex: 1;
        }

        .category-amount {
            font-size: 14px;
            font-weight: 600;
        }

        .category-amount.income {
            color: #28a745;
        }

        .category-amount.expense {
            color: #dc3545;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .tab {
            padding: 8px 16px;
            border-radius: 20px;
            background: var(--secondary-bg-color);
            border: none;
            font-size: 14px;
            cursor: pointer;
            white-space: nowrap;
            color: var(--text-color);
        }

        .tab.active {
            background: var(--button-color);
            color: var(--button-text-color);
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: var(--hint-color);
        }

        .error {
            text-align: center;
            padding: 20px;
            color: #dc3545;
            background: #ffeaea;
            border-radius: 8px;
        }

        .operations-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .operation-item {
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .operation-item:last-child {
            border-bottom: none;
        }

        .operation-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .operation-category {
            font-size: 14px;
            font-weight: 500;
        }

        .operation-amount {
            font-size: 14px;
            font-weight: 600;
        }

        .operation-description {
            font-size: 12px;
            color: var(--hint-color);
            margin-bottom: 2px;
        }

        .operation-date {
            font-size: 11px;
            color: var(--hint-color);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--hint-color);
        }

        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="loading" id="loading">
            <div>Загрузка данных...</div>
        </div>

        <div id="content" style="display: none;">
            <div class="header">
                <h1>Финансовая аналитика</h1>
                <div class="subtitle" id="user-greeting"></div>
            </div>

            <div class="summary-cards" id="summary-cards">
                <div class="summary-card income">
                    <div class="value" id="total-income">0 ₽</div>
                    <div class="label">Доходы</div>
                </div>
                <div class="summary-card expense">
                    <div class="value" id="total-expense">0 ₽</div>
                    <div class="label">Расходы</div>
                </div>
                <div class="summary-card balance">
                    <div class="value" id="balance">0 ₽</div>
                    <div class="label">Баланс</div>
                </div>
                <div class="summary-card total">
                    <div class="value" id="total-ops">0</div>
                    <div class="label">Операций</div>
                </div>
            </div>

            <div class="tabs" id="main-tabs">
                <button class="tab active" data-tab="categories">Категории</button>
                <button class="tab" data-tab="semantic">Умные</button>
                <button class="tab" data-tab="chart">График</button>
                <button class="tab" data-tab="operations">Операции</button>
            </div>

            <div id="tab-categories" class="tab-content">
                <div class="tabs" id="type-tabs">
                    <button class="tab active" data-type="expense">Расходы</button>
                    <button class="tab" data-type="income">Доходы</button>
                </div>
                <div class="section">
                    <div class="category-list" id="category-list"></div>
                </div>
            </div>

            <div id="tab-semantic" class="tab-content" style="display: none;">
                <div class="tabs">
                    <button class="tab active" data-semantic-type="expense">Расходы</button>
                    <button class="tab" data-semantic-type="income">Доходы</button>
                </div>
                <div class="section">
                    <div class="category-list" id="semantic-list"></div>
                </div>
            </div>

            <div id="tab-chart" class="tab-content" style="display: none;">
                <div class="section">
                    <div class="section-title">Динамика по месяцам</div>
                    <div class="chart-container">
                        <canvas id="monthly-chart"></canvas>
                    </div>
                </div>
            </div>

            <div id="tab-operations" class="tab-content" style="display: none;">
                <div class="section">
                    <div class="section-title">Последние операции</div>
                    <div class="operations-list" id="operations-list"></div>
                </div>
            </div>
        </div>

        <div id="error-container" style="display: none;">
            <div class="error" id="error-message"></div>
        </div>
    </div>

    <script>
        const tg = window.Telegram.WebApp;
        const API_BASE = '/api/telegram-mini-app';
        
        let currentCategoryType = 'expense';
        let currentSemanticType = 'expense';
        let categoryData = { income: [], expense: [] };
        let semanticData = { income: [], expense: [] };
        let monthlyChart = null;

        document.addEventListener('DOMContentLoaded', () => {
            tg.ready();
            tg.expand();

            applyTheme();
            
            const user = tg.initDataUnsafe?.user;
            if (user) {
                document.getElementById('user-greeting').textContent = 
                    `Привет, ${user.first_name}!`;
            }

            initTabs();
            loadData();

            tg.MainButton.setText('Обновить');
            tg.MainButton.show();
            tg.MainButton.onClick(() => {
                loadData();
                tg.HapticFeedback.impactOccurred('light');
            });
        });

        function applyTheme() {
            const root = document.documentElement;
            if (tg.themeParams) {
                root.style.setProperty('--bg-color', tg.themeParams.bg_color || '#ffffff');
                root.style.setProperty('--text-color', tg.themeParams.text_color || '#000000');
                root.style.setProperty('--hint-color', tg.themeParams.hint_color || '#999999');
                root.style.setProperty('--link-color', tg.themeParams.link_color || '#2481cc');
                root.style.setProperty('--button-color', tg.themeParams.button_color || '#2481cc');
                root.style.setProperty('--button-text-color', tg.themeParams.button_text_color || '#ffffff');
                root.style.setProperty('--secondary-bg-color', tg.themeParams.secondary_bg_color || '#f1f1f1');
            }
            document.body.style.backgroundColor = tg.themeParams?.bg_color || '#ffffff';
        }

        function initTabs() {
            document.querySelectorAll('#main-tabs .tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabName = tab.dataset.tab;
                    document.querySelectorAll('#main-tabs .tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
                    document.getElementById(`tab-${tabName}`).style.display = 'block';
                    tg.HapticFeedback.selectionChanged();
                });
            });

            document.querySelectorAll('#type-tabs .tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    currentCategoryType = tab.dataset.type;
                    document.querySelectorAll('#type-tabs .tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    renderCategories();
                    tg.HapticFeedback.selectionChanged();
                });
            });

            document.querySelectorAll('[data-semantic-type]').forEach(tab => {
                tab.addEventListener('click', () => {
                    currentSemanticType = tab.dataset.semanticType;
                    document.querySelectorAll('[data-semantic-type]').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    renderSemanticCategories();
                    tg.HapticFeedback.selectionChanged();
                });
            });
        }

        async function loadData() {
            showLoading();
            
            try {
                const initData = tg.initData || '';
                const headers = {
                    'Content-Type': 'application/json',
                    'X-Telegram-Init-Data': initData
                };

                const [summaryRes, categoriesRes, semanticRes, dateRes, opsRes] = await Promise.all([
                    fetch(`${API_BASE}/summary`, { headers }),
                    fetch(`${API_BASE}/by-category`, { headers }),
                    fetch(`${API_BASE}/by-semantic-category`, { headers }),
                    fetch(`${API_BASE}/by-date?group=month`, { headers }),
                    fetch(`${API_BASE}/operations?limit=50`, { headers })
                ]);

                const summary = await summaryRes.json();
                const categories = await categoriesRes.json();
                const semantic = await semanticRes.json();
                const dateData = await dateRes.json();
                const operations = await opsRes.json();

                renderSummary(summary);
                categoryData = categories;
                semanticData = semantic;
                renderCategories();
                renderSemanticCategories();
                renderChart(dateData);
                renderOperations(operations.data || []);

                showContent();
            } catch (error) {
                console.error('Error loading data:', error);
                showError('Ошибка загрузки данных. Попробуйте позже.');
            }
        }

        function formatMoney(amount) {
            const num = parseFloat(amount) || 0;
            return num.toLocaleString('ru-RU', { 
                minimumFractionDigits: 0, 
                maximumFractionDigits: 0 
            }) + ' ₽';
        }

        function renderSummary(data) {
            document.getElementById('total-income').textContent = formatMoney(data.total_income);
            document.getElementById('total-expense').textContent = formatMoney(Math.abs(data.total_expense));
            document.getElementById('balance').textContent = formatMoney(data.balance);
            document.getElementById('total-ops').textContent = data.total_operations;
        }

        function renderCategories() {
            const list = document.getElementById('category-list');
            const data = categoryData[currentCategoryType] || [];

            if (data.length === 0) {
                list.innerHTML = '<div class="empty-state"><div class="icon">📊</div><div>Нет данных</div></div>';
                return;
            }

            list.innerHTML = data.map(item => `
                <div class="category-item">
                    <span class="category-name">${item.category || 'Без категории'}</span>
                    <span class="category-amount ${currentCategoryType}">
                        ${formatMoney(Math.abs(item.total))}
                    </span>
                </div>
            `).join('');
        }

        function renderSemanticCategories() {
            const list = document.getElementById('semantic-list');
            const data = semanticData[currentSemanticType] || [];

            if (data.length === 0) {
                list.innerHTML = '<div class="empty-state"><div class="icon">🧠</div><div>Нет умных категорий</div></div>';
                return;
            }

            list.innerHTML = data.map(item => `
                <div class="category-item">
                    <span class="category-name">${item.semantic_category || 'Без категории'}</span>
                    <span class="category-amount ${currentSemanticType}">
                        ${formatMoney(Math.abs(item.total))}
                    </span>
                </div>
            `).join('');
        }

        function renderChart(data) {
            const ctx = document.getElementById('monthly-chart').getContext('2d');
            
            const incomeMap = {};
            const expenseMap = {};
            
            (data.income || []).forEach(item => {
                incomeMap[item.period] = parseFloat(item.total) || 0;
            });
            
            (data.expense || []).forEach(item => {
                expenseMap[item.period] = Math.abs(parseFloat(item.total) || 0);
            });

            const allPeriods = [...new Set([...Object.keys(incomeMap), ...Object.keys(expenseMap)])].sort();

            if (monthlyChart) {
                monthlyChart.destroy();
            }

            monthlyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: allPeriods,
                    datasets: [
                        {
                            label: 'Доходы',
                            data: allPeriods.map(p => incomeMap[p] || 0),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Расходы',
                            data: allPeriods.map(p => expenseMap[p] || 0),
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 8,
                                font: { size: 11 }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { font: { size: 10 } }
                        },
                        y: {
                            ticks: { 
                                font: { size: 10 },
                                callback: value => (value / 1000) + 'k'
                            }
                        }
                    }
                }
            });
        }

        function renderOperations(data) {
            const list = document.getElementById('operations-list');

            if (data.length === 0) {
                list.innerHTML = '<div class="empty-state"><div class="icon">📝</div><div>Нет операций</div></div>';
                return;
            }

            list.innerHTML = data.slice(0, 50).map(op => `
                <div class="operation-item">
                    <div class="operation-header">
                        <span class="operation-category">${op.category || 'Без категории'}</span>
                        <span class="operation-amount ${op.action}">
                            ${op.action === 'income' ? '+' : ''}${formatMoney(op.ammount)}
                        </span>
                    </div>
                    ${op.description ? `<div class="operation-description">${op.description.substring(0, 50)}${op.description.length > 50 ? '...' : ''}</div>` : ''}
                    <div class="operation-date">${formatDate(op.datatime)}</div>
                </div>
            `).join('');
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('ru-RU', { 
                day: 'numeric', 
                month: 'short', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showLoading() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('content').style.display = 'none';
            document.getElementById('error-container').style.display = 'none';
        }

        function showContent() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('content').style.display = 'block';
            document.getElementById('error-container').style.display = 'none';
        }

        function showError(message) {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('content').style.display = 'none';
            document.getElementById('error-container').style.display = 'block';
            document.getElementById('error-message').textContent = message;
        }
    </script>
</body>
</html>
