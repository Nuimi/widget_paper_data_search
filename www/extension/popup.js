const extApi = globalThis.browser ?? globalThis.chrome;
const DASH = "-";

function showLoginView() {
    document.getElementById("loginView").style.display = "block";
    document.getElementById("resultView").style.display = "none";
}

function showResultView() {
    document.getElementById("loginView").style.display = "none";
    document.getElementById("resultView").style.display = "block";
}

async function getStorage(keys) {
    return await extApi.storage.local.get(keys);
}

function escapeHtml(value) {
    return String(value ?? DASH)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function getCompatPayload(data) {
    return {
        qData: data?.qData ?? data?.raw?.qData ?? data?.metrics?.qData ?? null,
        journalData: data?.journalData ?? data?.raw?.journal ?? data?.metrics?.journal ?? null,
        wosData: data?.wosData ?? data?.raw?.wosData ?? data?.metrics?.wosData ?? null,
    };
}

function getDisplaySettings(data) {
    return data?.displaySettings ?? {
        hasCustomSettings: false,
        simpleRows: {},
        rankColumns: {},
    };
}

function getRankColumnDefinitions(data, key) {
    const allColumns = {
        category: {
            key: "category",
            label: "Category",
            getValue: row => row.category,
        },
        edition: {
            key: "edition",
            label: "Edition",
            getValue: row => row.edition,
        },
        quartile: {
            key: "quartile",
            label: "Quartile",
            getValue: row => row.quartile,
        },
        rank: {
            key: "rank",
            label: "Rank",
            getValue: row => row.rank,
        },
        percentile: {
            key: "percentile",
            label: "Percentile",
            getValue: row => row.jifPercentile ?? row.jciPercentile ?? DASH,
        },
    };

    const displaySettings = getDisplaySettings(data);
    if (!displaySettings.hasCustomSettings) {
        return [
            allColumns.category,
            allColumns.edition,
            allColumns.quartile,
            allColumns.rank,
            allColumns.percentile,
        ];
    }

    return (displaySettings.rankColumns?.[key] ?? [])
        .map(columnKey => allColumns[columnKey])
        .filter(Boolean);
}

function renderSimpleRows(data) {
    const displaySettings = getDisplaySettings(data);
    const rows = [
        { field: "articleTitle", label: "Paper name", value: data.articleTitle },
        { field: "journal", label: "Journal", value: data.journal },
        { field: "issn", label: "ISSN", value: data.issn },
        { field: "year", label: "Paper year", value: data.year },
        { field: "metricsYear", label: "Metric year", value: data.metricsYear },
        { field: "source", label: "Source", value: data.source },
        { field: "doi", label: "DOI", value: data.doi },
        { field: "jif", label: "JIF", value: data.jif },
        { field: "jif5Years", label: "JIF 5 Years", value: data.jif5Years },
        { field: "jifWithoutSelfCitations", label: "JIF Without Self Citations", value: data.jifWithoutSelfCitations },
        { field: "jci", label: "JCI", value: data.jci },
        { field: "immediacyIndex", label: "Immediacy Index", value: data.immediacyIndex },
        { field: "totalCites", label: "Total Cites", value: data.totalCites },
        { field: "articleInfluence", label: "Article Influence", value: data.articleInfluence },
        { field: "eigenFactorScore", label: "EigenFactor Score", value: data.eigenFactorScore },
        { field: "eigenFactorNormalized", label: "EigenFactor Normalized", value: data.eigenFactorNormalized },
        { field: "jifPercentile", label: "JIF Percentile", value: data.jifPercentile },
        { field: "citableItemsTotal", label: "Citable Items Total", value: data.citableItemsTotal },
        { field: "citableItemsArticlesPercentage", label: "Articles Percentage", value: data.citableItemsArticlesPercentage },
        { field: "halfLifeCited", label: "Half Life Cited", value: data.halfLifeCited },
        { field: "halfLifeCiting", label: "Half Life Citing", value: data.halfLifeCiting },
    ];

    const visibleRows = rows.filter(row => {
        if (!displaySettings.hasCustomSettings) {
            return true;
        }

        return Boolean(displaySettings.simpleRows?.[row.field]);
    });

    if (visibleRows.length === 0) {
        return "";
    }

    return `
        <div class="metric-list">
            ${visibleRows.map(row => `
                <div class="metric-row">
                    <div class="metric-label">${escapeHtml(row.label)}</div>
                    <div class="metric-value">${escapeHtml(row.value)}</div>
                </div>
            `).join("")}
        </div>
    `;
}

function renderRankTable(title, rows, columns) {
    if (!Array.isArray(columns) || columns.length === 0) {
        return "";
    }

    if (!Array.isArray(rows) || rows.length === 0) {
        return `
            <div class="metric-block">
                <h4>${escapeHtml(title)}</h4>
                <div>No data foud</div>
            </div>
        `;
    }

    return `
        <div class="metric-block">
            <h4>${escapeHtml(title)}</h4>
            <div class="table-wrap">
                <table class="metric-table">
                    <thead>
                        <tr>
                            ${columns.map(column => `<th>${escapeHtml(column.label)}</th>`).join("")}
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map(row => `
                            <tr>
                                ${columns.map(column => `<td>${escapeHtml(column.getValue(row))}</td>`).join("")}
                            </tr>
                        `).join("")}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function renderAllMetrics(data) {
    const el = document.getElementById("allMetrics");
    if (!el) return;

    if (!data) {
        el.innerHTML = "<div>No data found</div>";
        return;
    }

    if (data.error) {
        el.innerHTML = `<div>${escapeHtml(data.error)}</div>`;
        return;
    }

    const sections = [
        renderSimpleRows(data),
        renderRankTable("JIF Ranks", data.jifRanks, getRankColumnDefinitions(data, "jifRanks")),
        renderRankTable("Article Influence Ranks", data.articleInfluenceRanks, getRankColumnDefinitions(data, "articleInfluenceRanks")),
    ].filter(Boolean);

    el.innerHTML = sections.join("");
}

function getQuartileTone(q) {
    switch ((q || "").toUpperCase()) {
        case "Q1":
            return "good";
        case "Q2":
            return "mid";
        case "Q3":
        case "Q4":
            return "bad";
        default:
            return "neutral";
    }
}

function extractQuartile(data) {
    if (!data) return null;
    if (data.quartile) return data.quartile;

    const { qData } = getCompatPayload(data);

    return (
        qData?.ranks?.jif?.[0]?.quartile ||
        qData?.ranks?.jci?.[0]?.quartile ||
        qData?.ranks?.articleInfluence?.[0]?.quartile ||
        qData?.ranks?.eigenFactorScore?.[0]?.quartile ||
        null
    );
}

function extractArticleTitle(data) {
    for (const id of ['grid_data', 'allMetrics', 'pillQuartile']) {
        document.getElementById(id).style.display = '';
    }
    return data?.articleTitle || data?.input || null;
}

function extractJournalName(data) {
    if (!data) return null;

    const { journalData, qData } = getCompatPayload(data);

    return (
        (typeof data.journal === "string" ? data.journal : null) ||
        journalData?.hits?.[0]?.name ||
        qData?.journal?.name ||
        "Unknow journal"
    );
}

function extractYear(data) {
    if (!data) return null;

    return data.year || null;
}

function extractMetricsYear(data) {
    if (!data) return null;

    const { qData } = getCompatPayload(data);

    return data.metricsYear || qData?.year || null;
}

function extractIssn(data) {
    if (!data) return null;

    const { journalData } = getCompatPayload(data);

    return (
        data.issn ||
        journalData?.hits?.[0]?.matches?.find(match => match.field === "issn")?.value?.[0]?.replace(/<[^>]+>/g, "") ||
        null
    );
}

function extractSource(data) {
    if (!data) return DASH;

    return data.source || "Web of Science";
}

function extractMetrics(data) {
    if (!data) return null;

    return data.metrics || data.raw || data;
}

async function renderResult() {
    const { lastResult } = await getStorage(["lastResult"]);

    const articleTitleEl = document.getElementById("articleTitle");
    const journalNameEl = document.getElementById("journalName");
    const issnEl = document.getElementById("issn");
    const sourceEl = document.getElementById("source");
    const yearEl = document.getElementById("year");
    const pill = document.getElementById("pillQuartile");
    const hint = document.getElementById("hint");
    const metricsWrap = document.getElementById("metricsWrap");
    const metricsEl = document.getElementById("metrics");

    document.getElementById("allMetrics").innerHTML = "";
    if (!lastResult) {
        articleTitleEl.textContent = "Nothing found yet";
        journalNameEl.textContent = "";
        issnEl.textContent = DASH;
        sourceEl.textContent = DASH;
        yearEl.textContent = "";
        pill.textContent = DASH;
        pill.dataset.tone = "neutral";
        hint.style.display = "block";
        metricsWrap.style.display = "none";
        return;
    }

    if (lastResult.error) {
        articleTitleEl.textContent = lastResult.error;
        journalNameEl.textContent = "";
        issnEl.textContent = DASH;
        sourceEl.textContent = DASH;
        yearEl.textContent = "";
        pill.textContent = DASH;
        pill.dataset.tone = "neutral";
        hint.style.display = "none";
        metricsWrap.style.display = "none";
        return;
    }

    const articleTitle = extractArticleTitle(lastResult);
    const journalName = extractJournalName(lastResult);
    const issn = extractIssn(lastResult);
    const source = extractSource(lastResult);
    const year = extractYear(lastResult);
    const metricsYear = extractMetricsYear(lastResult);
    const quartile = extractQuartile(lastResult);
    const metrics = extractMetrics(lastResult);

    renderAllMetrics(lastResult);

    articleTitleEl.textContent = articleTitle || "Paper title not found";
    journalNameEl.textContent = journalName ? `Journal: ${journalName}` : "";
    issnEl.textContent = issn || DASH;
    sourceEl.textContent = source || DASH;
    yearEl.textContent = metricsYear
        ? `Metrics ${metricsYear}${year && year !== metricsYear ? `, paper ${year}` : ""}`
        : (year ? String(year) : "");

    const q = (quartile || DASH).toUpperCase();
    pill.textContent = q;
    pill.dataset.tone = getQuartileTone(q);

    hint.style.display = "none";

    if (metrics) {
        metricsEl.textContent = JSON.stringify(metrics, null, 2);
        if (lastResult.isWoS)
        {
            metricsWrap.style.display = "";
        }
    } else {
        metricsWrap.style.display = "none";
    }
}

async function sendCommand(type, data = {}) {
    return extApi.runtime.sendMessage({ type, ...data });
}

async function initPopup() {
    const status = await sendCommand('AUTH_STATUS');
    if (!status?.authenticated) {
        showLoginView();
        return;
    }
    showResultView();
    await renderResult();
}

document.addEventListener('DOMContentLoaded', async () => {
    const loginBtn = document.getElementById('loginBtn');
    const logoutBtn = document.getElementById('logoutBtn');
    const searchBtn = document.getElementById('searchBtn');
    const searchStatus = document.getElementById('searchStatus');
    const loginError = document.getElementById('loginError');

    loginBtn.addEventListener('click', async () => {
        const login = document.getElementById('login').value.trim();
        const passwordInput = document.getElementById('password');
        const password = passwordInput.value;
        loginError.style.display = 'none';
        if (!login || !password) {
            loginError.textContent = 'Fill login and password.';
            loginError.style.display = 'block';
            return;
        }
        loginBtn.disabled = true;
        try {
            const result = await sendCommand('AUTH_LOGIN', { login, password });
            if (!result.ok) throw new Error(result.error || 'Login failed.');
            passwordInput.value = '';
            showResultView();
            await renderResult();
        } catch (error) {
            loginError.textContent = error.message;
            loginError.style.display = 'block';
        } finally { loginBtn.disabled = false; }
    });

    logoutBtn.addEventListener('click', async () => {
        logoutBtn.disabled = true;
        try {
            const result = await sendCommand('AUTH_LOGOUT');
            if (!result.ok) throw new Error(result.error || 'Could not log out. Please retry.');
            searchStatus.textContent = '';
            document.getElementById('searchQuery').value = '';
            showLoginView();
        } catch (error) {
            searchStatus.textContent = error.message;
        } finally { logoutBtn.disabled = false; }
    });

    document.getElementById('searchForm').addEventListener('submit', async event => {
        event.preventDefault();
        if (searchBtn.disabled) return;
        searchBtn.disabled = true;
        searchStatus.textContent = 'Searching…';
        document.getElementById('allMetrics').innerHTML = '';
        document.getElementById('metricsWrap').style.display = 'none';
        document.getElementById('articleTitle').textContent = 'Searching…';
        document.getElementById('pillQuartile').textContent = DASH;
        try {
            const result = await sendCommand('LOOKUP', { query: document.getElementById('searchQuery').value });
            if (result.authRequired) {
                showLoginView();
                loginError.textContent = result.error;
                loginError.style.display = 'block';
                return;
            }
            if (!result.ok) throw new Error(result.error || 'Lookup failed.');
            searchStatus.textContent = result.data.isWoS ? '' : 'No journal metrics found for this match.';
            await renderResult();
        } catch (error) {
            searchStatus.textContent = error.message;
            document.getElementById('articleTitle').textContent = 'Search was not completed';
        } finally { searchBtn.disabled = false; }
    });

    extApi.storage.onChanged.addListener(async (changes, area) => {
        if (area !== 'local') return;
        if (changes.token && !changes.token.newValue) showLoginView();
        if (changes.lastResult) await renderResult();
    });
    try { await initPopup(); }
    catch { showLoginView(); }
});
