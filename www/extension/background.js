chrome.runtime.onInstalled.addListener(() => {
    chrome.contextMenus.create({
        id: "qQuartileLookup",
        title: "Find out quartile (Q1–Q4)",
        contexts: ["selection"],
    });
});

async function openPopupIfSupported(tab) {
    if (!chrome.action || typeof chrome.action.openPopup !== "function") {
        return;
    }

    try {
        const options = tab?.windowId ? { windowId: tab.windowId } : undefined;
        await chrome.action.openPopup(options);
    } catch (error) {
        console.log("Popup auto-open is not available", error);
    }
}

chrome.contextMenus.onClicked.addListener(async (info, tab) => {
    if (info.menuItemId !== "qQuartileLookup") return;

    const query = info.selectionText?.trim();
    if (!query) return;

    const { token } = await chrome.storage.local.get(["token"]);
    const params = new URLSearchParams({ q: query });

    if (token) {
        params.set("token", token);
    }

    const url = `https://imitweby.uhk.cz/widget/presenters/api.php?${params.toString()}`;

    try {
        const response = await fetch(url, { method: "GET" });
        const contentType = response.headers.get("content-type") || "";
        const raw = await response.text();

        if (!response.ok) {
            console.log(`HTTP ${response.status}`);
        }

        if (!contentType.includes("application/json")) {
            console.log("Response is not JSON");
            console.log(raw.slice(0, 500));
            return;
        }

        const data = JSON.parse(raw);
        await chrome.storage.local.set({ lastResult: data });

        const quartile = extractQuartile(data);
        if (quartile && tab?.id) {
            await chrome.action.setBadgeText({
                text: quartile.toUpperCase(),
                tabId: tab.id,
            });
        }

        await openPopupIfSupported(tab);
    } catch (error) {
        console.log(error);
    }
});

function extractQuartile(data) {
    const qData = data?.qData ?? data?.raw?.qData ?? data?.metrics?.qData;

    return (
        data?.quartile ||
        qData?.ranks?.jif?.[0]?.quartile ||
        qData?.ranks?.jci?.[0]?.quartile ||
        qData?.ranks?.articleInfluence?.[0]?.quartile ||
        null
    );
}
