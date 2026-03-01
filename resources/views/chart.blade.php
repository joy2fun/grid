<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>股票市值竞赛图</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://d3js.org/d3.v7.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #08080c;
      --bg-secondary: #101018;
      --fg: #e8e8ed;
      --muted: #5a5a6e;
      --accent: #00d4aa;
      --card: #14141e;
      --border: #252532;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Space Grotesk', sans-serif;
      background: var(--bg);
      color: var(--fg);
      min-height: 100vh;
      overflow-x: hidden;
    }

    .mono { font-family: 'JetBrains Mono', monospace; }

    .bg-texture {
      position: fixed; inset: 0;
      background-image:
        radial-gradient(ellipse 100% 60% at 50% -10%, rgba(0, 212, 170, 0.04), transparent),
        radial-gradient(ellipse 80% 50% at 90% 100%, rgba(99, 102, 241, 0.03), transparent);
      pointer-events: none; z-index: 0;
    }

    .grid-bg {
      position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.012) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.012) 1px, transparent 1px);
      background-size: 40px 40px;
      pointer-events: none; z-index: 0;
    }

    .chart-container {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 8px 40px rgba(0,0,0,0.4);
      position: relative;
    }

    .axis-line { stroke: var(--border); stroke-width: 1; }
    .axis-text { fill: var(--muted); font-size: 11px; font-family: 'JetBrains Mono', monospace; }
    .grid-line { stroke: var(--border); stroke-width: 0.5; opacity: 0.3; }

    .line-path {
      fill: none;
      stroke-width: 2.5;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .line-glow {
      fill: none;
      stroke-width: 8;
      stroke-linecap: round;
      stroke-linejoin: round;
      filter: blur(4px);
      opacity: 0.3;
    }

    .control-btn {
      background: var(--bg-secondary);
      border: 1px solid var(--border);
      color: var(--fg);
      padding: 10px 18px;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
      font-family: 'Space Grotesk', sans-serif;
      font-weight: 500;
    }

    .control-btn:hover { background: var(--card); border-color: var(--accent); }
    .control-btn.active { background: var(--accent); color: var(--bg); border-color: var(--accent); }
    .control-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    .speed-btn { padding: 6px 12px; font-size: 11px; }

    .timeline-slider {
      -webkit-appearance: none; appearance: none;
      width: 100%; height: 5px;
      background: var(--border); border-radius: 3px;
      outline: none; cursor: pointer;
    }

    .timeline-slider::-webkit-slider-thumb {
      -webkit-appearance: none; appearance: none;
      width: 16px; height: 16px;
      background: var(--accent); border-radius: 50%;
      cursor: pointer;
      box-shadow: 0 0 12px rgba(0, 212, 170, 0.5);
      transition: transform 0.15s ease;
    }

    .timeline-slider::-webkit-slider-thumb:hover { transform: scale(1.2); }

    .stock-checkbox {
      appearance: none;
      width: 18px; height: 18px;
      border: 2px solid var(--border);
      border-radius: 4px;
      cursor: pointer;
      position: relative;
      transition: all 0.2s ease;
    }

    .stock-checkbox:checked {
      background: var(--accent);
      border-color: var(--accent);
    }

    .stock-checkbox:checked::after {
      content: '✓';
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      color: var(--bg);
      font-size: 12px;
      font-weight: bold;
    }

    .stock-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 14px;
      background: var(--bg-secondary);
      border: 1px solid var(--border);
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .stock-item:hover { border-color: var(--accent); }
    .stock-item.selected { border-color: var(--accent); background: rgba(0, 212, 170, 0.1); }

    .stat-card {
      background: linear-gradient(145deg, var(--card) 0%, var(--bg-secondary) 100%);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 14px;
      transition: transform 0.2s ease;
    }

    .stat-card:hover { transform: translateY(-2px); }

    .year-display {
      font-size: 120px; font-weight: 700;
      color: var(--fg); opacity: 0.04;
      position: absolute; right: 20px; top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      font-family: 'JetBrains Mono', monospace;
      line-height: 1; z-index: 1;
    }

    .leader-badge {
      position: absolute; left: 16px; top: 16px; z-index: 10;
      background: rgba(20, 20, 30, 0.85);
      backdrop-filter: blur(8px);
      padding: 12px 16px;
      border-radius: 10px;
      border: 1px solid var(--border);
    }

    .leader-name { font-size: 24px; font-weight: 700; transition: color 0.3s ease; }
    .leader-value { font-size: 13px; color: var(--muted); font-family: 'JetBrains Mono', monospace; margin-top: 2px; }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(16px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .animate-in { animation: fadeInUp 0.5s ease forwards; }

    .loading-overlay {
      position: fixed; inset: 0;
      background: rgba(8, 8, 12, 0.9);
      display: flex; align-items: center; justify-content: center;
      z-index: 100;
      opacity: 0; pointer-events: none;
      transition: opacity 0.3s ease;
    }

    .loading-overlay.active { opacity: 1; pointer-events: auto; }

    @media (max-width: 640px) {
      .year-display { font-size: 60px; right: 10px; }
      .leader-name { font-size: 18px; }
      .leader-badge { left: 10px; top: 10px; padding: 8px 12px; }
    }
  </style>
</head>
<body>
  <div class="bg-texture"></div>
  <div class="grid-bg"></div>

  <div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
      <div class="text-2xl font-bold mb-2">加载中...</div>
      <div class="text-[var(--muted)]">正在获取股票数据</div>
    </div>
  </div>

  <div class="relative z-10 min-h-screen p-4 md:p-6 lg:p-8">
    <div class="max-w-6xl mx-auto">

      <header class="mb-5 animate-in" style="animation-delay: 0.1s;">
        <p class="text-xs uppercase tracking-widest text-[var(--muted)] mb-1 mono">Stock Market Cap Race</p>
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight">股票市值竞赛图</h1>
      </header>

      <!-- 配置区域 -->
      <div class="bg-[var(--card)] border border-[var(--border)] rounded-xl p-4 mb-5 animate-in" style="animation-delay: 0.15s;">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
          <div>
            <label class="block text-xs text-[var(--muted)] uppercase tracking-wider mb-2">起始日期</label>
            <input type="date" id="startDate" class="w-full bg-[var(--bg-secondary)] border border-[var(--border)] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[var(--accent)] transition-colors" value="{{ date('Y-m-d', strtotime('-1 year')) }}">
          </div>
          <div>
            <label class="block text-xs text-[var(--muted)] uppercase tracking-wider mb-2">时间间隔</label>
            <select id="intervalSelect" class="w-full bg-[var(--bg-secondary)] border border-[var(--border)] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[var(--accent)] transition-colors">
              <option value="auto">自动 (推荐)</option>
              <option value="daily">按天</option>
              <option value="2days">每2天</option>
              <option value="3days">每3天</option>
            </select>
            <p class="text-xs text-[var(--muted)] mt-1" id="intervalHint">根据时间跨度自动选择</p>
          </div>
          <div class="md:col-span-2 flex items-start">
            <button id="loadDataBtn" class="control-btn w-full md:w-auto flex items-center justify-center gap-2 mt-[22px] h-[42px]">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                <path d="M3 3v5h5"></path>
              </svg>
              加载数据
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs text-[var(--muted)] uppercase tracking-wider mb-2">选择股票（初始市值均为 ¥100,000）</label>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 max-h-48 overflow-y-auto p-1" id="stockList">
            @foreach($stocks as $stock)
            <div class="stock-item" data-id="{{ $stock->id }}" data-code="{{ $stock->code }}" data-name="{{ $stock->name }}">
              <input type="checkbox" class="stock-checkbox" value="{{ $stock->id }}" id="stock-{{ $stock->id }}">
              <label for="stock-{{ $stock->id }}" class="flex-1 cursor-pointer text-sm truncate">
                <span class="font-medium">{{ $stock->code }}</span>
                <span class="text-[var(--muted)] text-xs block truncate">{{ $stock->name }}</span>
              </label>
            </div>
            @endforeach
          </div>
          <div class="flex justify-between items-center mt-2">
            <span class="text-xs text-[var(--muted)]" id="selectedCount">已选择 0 个</span>
            <div class="flex gap-2">
              <button id="selectAllBtn" class="text-xs text-[var(--accent)] hover:underline">全选</button>
              <button id="clearAllBtn" class="text-xs text-[var(--muted)] hover:underline">清空</button>
            </div>
          </div>
        </div>
      </div>

      <!-- 统计卡片 -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 animate-in" style="animation-delay: 0.2s;" id="statsSection" style="display: none;">
        <div class="stat-card">
          <p class="text-xs text-[var(--muted)] uppercase tracking-wider mb-1">日期</p>
          <p class="text-xl font-bold mono" id="currentDate">-</p>
        </div>
        <div class="stat-card">
          <p class="text-xs text-[var(--muted)] uppercase tracking-wider mb-1">领先</p>
          <p class="text-lg font-bold" id="leaderName" style="color: #00d4aa;">-</p>
        </div>
        <div class="stat-card">
          <p class="text-xs text-[var(--muted)] uppercase tracking-wider mb-1">市值</p>
          <p class="text-xl font-bold mono" id="leaderValue">-</p>
        </div>
        <div class="stat-card">
          <p class="text-xs text-[var(--muted)] uppercase tracking-wider mb-1">涨跌幅</p>
          <p class="text-xl font-bold mono" id="leaderChange">-</p>
        </div>
      </div>

      <!-- 图表区域 -->
      <div id="chartSection" style="display: none;">
        <div class="chart-container mb-4 animate-in relative" style="animation-delay: 0.25s;">
          <div class="year-display" id="dateDisplay">-</div>
          <div id="chart" class="w-full" style="height: 460px;"></div>
        </div>

        <div class="bg-[var(--card)] border border-[var(--border)] rounded-xl p-4 mb-4 animate-in" style="animation-delay: 0.3s;">
          <div class="flex items-center gap-3 mb-3">
            <button id="playBtn" class="control-btn active flex items-center justify-center w-10 h-10 p-0" aria-label="播放/暂停">
              <svg id="playIcon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <rect x="6" y="4" width="4" height="16"></rect>
                <rect x="14" y="4" width="4" height="16"></rect>
              </svg>
            </button>
            <button id="resetBtn" class="control-btn flex items-center justify-center w-10 h-10 p-0" aria-label="重置">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                <path d="M3 3v5h5"></path>
              </svg>
            </button>
            <div class="flex-1 px-1">
              <input type="range" id="timeline" class="timeline-slider" min="0" max="100" value="0" step="0.1">
            </div>
            <div class="flex gap-1">
              <button class="speed-btn control-btn" data-speed="0.25">0.25x</button>
              <button class="speed-btn control-btn" data-speed="0.5">0.5x</button>
              <button class="speed-btn control-btn active" data-speed="1">1x</button>
              <button class="speed-btn control-btn" data-speed="2">2x</button>
            </div>
          </div>
          <div class="flex justify-between text-xs mono text-[var(--muted)] px-1" id="timelineLabels">
            <span>-</span>
            <span>-</span>
            <span>-</span>
            <span>-</span>
            <span>-</span>
          </div>
        </div>

        <div class="bg-[var(--card)] border border-[var(--border)] rounded-xl p-4 animate-in" style="animation-delay: 0.35s;">
          <div class="flex flex-wrap gap-2" id="legend"></div>
        </div>
      </div>

      <!-- 空状态 -->
      <div id="emptyState" class="text-center py-20 animate-in" style="animation-delay: 0.2s;">
        <div class="text-6xl mb-4">📊</div>
        <h3 class="text-xl font-bold mb-2">选择股票开始</h3>
        <p class="text-[var(--muted)]">选择起始日期和股票，点击"加载数据"查看市值竞赛图</p>
      </div>

    </div>
  </div>

  <script>
    // ==================== LineChartRace Class ====================
    class LineChartRace {
      #config = null;
      #interpolatedData = [];
      #state = { currentFrame: 0, isPlaying: false, speed: 1, highlightedSeries: null, animationId: null, lastTime: 0 };
      #dom = {};
      #chart = { svg: null, xScale: null, yScale: null, width: 0, height: 0, margin: { top: 50, right: 140, bottom: 45, left: 20 } };

      constructor(config) {
        this.#config = this.#mergeConfig(config);
        this.#interpolatedData = this.#interpolateData();
        this.#cacheDomElements();
        this.#bindEvents();
      }

      #mergeConfig(userConfig) {
        const defaults = {
          container: '#chart', series: [],
          xAxis: { values: [], format: (v) => v.toString(), min: null, max: null },
          yAxis: { format: (v) => v.toString(), minPadding: 0.15 },
          options: { totalFrames: 600, visibleWindow: 60, minWindowSize: 30, autoPlay: true, showEndpointValues: true, endpointPadding: 5, onStatsUpdate: null }
        };
        return { ...defaults, ...userConfig, xAxis: { ...defaults.xAxis, ...userConfig.xAxis }, yAxis: { ...defaults.yAxis, ...userConfig.yAxis }, options: { ...defaults.options, ...userConfig.options } };
      }

      #cacheDomElements() {
        this.#dom = {
          container: document.querySelector(this.#config.container),
          playBtn: document.getElementById('playBtn'), playIcon: document.getElementById('playIcon'),
          resetBtn: document.getElementById('resetBtn'), timelineSlider: document.getElementById('timeline'),
          speedBtns: document.querySelectorAll('.speed-btn'), legendContainer: document.getElementById('legend'),
          currentXDisplay: document.getElementById('currentDate'), xDisplay: document.getElementById('dateDisplay'),
          leaderName: document.getElementById('leaderName'), leaderValue: document.getElementById('leaderValue'),
          leaderChange: document.getElementById('leaderChange')
        };
      }

      #getAxisRange() {
        const { values, min, max } = this.#config.xAxis;
        return { min: min ?? 0, max: max ?? values.length - 1 };
      }

      #interpolateData() {
        const { series, options } = this.#config;
        const interpolated = [];
        const totalFrames = options.totalFrames;
        const dataLength = series[0]?.data?.length ?? 0;

        for (let frame = 0; frame <= totalFrames; frame++) {
          const progress = frame / totalFrames;
          const exactIndex = progress * (dataLength - 1);
          const baseIndex = Math.floor(exactIndex);
          const fraction = exactIndex - baseIndex;
          const smoothFraction = fraction < 0.5 ? 4 * fraction * fraction * fraction : 1 - Math.pow(-2 * fraction + 2, 3) / 2;

          const dataPoint = { x: baseIndex + fraction, frameIndex: frame };
          series.forEach(s => {
            const baseValue = s.data[baseIndex] ?? s.data[s.data.length - 1];
            if (baseIndex < dataLength - 1) {
              const nextValue = s.data[baseIndex + 1] ?? baseValue;
              dataPoint[s.id] = baseValue + (nextValue - baseValue) * smoothFraction;
            } else {
              dataPoint[s.id] = baseValue;
            }
          });
          interpolated.push(dataPoint);
        }
        return interpolated;
      }

      #initChart() {
        const { margin } = this.#chart;
        const rect = this.#dom.container.getBoundingClientRect();
        this.#chart.width = rect.width - margin.left - margin.right;
        this.#chart.height = rect.height - margin.top - margin.bottom;

        d3.select(this.#config.container).selectAll('*').remove();

        this.#chart.svg = d3.select(this.#config.container)
          .append('svg')
          .attr('width', this.#chart.width + margin.left + margin.right)
          .attr('height', this.#chart.height + margin.top + margin.bottom)
          .append('g')
          .attr('transform', `translate(${margin.left},${margin.top})`);

        const { min } = this.#getAxisRange();
        this.#chart.xScale = d3.scaleLinear().domain([min, min + this.#config.options.visibleWindow]).range([0, this.#chart.width]);
        this.#chart.yScale = d3.scaleLinear().domain([80000, 120000]).range([this.#chart.height, 0]);

        this.#chart.svg.append('g').attr('class', 'grid-y');
        this.#chart.svg.append('g').attr('class', 'x-axis').attr('transform', `translate(0,${this.#chart.height})`);
        this.#chart.svg.append('g').attr('class', 'y-axis');

        this.#chart.svg.append('defs').append('clipPath').attr('id', 'chart-clip')
          .append('rect').attr('x', 0).attr('y', -10).attr('width', this.#chart.width).attr('height', this.#chart.height + 20);

        const chartArea = this.#chart.svg.append('g').attr('class', 'chart-area').attr('clip-path', 'url(#chart-clip)');
        this.#config.series.forEach((s, idx) => {
          chartArea.append('path').attr('class', `line-glow line-glow-${idx}`).attr('stroke', s.color);
          chartArea.append('path').attr('class', `line-path line-path-${idx}`).attr('stroke', s.color);
          chartArea.append('circle').attr('class', `line-dot line-dot-${idx}`).attr('r', 5).attr('fill', s.color).style('filter', `drop-shadow(0 0 4px ${s.color})`);
        });

        const labelsGroup = this.#chart.svg.append('g').attr('class', 'line-labels');
        this.#config.series.forEach((s, idx) => {
          labelsGroup.append('text').attr('class', `line-value line-value-${idx}`).attr('dy', '0.35em').attr('text-anchor', 'start')
            .attr('fill', s.color).style('font-size', '11px').style('font-family', "'JetBrains Mono', monospace").style('font-weight', '600');
        });

        this.#updateAxes(min);
        this.#updateChart(0);
      }

      #updateAxes(currentX) {
        const { xScale, yScale, svg, width, height } = this.#chart;
        const { series, xAxis, yAxis, options } = this.#config;
        const { min, max } = this.#getAxisRange();
        const { visibleWindow, minWindowSize, endpointPadding } = options;

        const dataSpan = currentX - min;
        const windowSize = Math.min(visibleWindow, Math.max(minWindowSize, dataSpan + 5));
        let startX = currentX - windowSize * 0.75;
        let endX = startX + windowSize;

        if (currentX >= max - 5) {
          endX = max + endpointPadding;
          startX = endX - windowSize;
          if (startX < min) startX = min;
        } else if (startX < min) {
          startX = min;
          endX = startX + windowSize;
        }

        xScale.domain([startX, endX]);

        const visibleRangeData = this.#interpolatedData.filter(d => d.x >= startX && d.x <= currentX);
        let maxYValue = 0, minYValue = Infinity;
        visibleRangeData.forEach(d => {
          series.forEach(s => {
            if (d[s.id] > maxYValue) maxYValue = d[s.id];
            if (d[s.id] < minYValue) minYValue = d[s.id];
          });
        });

        const padding = (maxYValue - minYValue) * yAxis.minPadding || 5000;
        yScale.domain([Math.max(0, minYValue - padding), maxYValue + padding]);

        const dateValues = xAxis.values;
        // X-axis with dynamic ticks based on screen width
        const isSmallScreen = window.innerWidth < 640;
        const xTickCount = isSmallScreen ? 3 : 5;
        const tickStep = Math.max(1, Math.floor((dateValues.length - 1) / xTickCount));

        svg.select('.x-axis').call(d3.axisBottom(xScale)
          .tickValues(d3.range(0, dateValues.length, tickStep).map(i => i))
          .tickFormat(d => {
            const idx = Math.round(d);
            return dateValues[idx] ? dateValues[idx].slice(5) : '';
          }).tickSize(0))
          .selectAll('text').attr('class', 'axis-text').attr('dy', '1.2em');
        svg.selectAll('.x-axis path').attr('class', 'axis-line');

        // Hide Y-axis but keep grid lines
        svg.select('.y-axis').style('display', 'none');

        svg.select('.grid-y').selectAll('line').remove();
        const yMax = yScale.domain()[1];
        const gridValues = Array.from({ length: 6 }, (_, i) => yMax / 6 * (i + 1));
        svg.select('.grid-y').selectAll('line').data(gridValues).enter().append('line').attr('class', 'grid-line')
          .attr('x1', 0).attr('x2', width).attr('y1', d => yScale(d)).attr('y2', d => yScale(d));
      }

      #updateChart(frame) {
        const { xScale, yScale, svg } = this.#chart;
        const { series, yAxis, options } = this.#config;
        const frameIndex = Math.round(frame);
        const data = this.#interpolatedData[frameIndex];
        if (!data) return;

        const currentX = data.x;
        const visibleData = this.#interpolatedData.filter(d => d.x <= currentX);

        this.#updateAxes(currentX);

        series.forEach((s, idx) => {
          const lineGen = d3.line().x(d => xScale(d.x)).y(d => yScale(d[s.id])).curve(d3.curveCatmullRom.alpha(0.5));
          const isHighlighted = this.#state.highlightedSeries === s.id;
          const opacity = this.#state.highlightedSeries ? (isHighlighted ? 1 : 0.12) : 1;

          svg.select(`.line-glow-${idx}`).datum(visibleData).attr('d', lineGen).attr('opacity', opacity * 0.3);
          svg.select(`.line-path-${idx}`).datum(visibleData).attr('d', lineGen).attr('opacity', opacity);

          if (visibleData.length > 0 && options.showEndpointValues) {
            const lastPoint = visibleData[visibleData.length - 1];
            const value = Math.round(lastPoint[s.id]);
            const dotX = xScale(lastPoint.x);
            const dotY = yScale(lastPoint[s.id]);

            // Animate endpoint dot with smooth transition
            svg.select(`.line-dot-${idx}`)
              .transition()
              .duration(80)
              .ease(d3.easeLinear)
              .attr('cx', dotX)
              .attr('cy', dotY)
              .attr('opacity', opacity);

            // Animate endpoint label with smooth transition
            svg.select(`.line-value-${idx}`)
              .transition()
              .duration(80)
              .ease(d3.easeLinear)
              .attr('x', dotX + 10)
              .attr('y', dotY)
              .text(`${s.name} ${yAxis.format(value)}`)
              .attr('opacity', opacity);
          } else {
            svg.select(`.line-dot-${idx}`).transition().duration(80).attr('opacity', 0);
            svg.select(`.line-value-${idx}`).transition().duration(80).attr('opacity', 0);
          }
        });

        this.#updateStats(data);
      }

      #updateStats(data) {
        const { series, xAxis, options } = this.#config;
        const dateValues = xAxis.values;
        const dateIndex = Math.round(data.x);
        const currentDate = dateValues[dateIndex] ?? dateValues[dateValues.length - 1];

        let leader = series[0];
        let maxValue = data[leader?.id] ?? 0;
        series.forEach(s => {
          if (data[s.id] > maxValue) { maxValue = data[s.id]; leader = s; }
        });

        const initialCapital = 100000;
        const changePercent = ((maxValue - initialCapital) / initialCapital * 100).toFixed(2);
        const isPositive = changePercent >= 0;

        if (this.#dom.currentXDisplay) this.#dom.currentXDisplay.textContent = currentDate;
        if (this.#dom.xDisplay) this.#dom.xDisplay.textContent = currentDate;
        if (this.#dom.leaderName) { this.#dom.leaderName.textContent = leader?.name ?? '-'; this.#dom.leaderName.style.color = leader?.color; }
        if (this.#dom.leaderValue) this.#dom.leaderValue.textContent = `¥${Math.round(maxValue).toLocaleString()}`;
        if (this.#dom.leaderChange) { this.#dom.leaderChange.textContent = `${isPositive ? '+' : ''}${changePercent}%`; this.#dom.leaderChange.style.color = isPositive ? '#00d4aa' : '#ef4444'; }

        if (options.onStatsUpdate) options.onStatsUpdate({ date: currentDate, leader, maxValue, changePercent });
      }

      #animate(timestamp) {
        if (!this.#state.lastTime) this.#state.lastTime = timestamp;
        const delta = timestamp - this.#state.lastTime;
        const totalFrames = this.#interpolatedData.length - 1;

        if (delta > 16) {
          this.#state.lastTime = timestamp;
          if (this.#state.isPlaying && this.#state.currentFrame < totalFrames) {
            this.#state.currentFrame += this.#state.speed * 0.5;
            if (this.#state.currentFrame > totalFrames) {
              this.#state.currentFrame = totalFrames;
              this.#state.isPlaying = false;
              this.#updatePlayButton();
            }
            this.#updateChart(this.#state.currentFrame);
            if (this.#dom.timelineSlider) {
              this.#dom.timelineSlider.value = (this.#state.currentFrame / totalFrames) * 100;
            }
          }
        }

        // Only continue animation loop if playing and not at end
        if (this.#state.isPlaying && this.#state.currentFrame < totalFrames) {
          this.#state.animationId = requestAnimationFrame((t) => this.#animate(t));
        } else {
          this.#state.animationId = null;
        }
      }

      #updatePlayButton() {
        if (!this.#dom.playBtn || !this.#dom.playIcon) return;
        this.#dom.playBtn.classList.toggle('active', this.#state.isPlaying);
        this.#dom.playIcon.innerHTML = this.#state.isPlaying
          ? '<rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect>'
          : '<polygon points="7,4 20,12 7,20"></polygon>';
      }

      #bindEvents() {
        if (this.#dom.playBtn) this.#dom.playBtn.addEventListener('click', () => this.togglePlay());
        if (this.#dom.resetBtn) this.#dom.resetBtn.addEventListener('click', () => this.reset());
        if (this.#dom.timelineSlider) {
          this.#dom.timelineSlider.addEventListener('input', (e) => {
            const totalFrames = this.#interpolatedData.length - 1;
            this.#state.currentFrame = (parseFloat(e.target.value) / 100) * totalFrames;
            this.#updateChart(this.#state.currentFrame);
          });
        }
        this.#dom.speedBtns?.forEach(btn => {
          btn.addEventListener('click', () => {
            this.#dom.speedBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            this.#state.speed = parseFloat(btn.dataset.speed);
          });
        });
        let resizeTimeout;
        window.addEventListener('resize', () => { clearTimeout(resizeTimeout); resizeTimeout = setTimeout(() => this.#initChart(), 200); });
      }

      #createLegend() {
        if (!this.#dom.legendContainer) return;
        this.#dom.legendContainer.innerHTML = '';
        this.#config.series.forEach(s => {
          const item = document.createElement('div');
          item.className = 'legend-item';
          item.style.color = s.color;
          item.style.cssText = 'display:flex;align-items:center;gap:8px;padding:7px 12px;background:var(--bg-secondary);border-radius:6px;border:2px solid transparent;cursor:pointer;transition:all 0.2s;';
          item.innerHTML = `<div style="width:8px;height:8px;border-radius:2px;background:${s.color}"></div><span class="text-sm font-medium">${s.name}</span>`;
          item.addEventListener('click', () => {
            if (this.#state.highlightedSeries === s.id) {
              this.#state.highlightedSeries = null;
              item.style.borderColor = 'transparent';
            } else {
              this.#dom.legendContainer.querySelectorAll('.legend-item').forEach(el => el.style.borderColor = 'transparent');
              this.#state.highlightedSeries = s.id;
              item.style.borderColor = s.color;
            }
            this.#updateChart(this.#state.currentFrame);
          });
          this.#dom.legendContainer.appendChild(item);
        });
      }

      init() {
        this.#initChart();
        this.#createLegend();
        if (this.#config.options.autoPlay) {
          this.play();
        }
        return this;
      }

      play() {
        this.#state.isPlaying = true;
        this.#updatePlayButton();
        // Start animation loop if not already running
        if (!this.#state.animationId) {
          this.#state.animationId = requestAnimationFrame((t) => this.#animate(t));
        }
        return this;
      }

      pause() {
        this.#state.isPlaying = false;
        this.#updatePlayButton();
        // Cancel animation loop
        if (this.#state.animationId) {
          cancelAnimationFrame(this.#state.animationId);
          this.#state.animationId = null;
        }
        return this;
      }
      togglePlay() {
        if (this.#state.isPlaying) {
          this.pause();
        } else {
          const totalFrames = this.#interpolatedData.length - 1;
          if (this.#state.currentFrame >= totalFrames) {
            this.reset().play();
          } else {
            this.play();
          }
        }
        return this;
      }
      reset() {
        this.pause();
        this.#state.currentFrame = 0;
        if (this.#dom.timelineSlider) this.#dom.timelineSlider.value = 0;
        this.#updateChart(0);
        return this;
      }
    }

    // ==================== UI Logic ====================
    let chart = null;
    const stockItems = document.querySelectorAll('.stock-item');
    const selectedCountEl = document.getElementById('selectedCount');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const emptyState = document.getElementById('emptyState');
    const chartSection = document.getElementById('chartSection');
    const statsSection = document.getElementById('statsSection');

    function updateSelectedCount() {
      const count = document.querySelectorAll('.stock-checkbox:checked').length;
      selectedCountEl.textContent = `已选择 ${count} 个`;
      stockItems.forEach(item => {
        const checkbox = item.querySelector('.stock-checkbox');
        item.classList.toggle('selected', checkbox.checked);
      });
    }

    stockItems.forEach(item => {
      item.addEventListener('click', (e) => {
        if (e.target.type !== 'checkbox') {
          const checkbox = item.querySelector('.stock-checkbox');
          checkbox.checked = !checkbox.checked;
          updateSelectedCount();
        }
      });
    });

    document.querySelectorAll('.stock-checkbox').forEach(cb => {
      cb.addEventListener('change', updateSelectedCount);
    });

    document.getElementById('selectAllBtn').addEventListener('click', () => {
      document.querySelectorAll('.stock-checkbox').forEach(cb => cb.checked = true);
      updateSelectedCount();
    });

    document.getElementById('clearAllBtn').addEventListener('click', () => {
      document.querySelectorAll('.stock-checkbox').forEach(cb => cb.checked = false);
      updateSelectedCount();
    });

    document.getElementById('loadDataBtn').addEventListener('click', async () => {
      const selectedStocks = Array.from(document.querySelectorAll('.stock-checkbox:checked')).map(cb => parseInt(cb.value));
      const startDate = document.getElementById('startDate').value;
      const interval = document.getElementById('intervalSelect').value;

      if (selectedStocks.length === 0) {
        alert('请至少选择一个股票');
        return;
      }
      if (!startDate) {
        alert('请选择起始日期');
        return;
      }

      loadingOverlay.classList.add('active');

      try {
        const params = new URLSearchParams();
        selectedStocks.forEach(id => params.append('stock_ids[]', id));
        params.append('start_date', startDate);
        params.append('interval', interval);

        const response = await fetch(`/chart/data?${params.toString()}`);
        const data = await response.json();

        if (data.series.length === 0) {
          alert('所选股票在指定日期范围内没有数据');
          loadingOverlay.classList.remove('active');
          return;
        }

        // Update timeline labels
        const dates = data.dates;
        const labels = document.getElementById('timelineLabels').querySelectorAll('span');
        const step = Math.floor((dates.length - 1) / 4);
        labels[0].textContent = dates[0]?.slice(5) ?? '-';
        labels[1].textContent = dates[step]?.slice(5) ?? '-';
        labels[2].textContent = dates[step * 2]?.slice(5) ?? '-';
        labels[3].textContent = dates[step * 3]?.slice(5) ?? '-';
        labels[4].textContent = dates[dates.length - 1]?.slice(5) ?? '-';

        // Show chart
        emptyState.style.display = 'none';
        chartSection.style.display = 'block';
        statsSection.style.display = 'grid';

        // Show interval info
        const intervalNames = {
          'daily': '按天', '2days': '每2天', '3days': '每3天', 'auto': '自动'
        };
        console.log(`数据加载完成: ${intervalNames[data.interval] || data.interval}, 共 ${data.totalPoints} 个数据点`);

        // Destroy old chart if exists
        if (chart) {
          chart.pause();
        }

        // Create new chart
        const chartConfig = {
          container: '#chart',
          series: data.series.map(s => ({
            id: s.id,
            name: s.name,
            color: s.color,
            data: s.data
          })),
          xAxis: {
            values: data.dates,
            format: (v) => v.toString(),
            min: 0,
            max: data.dates.length - 1
          },
          yAxis: {
            format: (v) => `¥${(v / 10000).toFixed(1)}万`,
            minPadding: 0.1
          },
          options: {
            totalFrames: Math.min(600, data.dates.length * 3),
            visibleWindow: Math.min(60, Math.floor(data.dates.length / 3)),
            minWindowSize: Math.min(30, Math.floor(data.dates.length / 5)),
            autoPlay: true,
            showEndpointValues: true,
            endpointPadding: Math.max(5, data.dates.length * 0.05)
          }
        };

        chart = new LineChartRace(chartConfig).init();

      } catch (error) {
        console.error('Error loading data:', error);
        alert('加载数据失败，请重试');
      } finally {
        loadingOverlay.classList.remove('active');
      }
    });

    // Initialize
    // Interval hint update
    const intervalSelect = document.getElementById('intervalSelect');
    const intervalHint = document.getElementById('intervalHint');
    const startDateInput = document.getElementById('startDate');

    const intervalLabels = {
      'auto': '根据时间跨度自动选择',
      'daily': '每天一个数据点',
      '2days': '每2天一个数据点',
      '3days': '每3天一个数据点'
    };

    function updateIntervalHint() {
      const interval = intervalSelect.value;
      const startDate = new Date(startDateInput.value);
      const endDate = new Date();
      const daysDiff = Math.floor((endDate - startDate) / (1000 * 60 * 60 * 24));

      let hint = intervalLabels[interval];
      if (interval === 'auto' && daysDiff > 0) {
        let suggested = 'daily';
        if (daysDiff > 400) suggested = '3days';
        else if (daysDiff > 270) suggested = '2days';
        hint += ` (当前跨度约${daysDiff}天，建议: ${intervalLabels[suggested].replace('一个数据点', '')})`;
      }
      intervalHint.textContent = hint;
    }

    intervalSelect.addEventListener('change', updateIntervalHint);
    startDateInput.addEventListener('change', updateIntervalHint);
    updateIntervalHint();

    updateSelectedCount();
  </script>
</body>
</html>
