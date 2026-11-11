import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = '#64748b';

const COLORS = {
    navy: '#16365f',
    navySoft: 'rgba(31, 74, 125, 0.15)',
    sky: '#0ea5e9',
    skySoft: 'rgba(14, 165, 233, 0.18)',
    indigo: '#6366f1',
    goal: '#94a3b8',
};

const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

/* ------------------------------------------------------------------ */
/* Redimensiona a foto no navegador antes do upload (mais rápido no 4G
/* e evita o limite de 4,5 MB do Vercel). Converte HEIC/PNG em JPEG.  */
/* ------------------------------------------------------------------ */
async function resizeImage(file, maxSide = 1568, quality = 0.85) {
    let source;
    try {
        source = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        source = await new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = URL.createObjectURL(file);
        });
    }

    const w = source.width;
    const h = source.height;
    const scale = Math.min(1, maxSide / Math.max(w, h));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(w * scale);
    canvas.height = Math.round(h * scale);
    canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    return blob ?? file;
}

function defaultMealType(date = new Date()) {
    const m = date.getHours() * 60 + date.getMinutes();
    if (m < 10 * 60) return 'cafe_da_manha';
    if (m < 11 * 60 + 30) return 'lanche';
    if (m < 15 * 60) return 'almoco';
    if (m < 18 * 60) return 'lanche';
    return 'jantar';
}

const pad = (n) => String(n).padStart(2, '0');

/* ------------------------------------------------------------------ */
/* Modal "Nova refeição"                                              */
/* ------------------------------------------------------------------ */
Alpine.store('mealModal', {
    open: false,
    show() {
        this.open = true;
        window.dispatchEvent(new CustomEvent('meal-modal-opened'));
    },
    hide() {
        this.open = false;
    },
});

Alpine.data('mealForm', (defaultDate) => ({
    file: null,
    preview: null,
    mealType: defaultMealType(),
    date: defaultDate,
    time: '',
    notes: '',
    loading: false,
    error: '',
    result: null,
    redirect: null,

    init() {
        window.addEventListener('meal-modal-opened', () => this.reset());
    },

    reset() {
        const now = new Date();
        this.file = null;
        if (this.preview) URL.revokeObjectURL(this.preview);
        this.preview = null;
        this.mealType = defaultMealType(now);
        this.time = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
        this.notes = '';
        this.loading = false;
        this.error = '';
        this.result = null;
        this.redirect = null;
    },

    async pick(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        this.error = '';
        try {
            this.file = await resizeImage(file);
        } catch {
            this.file = file;
        }
        if (this.preview) URL.revokeObjectURL(this.preview);
        this.preview = URL.createObjectURL(this.file);
    },

    async submit() {
        if (!this.file) {
            this.error = 'Tire ou escolha uma foto do prato.';
            return;
        }
        this.loading = true;
        this.error = '';

        const body = new FormData();
        body.append('photo', this.file, 'prato.jpg');
        body.append('meal_type', this.mealType);
        body.append('date', this.date);
        body.append('time', this.time);
        body.append('notes', this.notes);

        try {
            const res = await fetch('/refeicoes', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                throw new Error(firstError || data.message || 'Não foi possível salvar a refeição.');
            }
            this.result = data.meal;
            this.redirect = data.redirect;
        } catch (e) {
            this.error = e.message;
        } finally {
            this.loading = false;
        }
    },

    finish() {
        window.location.href = this.redirect || '/';
    },

    fmt(value) {
        return Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 });
    },
}));

/* ------------------------------------------------------------------ */
/* Atividade: estimativa de calorias (MET × peso × horas)             */
/* ------------------------------------------------------------------ */
const normalize = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();

Alpine.data('activityForm', (mets, defaultMet, weight) => ({
    activity: '',
    minutes: '',
    weight,
    get known() {
        return Object.keys(mets).some((name) => normalize(name) === normalize(this.activity));
    },
    get met() {
        const key = Object.keys(mets).find((name) => normalize(name) === normalize(this.activity));
        return key ? mets[key] : defaultMet;
    },
    get estimate() {
        if (!this.activity || !this.minutes) return 0;
        return Math.round(this.met * this.weight * (this.minutes / 60));
    },
}));

/* ------------------------------------------------------------------ */
/* Dashboard: abas + gráficos                                         */
/* ------------------------------------------------------------------ */
Alpine.data('dashboard', (initialTab, charts) => ({
    tab: initialTab,
    rendered: {},

    init() {
        this.$nextTick(() => this.renderCharts());
        this.$watch('tab', (value) => {
            const url = new URL(window.location);
            url.searchParams.set('tab', value);
            history.replaceState(null, '', url);
            this.$nextTick(() => this.renderCharts());
        });
    },

    link(date) {
        return `?data=${date}&tab=${this.tab}`;
    },

    renderCharts() {
        const builders = {
            calorias: () => barChart('chart-calories', charts.calories.labels, [
                { label: 'Kcal', data: charts.calories.values, backgroundColor: COLORS.navy, borderRadius: 8, maxBarThickness: 28 },
                goalLine(charts.calories.labels, charts.calories.goal, 'Meta'),
            ]),
            peso: () => lineChart('chart-weight', charts.weight.labels, [
                { label: 'Peso (kg)', data: charts.weight.values, borderColor: COLORS.navy, backgroundColor: COLORS.navySoft, fill: true, tension: 0.35, pointRadius: 3 },
                ...(charts.weight.goal ? [goalLine(charts.weight.labels, charts.weight.goal, 'Meta')] : []),
            ]),
            atividade: () => barChart('chart-activity', charts.activity.labels, [
                { label: 'Minutos', data: charts.activity.values, backgroundColor: COLORS.indigo, borderRadius: 8, maxBarThickness: 28 },
                { label: 'Kcal gastas', data: charts.activity.kcal, backgroundColor: COLORS.skySoft, borderColor: COLORS.sky, borderWidth: 1, borderRadius: 8, maxBarThickness: 28 },
            ]),
            agua: () => barChart('chart-water', charts.water.labels, [
                { label: 'ml', data: charts.water.values, backgroundColor: COLORS.sky, borderRadius: 8, maxBarThickness: 28 },
                goalLine(charts.water.labels, charts.water.goal, 'Meta'),
            ]),
        };

        if (!this.rendered[this.tab] && builders[this.tab]) {
            builders[this.tab]();
            this.rendered[this.tab] = true;
        }
    },
}));

function goalLine(labels, goal, label) {
    return {
        type: 'line',
        label,
        data: labels.map(() => goal),
        borderColor: COLORS.goal,
        borderDash: [6, 6],
        borderWidth: 1.5,
        pointRadius: 0,
        fill: false,
    };
}

const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, grid: { color: '#e7eff7' } },
    },
};

function barChart(id, labels, datasets) {
    const el = document.getElementById(id);
    if (el) new Chart(el, { type: 'bar', data: { labels, datasets }, options: baseOptions });
}

function lineChart(id, labels, datasets) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
        type: 'line',
        data: { labels, datasets },
        options: { ...baseOptions, scales: { ...baseOptions.scales, y: { beginAtZero: false, grid: { color: '#e7eff7' } } } },
    });
}

window.Alpine = Alpine;
Alpine.start();
