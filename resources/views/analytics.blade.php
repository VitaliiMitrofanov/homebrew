<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Финансовая аналитика</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .chart-container {
            position: relative;
            height: 350px;
            margin-bottom: 20px;
        }
        .summary-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-card.income { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .summary-card.expense { background: linear-gradient(135deg, #dc3545, #fd7e14); color: white; }
        .summary-card.balance { background: linear-gradient(135deg, #007bff, #6f42c1); color: white; }
        .summary-card.total { background: linear-gradient(135deg, #6c757d, #495057); color: white; }
        .summary-card h3 { font-size: 2rem; margin-bottom: 0; }
        .summary-card p { margin-bottom: 0; opacity: 0.9; }
        .clickable-chart { cursor: pointer; }
        .nav-tabs .nav-link.active { font-weight: bold; }
        #detailsModal .modal-dialog { max-width: 90%; }
        .loading { text-align: center; padding: 50px; }
        .period-selector { margin-bottom: 15px; }
        .filter-section { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .category-filter { max-height: 300px; overflow-y: auto; }
        .category-filter .form-check { margin-bottom: 5px; }
        .filter-badge { margin-right: 5px; margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Финансовая аналитика</h1>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    Выйти ({{ auth()->user()->email }})
                </button>
            </form>
        </div>
        
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-auto">
                    <strong>Исключить категории:</strong>
                </div>
                <div class="col">
                    <div id="excludedBadges"></div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#categoryFilterCollapse">
                        Выбрать категории
                    </button>
                    <button class="btn btn-outline-danger btn-sm" id="clearFilters" style="display:none;">
                        Сбросить
                    </button>
                </div>
            </div>
            <div class="collapse mt-3" id="categoryFilterCollapse">
                <div class="category-filter" id="categoryFilter">
                    <div class="text-muted">Загрузка категорий...</div>
                </div>
            </div>
        </div>
        
        <div class="row" id="summaryCards">
            <div class="col-md-3">
                <div class="summary-card income">
                    <p>Доходы</p>
                    <h3 id="totalIncome">Загрузка...</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card expense">
                    <p>Расходы</p>
                    <h3 id="totalExpense">Загрузка...</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card balance">
                    <p>Баланс</p>
                    <h3 id="balance">Загрузка...</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card total">
                    <p>Всего операций</p>
                    <h3 id="totalOperations">Загрузка...</h3>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="analyticsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="date-tab" data-bs-toggle="tab" data-bs-target="#dateContent" type="button">По дате</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="category-tab" data-bs-toggle="tab" data-bs-target="#categoryContent" type="button">По категориям</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#userContent" type="button">По пользователям</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bankContent" type="button">По банкам</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="semantic-tab" data-bs-toggle="tab" data-bs-target="#semanticContent" type="button">Умные категории</button>
            </li>
        </ul>

        <div class="tab-content" id="analyticsTabContent">
            <div class="tab-pane fade show active" id="dateContent" role="tabpanel">
                <div class="period-selector">
                    <label class="me-2">Группировка:</label>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="dateGroup" id="groupDay" value="day">
                        <label class="btn btn-outline-primary" for="groupDay">День</label>
                        <input type="radio" class="btn-check" name="dateGroup" id="groupWeek" value="week">
                        <label class="btn btn-outline-primary" for="groupWeek">Неделя</label>
                        <input type="radio" class="btn-check" name="dateGroup" id="groupMonth" value="month" checked>
                        <label class="btn btn-outline-primary" for="groupMonth">Месяц</label>
                    </div>
                </div>
                <div class="chart-container clickable-chart">
                    <canvas id="dateChart"></canvas>
                </div>
            </div>
            <div class="tab-pane fade" id="categoryContent" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-success text-center">Доходы по категориям</h5>
                        <div class="chart-container clickable-chart">
                            <canvas id="categoryIncomeChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-danger text-center">Расходы по категориям</h5>
                        <div class="chart-container clickable-chart">
                            <canvas id="categoryExpenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="userContent" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-success text-center">Доходы по пользователям</h5>
                        <div class="chart-container clickable-chart">
                            <canvas id="userIncomeChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-danger text-center">Расходы по пользователям</h5>
                        <div class="chart-container clickable-chart">
                            <canvas id="userExpenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="bankContent" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-success text-center">Доходы по банкам</h5>
                        <div class="chart-container clickable-chart">
                            <canvas id="bankIncomeChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-danger text-center">Расходы по банкам</h5>
                        <div class="chart-container clickable-chart">
                            <canvas id="bankExpenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="semanticContent" role="tabpanel">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span id="semanticStatus" class="text-muted">Загрузка статуса...</span>
                        </div>
                        <button class="btn btn-primary btn-sm" id="populateSemanticBtn">
                            Сгенерировать категории (50 операций)
                        </button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-success text-center">Доходы по умным категориям</h5>
                        <div class="chart-container clickable-chart">
                            <canvas id="semanticIncomeChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-danger text-center">Расходы по умным категориям</h5>
                        <div class="chart-container clickable-chart">
                            <canvas id="semanticExpenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Детали операций</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table id="operationsTable" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Категория</th>
                                <th>Описание</th>
                                <th>Тип</th>
                                <th>Сумма</th>
                                <th>Пользователь</th>
                                <th>Банк</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        const formatMoney = (value) => {
            return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(value);
        };

        const colors = [
            '#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f',
            '#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#bab0ac',
            '#86bcb6', '#d37295', '#8cd17d', '#b6992d', '#499894'
        ];

        let charts = {};
        let dataTable = null;
        let currentDateGroup = 'month';
        let excludedCategories = [];
        let allCategories = [];

        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        });

        function getFilterParams(extra = {}) {
            const params = { ...extra };
            if (excludedCategories.length > 0) {
                params.exclude_categories = excludedCategories.join(',');
            }
            return params;
        }

        function loadCategories() {
            $.get('/api/analytics/categories', function(data) {
                allCategories = data;
                renderCategoryFilter();
            });
        }

        function renderCategoryFilter() {
            let html = '<div class="row">';
            allCategories.forEach((cat, index) => {
                const isExcluded = excludedCategories.includes(cat);
                html += `
                    <div class="col-md-4 col-lg-3">
                        <div class="form-check">
                            <input class="form-check-input category-checkbox" type="checkbox" value="${cat}" id="cat_${index}" ${isExcluded ? 'checked' : ''}>
                            <label class="form-check-label" for="cat_${index}">${cat}</label>
                        </div>
                    </div>`;
            });
            html += '</div>';
            $('#categoryFilter').html(html);
        }

        function updateExcludedBadges() {
            let html = '';
            excludedCategories.forEach(cat => {
                html += `<span class="badge bg-secondary filter-badge">${cat} <span class="remove-category" data-category="${cat}" style="cursor:pointer;">&times;</span></span>`;
            });
            $('#excludedBadges').html(html || '<span class="text-muted">Нет исключений</span>');
            $('#clearFilters').toggle(excludedCategories.length > 0);
        }

        function refreshAllData() {
            loadSummary();
            loadDateChart();
            loadCategoryCharts();
            loadUserCharts();
            loadBankCharts();
            loadSemanticCharts();
            loadSemanticStatus();
        }

        function loadSummary() {
            $.get('/api/analytics/summary', getFilterParams(), function(data) {
                $('#totalIncome').text(formatMoney(data.total_income));
                $('#totalExpense').text(formatMoney(data.total_expense));
                $('#balance').text(formatMoney(data.balance));
                $('#totalOperations').text(data.total_operations);
            });
        }

        function loadDateChart() {
            $.get('/api/analytics/by-date', getFilterParams({ group: currentDateGroup }), function(data) {
                const periods = [...new Set([...data.income.map(i => i.period), ...data.expense.map(e => e.period)])].sort();
                const incomeData = periods.map(p => {
                    const item = data.income.find(i => i.period === p);
                    return item ? parseFloat(item.total) : 0;
                });
                const expenseData = periods.map(p => {
                    const item = data.expense.find(e => e.period === p);
                    return item ? parseFloat(item.total) : 0;
                });

                if (charts.dateChart) charts.dateChart.destroy();
                
                charts.dateChart = new Chart(document.getElementById('dateChart'), {
                    type: 'line',
                    data: {
                        labels: periods,
                        datasets: [
                            { label: 'Доходы', data: incomeData, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)', fill: true, tension: 0.3 },
                            { label: 'Расходы', data: expenseData, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', fill: true, tension: 0.3 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onClick: (e, elements) => {
                            if (elements.length > 0) {
                                const index = elements[0].index;
                                const period = periods[index];
                                const action = elements[0].datasetIndex === 0 ? 'income' : 'expense';
                                showOperations({ period, group: currentDateGroup, action });
                            }
                        },
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { beginAtZero: true, ticks: { callback: v => formatMoney(v) } } }
                    }
                });
            });
        }

        function loadCategoryCharts() {
            $.get('/api/analytics/by-category', getFilterParams(), function(data) {
                createPieChart('categoryIncomeChart', data.income, 'income', 'category');
                createPieChart('categoryExpenseChart', data.expense, 'expense', 'category');
            });
        }

        function loadUserCharts() {
            $.get('/api/analytics/by-user', getFilterParams(), function(data) {
                createBarChart('userIncomeChart', data.income, 'income', 'username');
                createBarChart('userExpenseChart', data.expense, 'expense', 'username');
            });
        }

        function loadBankCharts() {
            $.get('/api/analytics/by-bank', getFilterParams(), function(data) {
                createPieChart('bankIncomeChart', data.income, 'income', 'bank');
                createPieChart('bankExpenseChart', data.expense, 'expense', 'bank');
            });
        }

        function loadSemanticCharts() {
            $.get('/api/analytics/by-semantic-category', getFilterParams(), function(data) {
                createPieChart('semanticIncomeChart', data.income, 'income', 'semantic_category');
                createPieChart('semanticExpenseChart', data.expense, 'expense', 'semantic_category');
                if (data.without_semantic > 0) {
                    $('#semanticStatus').html(`<span class="badge bg-warning">${data.without_semantic} операций без умной категории</span>`);
                } else {
                    $('#semanticStatus').html('<span class="badge bg-success">Все операции категоризированы</span>');
                }
            });
        }

        function loadSemanticStatus() {
            $.get('/api/semantic/status', function(data) {
                const percent = data.progress_percent;
                let statusHtml = `<span class="badge bg-info">${data.with_semantic_category} из ${data.total_operations} (${percent}%)</span>`;
                if (data.without_semantic_category > 0) {
                    statusHtml += ` <span class="badge bg-warning">${data.without_semantic_category} без категории</span>`;
                }
                $('#semanticStatus').html(statusHtml);
            });
        }

        function populateSemanticCategories() {
            $('#populateSemanticBtn').prop('disabled', true).text('Обработка...');
            $.post('/api/semantic/populate', { limit: 50 }, function(data) {
                $('#populateSemanticBtn').prop('disabled', false).text('Сгенерировать категории (50 операций)');
                alert(`Обработано: ${data.processed}, Ошибок: ${data.failed}, Осталось: ${data.remaining}`);
                loadSemanticCharts();
                loadSemanticStatus();
            }).fail(function() {
                $('#populateSemanticBtn').prop('disabled', false).text('Сгенерировать категории (50 операций)');
                alert('Ошибка при генерации категорий');
            });
        }

        function createPieChart(canvasId, data, action, filterKey) {
            if (charts[canvasId]) charts[canvasId].destroy();
            
            const labels = data.map(d => d[filterKey] || d.category || d.bank || 'Не указано');
            const values = data.map(d => parseFloat(d.total));
            
            charts[canvasId] = new Chart(document.getElementById(canvasId), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{ data: values, backgroundColor: colors }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (e, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const filter = { action };
                            filter[filterKey] = data[index][filterKey] || data[index].category || data[index].bank;
                            showOperations(filter);
                        }
                    },
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: ctx => `${ctx.label}: ${formatMoney(ctx.raw)}` } }
                    }
                }
            });
        }

        function createBarChart(canvasId, data, action, filterKey) {
            if (charts[canvasId]) charts[canvasId].destroy();
            
            const labels = data.map(d => d[filterKey] || 'Не указано');
            const values = data.map(d => parseFloat(d.total));
            const bgColor = action === 'income' ? '#28a745' : '#dc3545';
            
            charts[canvasId] = new Chart(document.getElementById(canvasId), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ label: action === 'income' ? 'Доходы' : 'Расходы', data: values, backgroundColor: bgColor }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    onClick: (e, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const filter = { action };
                            filter[filterKey] = data[index][filterKey];
                            showOperations(filter);
                        }
                    },
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { callback: v => formatMoney(v) } } }
                }
            });
        }

        function showOperations(filter) {
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            let title = 'Операции';
            if (filter.category) title += ` - ${filter.category}`;
            if (filter.username) title += ` - ${filter.username}`;
            if (filter.bank) title += ` - ${filter.bank}`;
            if (filter.period) title += ` - ${filter.period}`;
            if (filter.action) title += ` (${filter.action === 'income' ? 'Доходы' : 'Расходы'})`;
            
            $('#detailsModalLabel').text(title);
            
            if (dataTable) {
                dataTable.destroy();
                $('#operationsTable tbody').empty();
            }

            $.get('/api/analytics/operations', getFilterParams(filter), function(response) {
                dataTable = $('#operationsTable').DataTable({
                    data: response.data,
                    columns: [
                        { data: 'datatime', render: d => new Date(d).toLocaleString('ru-RU') },
                        { data: 'category' },
                        { data: 'description', render: d => d ? (d.length > 50 ? d.substring(0,50)+'...' : d) : '' },
                        { data: 'action', render: d => d === 'income' ? '<span class="badge bg-success">Доход</span>' : '<span class="badge bg-danger">Расход</span>' },
                        { data: 'ammount', render: d => formatMoney(d) },
                        { data: 'username' },
                        { data: 'data_source' }
                    ],
                    order: [[0, 'desc']],
                    language: {
                        search: 'Поиск:',
                        lengthMenu: 'Показать _MENU_ записей',
                        info: 'Показано _START_ - _END_ из _TOTAL_',
                        paginate: { first: 'Первая', last: 'Последняя', next: 'След.', previous: 'Пред.' }
                    },
                    pageLength: 25
                });
                modal.show();
            });
        }

        $(document).ready(function() {
            loadCategories();
            updateExcludedBadges();
            refreshAllData();

            $('input[name="dateGroup"]').change(function() {
                currentDateGroup = $(this).val();
                loadDateChart();
            });

            $(document).on('change', '.category-checkbox', function() {
                const category = $(this).val();
                if ($(this).is(':checked')) {
                    if (!excludedCategories.includes(category)) {
                        excludedCategories.push(category);
                    }
                } else {
                    excludedCategories = excludedCategories.filter(c => c !== category);
                }
                updateExcludedBadges();
                refreshAllData();
            });

            $(document).on('click', '.remove-category', function() {
                const category = $(this).data('category');
                excludedCategories = excludedCategories.filter(c => c !== category);
                renderCategoryFilter();
                updateExcludedBadges();
                refreshAllData();
            });

            $('#clearFilters').click(function() {
                excludedCategories = [];
                renderCategoryFilter();
                updateExcludedBadges();
                refreshAllData();
            });

            $('#populateSemanticBtn').click(function() {
                populateSemanticCategories();
            });
        });
    </script>
</body>
</html>
