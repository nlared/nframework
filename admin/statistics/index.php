<?
require '../common2.php';
$nframework->usecommon = true;

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));

$nframework->jss[] = "https://cdn.jsdelivr.net/npm/chart.js";


?>
<div class="container">
    <div class="box shadow-large">
        <div class="box-title">Estadísticas de tráfico</div>
        <p>Visualiza las estadísticas de tráfico de tu sitio web, incluyendo sesiones únicas, URIs visitadas, transferencia de datos y tiempos de respuesta.</p>
        <style>
            :root {
                --bg: #f3f6fb;
                --card: #ffffff;
                --text: #19202a;
                --muted: #66758a;
                --brand: #1264ff;
                --accent: #00a884;
                --border: #dbe3ef;
            }<h1>Estadísticas de tráfico</h1>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: "Segoe UI", "Trebuchet MS", sans-serif;
                background:
                    radial-gradient(circle at top left, #e8f4ff 0%, transparent 45%),
                    radial-gradient(circle at top right, #e7fff5 0%, transparent 40%),
                    var(--bg);
                color: var(--text);
                padding: 18px;
            }

            .wrap {
                max-width: 1100px;
                margin: 0 auto;
            }

            .panel {
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 14px;
                box-shadow: 0 8px 24px rgba(9, 27, 52, 0.08);
                padding: 16px;
            }

            .toolbar {
                display: flex;
                flex-wrap: wrap;
                align-items: end;
                gap: 12px;
                margin-bottom: 14px;
            }

            .field {
                display: grid;
                gap: 6px;
            }

            .field label {
                font-size: 12px;
                color: var(--muted);
                text-transform: uppercase;
                letter-spacing: .04em;
            }

            .field input {
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 8px 10px;
                font-size: 14px;
            }

            button {
                border: 0;
                background: linear-gradient(135deg, var(--brand), #2b7eff);
                color: #fff;
                font-weight: 600;
                border-radius: 8px;
                padding: 9px 14px;
                cursor: pointer;
            }

            .status {
                margin-left: auto;
                font-size: 13px;
                color: var(--muted);
            }

            .summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                gap: 10px;
                margin: 14px 0;
            }

            .stat {
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 10px;
                background: #fbfdff;
            }

            .stat .k {
                font-size: 12px;
                color: var(--muted);
            }

            .stat .v {
                font-size: 22px;
                font-weight: 700;
                color: var(--text);
            }

            #chartWrap {
                position: relative;
                height: 420px;
            }

            @media (max-width: 700px) {
                body {
                    padding: 10px;
                }

                .status {
                    width: 100%;
                    margin-left: 0;
                }

                #chartWrap {
                    height: 320px;
                }
            }
        </style>
        </head>

        <body>
            <div class="wrap">
                <div class="panel">
                    <div class="toolbar">
                        <div class="field">
                            <label for="dateini">Fecha inicio</label>
                            <input type="date" id="dateini" value="<?= $weekAgo ?>">
                        </div>
                padding: 9px 14px;
                        <div class="field">
                            <label for="dateend">Fecha fin</label>
                            <input type="date" id="dateend" value="<?= $today ?>">
                        </div>
                        <button id="reloadBtn" type="button">Actualizar</button>
                        <div class="status" id="status">Cargando...</div>
                    </div>

                    <div class="summary">
                        <div class="stat">
                            <div class="k">Total sesiones unicas</div>
                            <div class="v" id="totalSessions">0</div>
                        </div>
                        <div class="stat">
                            <div class="k">Total URIs unicas</div>
                            <div class="v" id="totalUris">0</div>
                        </div>
                        <div class="stat">
                            <div class="k">Transferencia (MB)</div>
                            <div class="v" id="totalMB">0</div>
                        </div>
                    </div>

                    <div id="chartWrap">
                        <canvas id="statsChart"></canvas>
                    </div>
                </div>
            </div>

            <script>
                (() => {
                    const POLL_MS = 5000;
                    const statusEl = document.getElementById('status');
                    const dateIniEl = document.getElementById('dateini');
                    const dateEndEl = document.getElementById('dateend');
                    const totalSessionsEl = document.getElementById('totalSessions');
                    const totalUrisEl = document.getElementById('totalUris');
                    const totalMBEl = document.getElementById('totalMB');
                    const ctx = document.getElementById('statsChart').getContext('2d');

                    const chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: [{
                                    label: 'Sesiones unicas por dia',
                                    data: [],
                                    borderColor: '#1264ff',
                                    backgroundColor: 'rgba(18, 100, 255, 0.16)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                },
                                {
                                    label: 'URIs unicas por dia',
                                    data: [],
                                    borderColor: '#00a884',
                                    backgroundColor: 'rgba(0, 168, 132, 0.12)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                }
                            ]
                        },
                        options: {
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });

                    let timer = null;

                    function formatLocalDateTime(dt) {
                        return new Intl.DateTimeFormat('es-MX', {
                            dateStyle: 'short',
                            timeStyle: 'medium'
                        }).format(dt);
                    }

                    function uniqueCount(listLike) {
                        if (!Array.isArray(listLike)) {
                            return 0;
                        }
                        const merged = listLike.flat();
                        return new Set(merged).size;
                    }

                    async function loadStats() {
                        const dateini = dateIniEl.value;
                        const dateend = dateEndEl.value;

                        if (!dateini || !dateend) {
                            statusEl.textContent = 'Define un rango de fechas valido';
                            return;
                        }

                        statusEl.textContent = 'Sincronizando...';
                        try {
                            const url = `data.php?dateini=${encodeURIComponent(dateini)}&dateend=${encodeURIComponent(dateend)}&_=${Date.now()}`;
                            const response = await fetch(url, {
                                cache: 'no-store'
                            });
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}`);
                            }
                            const payload = await response.json();

                            const labels = Object.keys(payload).sort();
                            const sessionsByDay = [];
                            const urisByDay = [];
                            let sessionsTotal = 0;
                            let urisTotal = 0;
                            let bytesTotal = 0;

                            labels.forEach((day) => {
                                const item = payload[day] || {};
                                const sessionsCount = uniqueCount(item.sessions || []);
                                const urisCount = uniqueCount(item.uris || []);
                                const dayBytes = (Array.isArray(item.size_bytes) ? item.size_bytes[0] : 0) || 0;

                                sessionsByDay.push(sessionsCount);
                                urisByDay.push(urisCount);

                                sessionsTotal += sessionsCount;
                                urisTotal += urisCount;
                                bytesTotal += dayBytes;
                            });

                            chart.data.labels = labels;
                            chart.data.datasets[0].data = sessionsByDay;
                            chart.data.datasets[1].data = urisByDay;
                            chart.update();

                            totalSessionsEl.textContent = sessionsTotal.toLocaleString('es-MX');
                            totalUrisEl.textContent = urisTotal.toLocaleString('es-MX');
                            totalMBEl.textContent = (bytesTotal / (1024 * 1024)).toFixed(2);

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

                    document.getElementById('reloadBtn').addEventListener('click', () => {
                        loadStats();
                        startPolling();
                    });

                    loadStats();
                    startPolling();
                })();
            </script>
        </body>

        </html>