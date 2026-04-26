window.onload = function() {
    // 1. Détection Ruche
    const urlParams = new URLSearchParams(window.location.search);
    const hiveName = urlParams.get('ruche') || "Ruche Générale";
    const titleElem = document.getElementById('selected-hive-title');
    if (titleElem) titleElem.innerText = hiveName;

    const canvas = document.getElementById('historyChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    if (typeof ChartZoom !== 'undefined') Chart.register(ChartZoom);

    let now = Date.now();
    const viewDuration = 30 * 60000; 
    const totalHistory = 180 * 60000; 

    // Simulation données cohérentes
    let baseT = hiveName.includes("Beta") ? 38 : 34;
    let tempData = [];
    let weightData = [];
    for (let i = 0; i < 180; i++) {
        const time = new Date(now - (180 - i) * 60000);
        tempData.push({ x: time, y: (baseT + (Math.random() * 0.4)).toFixed(1) });
        weightData.push({ x: time, y: (41 + (Math.random() * 1.2)).toFixed(1) });
    }

    const historyChart = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [
                { 
                    label: 'Température (°C)', 
                    data: tempData, 
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    yAxisID: 'yTemp',
                    pointRadius: 2,
                    tension: 0.3,
                    fill: true
                },
                { 
                    label: 'Poids (kg)', 
                    data: weightData, 
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    yAxisID: 'yWeight',
                    pointRadius: 2,
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onHover: (e) => { e.native.target.style.cursor = 'default'; },
            scales: {
                x: { 
                    type: 'time', 
                    time: { unit: 'minute', displayFormats: { minute: 'HH:mm' } },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                yTemp: {
                    type: 'linear', position: 'left',
                    title: { display: true, text: 'TEMPÉRATURE (°C)', color: '#2ecc71', font: { size: 10 } }
                },
                yWeight: {
                    type: 'linear', position: 'right',
                    title: { display: true, text: 'POIDS (KG)', color: '#3498db', font: { size: 10 } },
                    grid: { display: false }
                }
            },
            plugins: {
                legend: { position: 'top', labels: { color: '#fff', usePointStyle: true } },
                zoom: {
                    zoom: { wheel: { enabled: true }, mode: 'x' },
                    pan: { enabled: false }
                }
            }
        }
    });

    const scroller = document.getElementById('chartScroller');
    if (scroller) {
        scroller.addEventListener('input', () => {
            const val = scroller.value;
            const start = (now - totalHistory) + ((totalHistory - viewDuration) * (val / 100));
            historyChart.options.scales.x.min = start;
            historyChart.options.scales.x.max = start + viewDuration;
            historyChart.update('none');
        });
        
        // Init vue sur la fin
        historyChart.options.scales.x.min = now - viewDuration;
        historyChart.options.scales.x.max = now;
        historyChart.update();
    }
};