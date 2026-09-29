<?php
require '../common2.php';
$nframework->usecommon = true;

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));

$nframework->jss[] = 'https://cdn.jsdelivr.net/npm/chart.js';
?>

<div class="container">
    <div class="box shadow-large">
        <div class="box-title">Estadisticas de trafico</div>
        <p>Panel avanzado con multiples graficas de sesiones, rutas, transferencia, latencia, IPs y agentes.</p>

        <style>
            .stats-dashboard {
                --bg: #f2f5fb;
                --card: #ffffff;
                --text: #172234;
                --muted: #617086;
                --brand: #1362f4;
                --ok: #0aa57c;
                --warn: #dd8a00;
                --danger: #d53b3b;
                --border: #dde5f2;
                background:
                    radial-gradient(circle at 2% 0%, rgba(19, 98, 244, 0.10), transparent 26%),
                    radial-gradient(circle at 98% 5%, rgba(10, 165, 124, 0.10), transparent 24%),
                    var(--bg);
                border-radius: 14px;
                padding: 14px;
                color: var(--text);
                font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            }

            .stats-toolbar {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-end;
                gap: 10px;
                margin-bottom: 14px;
            }

            .stats-field {
                display: grid;
                gap: 5px;
            }

            .stats-field label {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: .05em;
                color: var(--muted);
            }

            .stats-field input {
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 7px 9px;
                min-width: 150px;
            }

            .stats-field select {
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 7px 9px;
                min-width: 150px;
                background: #fff;
            }

            .stats-btn {
                border: 0;
                border-radius: 9px;
                padding: 8px 14px;
                background: linear-gradient(135deg, #0f5de8, #2b86ff);
                color: #fff;
                font-weight: 700;
                cursor: pointer;
            }

            .stats-status {
                margin-left: auto;
                font-size: 13px;
                color: var(--muted);
            }

            .stats-summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                gap: 10px;
                margin-bottom: 14px;
            }

            .stats-card {
                border: 1px solid var(--border);
                border-radius: 10px;
                background: var(--card);
                padding: 10px;
            }

            .stats-card .k {
                color: var(--muted);
                font-size: 12px;
            }

            .stats-card .v {
                font-size: 22px;
                font-weight: 700;
                margin-top: 4px;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .stats-panel {
                border: 1px solid var(--border);
                border-radius: 12px;
                background: var(--card);
                padding: 10px;
            }

            .stats-panel h3 {
                margin: 0 0 8px;
                font-size: 14px;
                font-weight: 700;
                color: #1b2d4a;
            }

            .stats-canvas {
                position: relative;
                height: 260px;
            }

            .stats-canvas.tall {
                height: 320px;
            }

            @media (max-width: 980px) {
                .stats-grid {
                    grid-template-columns: 1fr;
                }

                .stats-status {
                    width: 100%;
                    margin-left: 0;
                }
            }
        </style>

        <div class="stats-dashboard">
            <div class="stats-toolbar">
                <div class="stats-field">
                    <label for="dateini">Fecha inicio</label>
                    <input type="date" id="dateini" value="<?= $weekAgo ?>">
                </div>
                <div class="stats-field">
                    <label for="dateend">Fecha fin</label>
                    <input type="date" id="dateend" value="<?= $today ?>">
                </div>
                <div class="stats-field">
                    <label for="methodFilter">Metodo HTTP</label>
                    <select id="methodFilter">
                        <option value="">Todos</option>
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="PATCH">PATCH</option>
                        <option value="DELETE">DELETE</option>
                        <option value="HEAD">HEAD</option>
                        <option value="OPTIONS">OPTIONS</option>
                    </select>
                </div>
                <div class="stats-field">
                    <label for="statusFilter">Status HTTP</label>
                    <select id="statusFilter">
                        <option value="">Todos</option>
                        <option value="2XX">2XX</option>
                        <option value="3XX">3XX</option>
                        <option value="4XX">4XX</option>
                        <option value="5XX">5XX</option>
                        <option value="200">200</option>
                        <option value="301">301</option>
                        <option value="302">302</option>
                        <option value="403">403</option>
                        <option value="404">404</option>
                        <option value="500">500</option>
                    </select>
                </div>
                <div class="stats-field">
                    <label for="blockedFilter">Bloqueos</label>
                    <select id="blockedFilter">
                        <option value="all">Todos</option>
                        <option value="exclude">Excluir bloqueados</option>
                        <option value="only">Solo bloqueados</option>
                    </select>
                </div>
                <button class="stats-btn" id="reloadBtn" type="button">Actualizar</button>
                <div class="stats-status" id="status">Cargando...</div>
            </div>

            <div class="stats-summary">
                <div class="stats-card">
                    <div class="k">Requests totales</div>
                    <div class="v" id="totalRequests">0</div>
                </div>
                <div class="stats-card">
                    <div class="k">Sesiones totales</div>
                    <div class="v" id="totalSessions">0</div>
                </div>
                <div class="stats-card">
                    <div class="k">Rutas unicas totales</div>
                    <div class="v" id="totalPaths">0</div>
                </div>
                <div class="stats-card">
                    <div class="k">IPs unicas totales</div>
                    <div class="v" id="totalIps">0</div>
                </div>
                <div class="stats-card">
                    <div class="k">Transferencia total (MB)</div>
                    <div class="v" id="totalMB">0</div>
                </div>
                <div class="stats-card">
                    <div class="k">Tiempo respuesta total (ms)</div>
                    <div class="v" id="totalResponse">0</div>
                </div>
                <div class="stats-card">
                    <div class="k">Dias con datos</div>
                    <div class="v" id="totalDays">0</div>
                </div>
                <div class="stats-card">
                    <div class="k">Latencia promedio (ms)</div>
                    <div class="v" id="avgResponse">0</div>
                </div>
                <div class="stats-card">
                    <div class="k">Tasa de error (%)</div>
                    <div class="v" id="errorRate">0</div>
                </div>
                <div class="stats-card">
                    <div class="k">Tasa de bloqueo (%)</div>
                    <div class="v" id="blockedRate">0</div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stats-panel">
                    <h3>Tendencia diaria: sesiones, rutas e IPs</h3>
                    <div class="stats-canvas"><canvas id="trendChart"></canvas></div>
                </div>

                <div class="stats-panel">
                    <h3>Transferencia por dia (MB)</h3>
                    <div class="stats-canvas"><canvas id="transferChart"></canvas></div>
                </div>

                <div class="stats-panel">
                    <h3>Latencia total y promedio por sesion</h3>
                    <div class="stats-canvas"><canvas id="responseChart"></canvas></div>
                </div>

                <div class="stats-panel">
                    <h3>Correlacion sesiones vs IPs</h3>
                    <div class="stats-canvas"><canvas id="scatterChart"></canvas></div>
                </div>

                <div class="stats-panel">
                    <h3>Acumulado de sesiones y transferencia</h3>
                    <div class="stats-canvas"><canvas id="cumulativeChart"></canvas></div>
                </div>

                <div class="stats-panel">
                    <h3>Distribucion global (sesiones/rutas/IPs)</h3>
                    <div class="stats-canvas"><canvas id="totalsChart"></canvas></div>
                </div>

                <div class="stats-panel">
                    <h3>Distribucion de codigos HTTP</h3>
                    <div class="stats-canvas"><canvas id="statusChart"></canvas></div>
                </div>

                <div class="stats-panel">
                    <h3>Errores y bloqueos por dia</h3>
                    <div class="stats-canvas"><canvas id="securityChart"></canvas></div>
                </div>

                <div class="stats-panel" style="grid-column: 1 / -1;">
                    <h3>Top agentes de usuario</h3>
                    <div class="stats-canvas tall"><canvas id="agentsChart"></canvas></div>
                </div>

                <div class="stats-panel" style="grid-column: 1 / -1;">
                    <h3>Top rutas solicitadas</h3>
                    <div class="stats-canvas tall"><canvas id="pathsChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (() => {
        const POLL_MS = 5000;
        const statusEl = document.getElementById('status');
        const dateIniEl = document.getElementById('dateini');
        const dateEndEl = document.getElementById('dateend');
        const methodFilterEl = document.getElementById('methodFilter');
        const statusFilterEl = document.getElementById('statusFilter');
        const blockedFilterEl = document.getElementById('blockedFilter');

        const totalRequestsEl = document.getElementById('totalRequests');
        const totalSessionsEl = document.getElementById('totalSessions');
        const totalPathsEl = document.getElementById('totalPaths');
        const totalIpsEl = document.getElementById('totalIps');
        const totalMBEl = document.getElementById('totalMB');
        const totalResponseEl = document.getElementById('totalResponse');
        const totalDaysEl = document.getElementById('totalDays');
        const avgResponseEl = document.getElementById('avgResponse');
        const errorRateEl = document.getElementById('errorRate');
        const blockedRateEl = document.getElementById('blockedRate');

        const charts = {};
        let timer = null;

        function safeNumber(value) {
            const n = Number(value);
            return Number.isFinite(n) ? n : 0;
        }

        function normalizeDateKey(key) {
            const parts = String(key).split('-');
            if (parts.length !== 3) {
                return null;
            }
            const y = parts[0];
            const m = parts[1].padStart(2, '0');
            const d = parts[2].padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function localDateLabel(dateIso) {
            return new Intl.DateTimeFormat('es-MX', {
                dateStyle: 'medium'
            }).format(new Date(`${dateIso}T00:00:00`));
        }

        function formatLocalDateTime(dt) {
            return new Intl.DateTimeFormat('es-MX', {
                dateStyle: 'short',
                timeStyle: 'medium'
            }).format(dt);
        }

        function buildOrUpdateChart(name, config) {
            if (charts[name]) {
                charts[name].data = config.data;
                charts[name].options = config.options;
                charts[name].update();
                return;
            }
            const ctx = document.getElementById(name).getContext('2d');
            charts[name] = new Chart(ctx, config);
        }

        function fillSummary(payload, points) {
            const totalRequests = safeNumber(payload.total_requests);
            const totalSessions = safeNumber(payload.total_sessions);
            const totalPaths = safeNumber(payload.total_paths);
            const totalIps = safeNumber(payload.total_ips);
            const totalSizeBytes = safeNumber(payload.total_size_bytes);
            const totalResponse = safeNumber(payload.total_response_time_ms);
            const avgResponse = safeNumber(payload.avg_response_time_ms);
            const errorRate = safeNumber(payload.error_rate) * 100;
            const blockedRate = safeNumber(payload.blocked_rate) * 100;

            totalRequestsEl.textContent = totalRequests.toLocaleString('es-MX');
            totalSessionsEl.textContent = totalSessions.toLocaleString('es-MX');
            totalPathsEl.textContent = totalPaths.toLocaleString('es-MX');
            totalIpsEl.textContent = totalIps.toLocaleString('es-MX');
            totalMBEl.textContent = (totalSizeBytes / (1024 * 1024)).toFixed(2);
            totalResponseEl.textContent = totalResponse.toLocaleString('es-MX');
            totalDaysEl.textContent = points.length.toLocaleString('es-MX');
            avgResponseEl.textContent = avgResponse.toFixed(2);
            errorRateEl.textContent = errorRate.toFixed(2);
            blockedRateEl.textContent = blockedRate.toFixed(2);
        }

        function renderCharts(payload) {
            const rawStats = payload.stats || {};
            const points = Object.keys(rawStats)
                .map((dateKey) => {
                    const iso = normalizeDateKey(dateKey);
                    if (!iso) {
                        return null;
                    }
                    const item = rawStats[dateKey] || {};
                    return {
                        iso,
                        label: localDateLabel(iso),
                        requests: safeNumber(item.request_count),
                        sessions: safeNumber(item.sessions),
                        paths: safeNumber(item.paths),
                        ips: safeNumber(item.ips),
                        sizeMB: safeNumber(item.size_bytes) / (1024 * 1024),
                        responseMs: safeNumber(item.response_time_ms),
                        errors: safeNumber(item.error_count),
                        blocked: safeNumber(item.blocked_count)
                    };
                })
                .filter(Boolean)
                .sort((a, b) => (a.iso > b.iso ? 1 : -1));

            fillSummary(payload, points);

            const labels = points.map((p) => p.label);
            const sessions = points.map((p) => p.sessions);
            const paths = points.map((p) => p.paths);
            const ips = points.map((p) => p.ips);
            const sizeMB = points.map((p) => Number(p.sizeMB.toFixed(3)));
            const responseMs = points.map((p) => p.responseMs);
            const requests = points.map((p) => p.requests);
            const errorCounts = points.map((p) => p.errors);
            const blockedCounts = points.map((p) => p.blocked);
            const avgResponseMs = points.map((p) => (p.requests > 0 ? Number((p.responseMs / p.requests).toFixed(2)) : 0));
            const errorRateByDay = points.map((p) => (p.requests > 0 ? Number(((p.errors / p.requests) * 100).toFixed(2)) : 0));

            const cumulativeSessions = [];
            const cumulativeMB = [];
            let sumSessions = 0;
            let sumMB = 0;
            points.forEach((p) => {
                sumSessions += p.sessions;
                sumMB += p.sizeMB;
                cumulativeSessions.push(sumSessions);
                cumulativeMB.push(Number(sumMB.toFixed(2)));
            });

            buildOrUpdateChart('trendChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                            label: 'Sesiones',
                            data: sessions,
                            borderColor: '#1362f4',
                            backgroundColor: 'rgba(19, 98, 244, 0.18)',
                            fill: true,
                            tension: 0.25
                        },
                        {
                            label: 'Rutas',
                            data: paths,
                            borderColor: '#0aa57c',
                            backgroundColor: 'rgba(10, 165, 124, 0.15)',
                            fill: true,
                            tension: 0.25
                        },
                        {
                            label: 'Requests',
                            data: requests,
                            borderColor: '#6d54d8',
                            backgroundColor: 'rgba(109, 84, 216, 0.08)',
                            fill: false,
                            tension: 0.2
                        },
                        {
                            label: 'IPs',
                            data: ips,
                            borderColor: '#dd8a00',
                            backgroundColor: 'rgba(221, 138, 0, 0.08)',
                            fill: false,
                            tension: 0.2
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            buildOrUpdateChart('transferChart', {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'MB por dia',
                        data: sizeMB,
                        backgroundColor: 'rgba(19, 98, 244, 0.72)',
                        borderRadius: 6
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            buildOrUpdateChart('responseChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                            label: 'Respuesta total (ms)',
                            data: responseMs,
                            borderColor: '#d53b3b',
                            yAxisID: 'y',
                            tension: 0.25
                        },
                        {
                            label: 'Promedio por sesion (ms)',
                            data: avgResponseMs,
                            borderColor: '#1362f4',
                            yAxisID: 'y1',
                            tension: 0.25
                        },
                        {
                            label: 'Error % por dia',
                            data: errorRateByDay,
                            borderColor: '#7a2b2b',
                            borderDash: [6, 4],
                            yAxisID: 'y2',
                            tension: 0.2
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left'
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        y2: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: (value) => `${value}%`
                            }
                        }
                    }
                }
            });

            buildOrUpdateChart('scatterChart', {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Dia (x=sesiones, y=IPs)',
                        data: points.map((p) => ({
                            x: p.sessions,
                            y: p.ips,
                            r: Math.max(4, Math.min(14, Math.round(p.paths / 2)))
                        })),
                        backgroundColor: 'rgba(10, 165, 124, 0.55)'
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sesiones'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'IPs'
                            }
                        }
                    }
                }
            });

            buildOrUpdateChart('cumulativeChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                            label: 'Sesiones acumuladas',
                            data: cumulativeSessions,
                            borderColor: '#0aa57c',
                            yAxisID: 'y',
                            fill: false,
                            tension: 0.2
                        },
                        {
                            label: 'MB acumulados',
                            data: cumulativeMB,
                            borderColor: '#dd8a00',
                            yAxisID: 'y1',
                            fill: false,
                            tension: 0.2
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left'
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });

            buildOrUpdateChart('totalsChart', {
                type: 'doughnut',
                data: {
                    labels: ['Sesiones', 'Rutas', 'IPs'],
                    datasets: [{
                        data: [safeNumber(payload.total_sessions), safeNumber(payload.total_paths), safeNumber(payload.total_ips)],
                        backgroundColor: ['#1362f4', '#0aa57c', '#dd8a00']
                    }]
                },
                options: {
                    maintainAspectRatio: false
                }
            });

            const statusEntries = Object.entries(payload.status_codes || {})
                .map(([code, count]) => ({
                    code: String(code),
                    count: safeNumber(count)
                }))
                .sort((a, b) => Number(a.code) - Number(b.code));

            buildOrUpdateChart('statusChart', {
                type: 'bar',
                data: {
                    labels: statusEntries.map((entry) => entry.code),
                    datasets: [{
                        label: 'Requests por codigo',
                        data: statusEntries.map((entry) => entry.count),
                        backgroundColor: statusEntries.map((entry) => {
                            const code = Number(entry.code);
                            if (code >= 500) return 'rgba(213, 59, 59, 0.85)';
                            if (code >= 400) return 'rgba(221, 138, 0, 0.85)';
                            if (code >= 300) return 'rgba(109, 84, 216, 0.80)';
                            return 'rgba(10, 165, 124, 0.85)';
                        }),
                        borderRadius: 6
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            const agentEntries = Object.entries(payload.agents || {})
                .map(([agent, count]) => ({
                    agent,
                    count: safeNumber(count)
                }))
                .sort((a, b) => b.count - a.count)
                .slice(0, 12);

            buildOrUpdateChart('agentsChart', {
                type: 'bar',
                data: {
                    labels: agentEntries.map((entry) => entry.agent.length > 70 ? `${entry.agent.slice(0, 67)}...` : entry.agent),
                    datasets: [{
                        label: 'Hits por user-agent',
                        data: agentEntries.map((entry) => entry.count),
                        backgroundColor: 'rgba(29, 48, 84, 0.82)',
                        borderRadius: 5
                    }]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });

            const pathEntries = (payload.top_paths || [])
                .map((item) => ({
                    path: String(item.path || ''),
                    count: safeNumber(item.count)
                }))
                .filter((item) => item.path.length > 0)
                .sort((a, b) => b.count - a.count)
                .slice(0, 15);

            buildOrUpdateChart('pathsChart', {
                type: 'bar',
                data: {
                    labels: pathEntries.map((entry) => entry.path.length > 90 ? `${entry.path.slice(0, 87)}...` : entry.path),
                    datasets: [{
                        label: 'Requests por ruta',
                        data: pathEntries.map((entry) => entry.count),
                        backgroundColor: 'rgba(19, 98, 244, 0.82)',
                        borderRadius: 5
                    }]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });

            buildOrUpdateChart('securityChart', {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                            label: 'Errores diarios',
                            data: errorCounts,
                            borderColor: '#d53b3b',
                            backgroundColor: 'rgba(213, 59, 59, 0.15)',
                            fill: true,
                            tension: 0.2
                        },
                        {
                            label: 'Bloqueos diarios',
                            data: blockedCounts,
                            borderColor: '#dd8a00',
                            backgroundColor: 'rgba(221, 138, 0, 0.12)',
                            fill: true,
                            tension: 0.2
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        async function loadStats() {
            const dateini = dateIniEl.value;
            const dateend = dateEndEl.value;
            const method = methodFilterEl.value;
            const status = statusFilterEl.value;
            const blocked = blockedFilterEl.value;

            if (!dateini || !dateend) {
                statusEl.textContent = 'Define un rango valido';
                return;
            }

            if (dateini > dateend) {
                statusEl.textContent = 'La fecha inicio no puede ser mayor que la fecha fin';
                return;
            }

            statusEl.textContent = 'Sincronizando...';

            try {
                const url = `data.php?dateini=${encodeURIComponent(dateini)}&dateend=${encodeURIComponent(dateend)}&method=${encodeURIComponent(method)}&status=${encodeURIComponent(status)}&blocked=${encodeURIComponent(blocked)}&_=${Date.now()}`;
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const payload = await response.json();
                renderCharts(payload);
                statusEl.textContent = `Ultima actualizacion: ${formatLocalDateTime(new Date())}`;
            } catch (error) {
                statusEl.textContent = `Error de carga: ${error.message}`;
            }
        }

        function startPolling() {
            if (timer) {
                clearInterval(timer);
            }
            timer = setInterval(loadStats, POLL_MS);
        }

        function reloadAndRestartPolling() {
            loadStats();
            startPolling();
        }

        document.getElementById('reloadBtn').addEventListener('click', reloadAndRestartPolling);
        methodFilterEl.addEventListener('change', reloadAndRestartPolling);
        statusFilterEl.addEventListener('change', reloadAndRestartPolling);
        blockedFilterEl.addEventListener('change', reloadAndRestartPolling);

        loadStats();
        startPolling();
    })();
</script>