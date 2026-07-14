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

async function setStorage(data) {
    return await extApi.storage.local.set(data);
}

async function removeStorage(keys) {
    return await extApi.storage.local.remove(keys);
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
        { field: "articleTitle", label: "Název článku", value: data.articleTitle },
        { field: "journal", label: "Časopis", value: data.journal },
        { field: "issn", label: "ISSN", value: data.issn },
        { field: "year", label: "Rok článku", value: data.year },
        { field: "metricsYear", label: "Rok metrik", value: data.metricsYear },
        { field: "source", label: "Zdroj", value: data.source },
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
                <div>Žádná data</div>
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
        el.innerHTML = "<div>Žádná data</div>";
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
    if (!data) return null;

    return data.articleTitle || data.title || data.input || null;
}

function extractJournalName(data) {
    if (!data) return null;

    const { journalData, qData } = getCompatPayload(data);

    return (
        (typeof data.journal === "string" ? data.journal : null) ||
        journalData?.hits?.[0]?.name ||
        qData?.journal?.name ||
        "Neznámý časopis"
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

    if (!lastResult) {
        articleTitleEl.textContent = "Zatím žádný výsledek";
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

    articleTitleEl.textContent = articleTitle || "Nenalezen název článku";
    journalNameEl.textContent = journalName ? `Časopis: ${journalName}` : "";
    issnEl.textContent = issn || DASH;
    sourceEl.textContent = source || DASH;
    yearEl.textContent = metricsYear
        ? `Metriky ${metricsYear}${year && year !== metricsYear ? `, článek ${year}` : ""}`
        : (year ? String(year) : "");

    const q = (quartile || DASH).toUpperCase();
    pill.textContent = q;
    pill.dataset.tone = getQuartileTone(q);

    hint.style.display = "none";

    if (metrics) {
        metricsEl.textContent = JSON.stringify(metrics, null, 2);
        metricsWrap.style.display = "";
    } else {
        metricsWrap.style.display = "none";
    }
}

async function loginRequest(login, password) {
    const response = await fetch("https://imitweby.uhk.cz/widget/ajax/logIn", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: new URLSearchParams({
            login,
            password,
        }).toString(),
    });

    const data = await response.json();
    return { ok: response.ok, data };
}

async function initPopup() {
    const { token } = await getStorage(["token"]);

    if (!token) {
        showLoginView();
        return;
    }

    showResultView();
    await renderResult();
}

document.addEventListener("DOMContentLoaded", async () => {
    await initPopup();

    const loginBtn = document.getElementById("loginBtn");
    const logoutBtn = document.getElementById("logoutBtn");

    if (loginBtn) {
        loginBtn.addEventListener("click", async () => {
            const login = document.getElementById("login").value.trim();
            const password = document.getElementById("password").value;
            const errorBox = document.getElementById("loginError");

            errorBox.style.display = "none";
            errorBox.textContent = "";

            if (!login || !password) {
                errorBox.textContent = "Vyplň login i heslo.";
                errorBox.style.display = "block";
                return;
            }

            try {
                const result = await loginRequest(login, password);

                if (!result.ok || !result.data?.token) {
                    await removeStorage(["token"]);
                    errorBox.textContent = result.data?.message || "Přihlášení selhalo.";
                    errorBox.style.display = "block";
                    return;
                }

                await setStorage({ token: result.data.token });

                showResultView();
                await renderResult();
            } catch (error) {
                errorBox.textContent = "Nepodařilo se spojit se serverem.";
                errorBox.style.display = "block";
                console.error(error);
            }
        });
    }

    if (logoutBtn) {
        logoutBtn.addEventListener("click", async () => {
            await removeStorage(["token", "lastResult"]);
            showLoginView();
        });
    }
});
